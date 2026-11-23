<?php

namespace Tests\Feature;

use App\Models\SolicitudReferencia;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolicitudEvolucionVistaTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::query()->where('user_name', 'superadmin')->firstOrFail();
    }

    private function solicitudPendiente(): SolicitudReferencia
    {
        return SolicitudReferencia::create([
            'fecha' => now()->toDateString(),
            'hora' => '09:00',
            'primer_nombre' => 'Ana',
            'primer_apellido' => 'Prueba',
            'genero' => 'F',
            'edad' => 35,
            'tipo_documento' => 'CC',
            'numero_documento' => (string) random_int(10000000, 99999999),
            'eps' => 'SANITAS EPS',
            'especialidad_requerida' => 'Medicina Interna',
            'servicio_ubicacion_actual' => 'URGENCIAS',
            'resumen_historia_clinica' => 'Resumen.',
            'estado' => 'pendiente_cama',
        ]);
    }

    private function sinVer(User $staff, SolicitudReferencia $solicitud): int
    {
        $fila = collect($this->actingAs($staff)->getJson('/api/solicitudes-referencia')->assertOk()->json('data'))
            ->firstWhere('id', $solicitud->id);

        return $fila['evoluciones_sin_ver'];
    }

    public function test_la_lista_cuenta_solo_las_evoluciones_que_no_se_han_visto(): void
    {
        $staff = $this->staff();
        $solicitud = $this->solicitudPendiente();
        $solicitud->evoluciones()->create(['nota' => 'Primera']);
        $solicitud->evoluciones()->create(['nota' => 'Segunda']);

        $this->assertSame(2, $this->sinVer($staff, $solicitud));
    }

    public function test_al_abrir_las_evoluciones_el_numero_se_quita_y_vuelve_con_una_nueva(): void
    {
        $staff = $this->staff();
        $solicitud = $this->solicitudPendiente();
        $solicitud->evoluciones()->create(['nota' => 'Primera']);

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/evoluciones/vistas")
            ->assertOk()
            ->assertJsonPath('data.evoluciones_sin_ver', 0);

        $this->assertSame(0, $this->sinVer($staff, $solicitud));
        $this->assertSame(1, $solicitud->evoluciones()->count());

        $solicitud->evoluciones()->create(['nota' => 'Nueva']);

        $this->assertSame(1, $this->sinVer($staff, $solicitud));
    }

    public function test_marcar_como_vistas_exige_sesion(): void
    {
        $solicitud = $this->solicitudPendiente();

        $this->postJson("/api/solicitudes-referencia/{$solicitud->id}/evoluciones/vistas")->assertUnauthorized();
    }
}
