<?php

namespace Tests\Feature;

use App\Mail\NuevaSolicitudReferenciaInterna;
use App\Models\SolicitudReferencia;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CancelarSolicitudTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::query()->where('user_name', 'superadmin')->firstOrFail();
    }

    private function solicitud(string $estado): SolicitudReferencia
    {
        return SolicitudReferencia::create([
            'fecha' => '2026-11-05',
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

    /**
     * @return array<string, string>
     */
    private function cambio(string $estado = 'cancelado'): array
    {
        return [
            'estado' => $estado,
            'motivo' => 'La clínica canceló el traslado',
            'nombre_quien_responde' => 'Super Administrador',
            'hora_respuesta' => '10:00',
        ];
    }

    public function test_a_pending_request_can_be_cancelled_and_moves_to_history(): void
    {
        Mail::fake();
        $staff = $this->staff();
        $solicitud = $this->solicitud('pendiente_cama');

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/cambiar-estado", $this->cambio())
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelado');

        $this->actingAs($staff)->getJson('/api/solicitudes-referencia/historico')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estado', 'cancelado');
    }

    public function test_cancelling_does_not_require_an_eps_or_other_acceptance_data(): void
    {
        Mail::fake();
        $solicitud = $this->solicitud('en_espera');

        $this->actingAs($this->staff())
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/cambiar-estado", $this->cambio())
            ->assertOk();

        $this->assertSame('cancelado', $solicitud->fresh()->estado);
    }

    public function test_a_cancelled_request_cannot_be_denied_or_cancelled_again(): void
    {
        Mail::fake();
        $staff = $this->staff();
        $solicitud = $this->solicitud('cancelado');

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/negar", [
                'hora_respuesta' => '10:00',
                'grupo_negacion' => 'no_red',
                'motivo_negacion' => 'Sin cupo',
                'eps' => 'SANITAS EPS',
                'nombre_quien_responde' => 'Super Administrador',
            ])
            ->assertStatus(422);

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/cambiar-estado", $this->cambio())
            ->assertStatus(422);
    }

    public function test_new_requests_still_cannot_be_cancelled_through_manual_state_change(): void
    {
        Mail::fake();
        $solicitud = $this->solicitud('pendiente');

        $this->actingAs($this->staff())
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/cambiar-estado", $this->cambio())
            ->assertStatus(422);

        Mail::assertNotSent(NuevaSolicitudReferenciaInterna::class);
    }
}
