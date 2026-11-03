<?php

namespace Tests\Feature;

use App\Models\SolicitudReferencia;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SoportesRespuestaTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::query()->where('user_name', 'superadmin')->firstOrFail();
    }

    private function solicitud(string $estado = 'pendiente'): SolicitudReferencia
    {
        return SolicitudReferencia::create([
            'fecha' => '2026-11-03',
            'hora' => '09:30',
            'primer_nombre' => 'Ana',
            'primer_apellido' => 'Prueba',
            'genero' => 'F',
            'edad' => 35,
            'tipo_documento' => 'CC',
            'numero_documento' => '12345678',
            'eps' => 'SANITAS EPS',
            'municipio_capita' => 'Cali, Valle del Cauca',
            'especialidad_requerida' => 'Medicina Interna',
            'servicio_ubicacion_actual' => 'URGENCIAS',
            'resumen_historia_clinica' => 'Resumen.',
            'correo_contacto' => 'clinica@example.com',
            'estado' => $estado,
        ]);
    }

    public function test_accepting_without_availability_stores_attached_supports(): void
    {
        Mail::fake();
        Storage::fake('local');
        $solicitud = $this->solicitud();

        $this->actingAs($this->staff())
            ->post("/api/solicitudes-referencia/{$solicitud->id}/aceptar", [
                'hora_respuesta' => '10:00',
                'nombre_quien_responde' => 'Super Administrador',
                'sin_disponibilidad' => '1',
                'eps' => 'SANITAS EPS',
                'soportes' => [UploadedFile::fake()->image('evidencia.png'), UploadedFile::fake()->create('autorizacion.pdf', 20, 'application/pdf')],
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertSame(2, $solicitud->adjuntos()->count());
        $this->assertSame('pendiente_cama', $solicitud->fresh()->estado);
    }

    public function test_denying_stores_attached_supports(): void
    {
        Mail::fake();
        Storage::fake('local');
        $solicitud = $this->solicitud();

        $this->actingAs($this->staff())
            ->post("/api/solicitudes-referencia/{$solicitud->id}/negar", [
                'hora_respuesta' => '10:00',
                'grupo_negacion' => 'no_red',
                'motivo_negacion' => 'Sin cupo',
                'eps' => 'SANITAS EPS',
                'nombre_quien_responde' => 'Super Administrador',
                'soportes' => [UploadedFile::fake()->image('evidencia.png')],
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertSame(1, $solicitud->adjuntos()->count());
        $this->assertSame('negado', $solicitud->fresh()->estado);
    }

    public function test_more_than_five_supports_are_rejected(): void
    {
        Mail::fake();
        Storage::fake('local');
        $solicitud = $this->solicitud();

        $this->actingAs($this->staff())
            ->post("/api/solicitudes-referencia/{$solicitud->id}/negar", [
                'hora_respuesta' => '10:00',
                'grupo_negacion' => 'no_red',
                'motivo_negacion' => 'Sin cupo',
                'eps' => 'SANITAS EPS',
                'nombre_quien_responde' => 'Super Administrador',
                'soportes' => array_map(fn (int $i) => UploadedFile::fake()->image("{$i}.png"), range(1, 6)),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertSame('pendiente', $solicitud->fresh()->estado);
    }
}
