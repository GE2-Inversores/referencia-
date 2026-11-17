<?php

namespace Tests\Feature;

use App\Models\Clinica;
use App\Models\SolicitudReferencia;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardCacheTest extends TestCase
{
    use RefreshDatabase;

    private const VERSION = 'dashboard_stats_version';

    private function solicitud(): SolicitudReferencia
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
            'municipio_capita' => 'Cali',
            'especialidad_requerida' => 'Medicina Interna',
            'servicio_ubicacion_actual' => 'URGENCIAS',
            'resumen_historia_clinica' => 'Resumen.',
            'estado' => 'pendiente',
        ]);
    }

    public function test_cambiar_una_solicitud_descarta_las_estadisticas_guardadas(): void
    {
        $this->seed(DatabaseSeeder::class);
        $antes = Cache::get(self::VERSION);

        $solicitud = $this->solicitud();
        $trasCrear = Cache::get(self::VERSION);
        $solicitud->update(['estado' => 'negado']);
        $trasActualizar = Cache::get(self::VERSION);

        $this->assertNotSame($antes, $trasCrear);
        $this->assertNotSame($trasCrear, $trasActualizar);
    }

    public function test_cambios_en_clinicas_tambien_descartan_las_estadisticas(): void
    {
        $antes = Cache::get(self::VERSION);

        Clinica::create(['nit' => '900000001', 'nombre' => 'Clínica Norte', 'email' => 'norte@example.com']);

        $this->assertNotSame($antes, Cache::get(self::VERSION));
    }

    public function test_iniciar_sesion_no_descarta_las_estadisticas_pero_desactivar_un_usuario_si(): void
    {
        $this->seed(DatabaseSeeder::class);
        $usuario = User::query()->where('user_name', 'superadmin')->firstOrFail();
        $inicial = Cache::get(self::VERSION);

        $usuario->update(['last_login_at' => now()]);
        $this->assertSame($inicial, Cache::get(self::VERSION));

        $usuario->update(['is_active' => false]);
        $this->assertNotSame($inicial, Cache::get(self::VERSION));
    }
}
