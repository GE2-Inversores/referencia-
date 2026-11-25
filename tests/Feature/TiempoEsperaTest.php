<?php

namespace Tests\Feature;

use App\Models\SolicitudReferencia;
use App\Models\SolicitudReferenciaEvento;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TiempoEsperaTest extends TestCase
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
            'estado' => $estado,
        ]);
    }

    public function test_lista_solo_los_aceptados_en_espera_con_su_hora(): void
    {
        $enEspera = $this->solicitud('en_espera');
        $this->solicitud('pendiente');
        $this->solicitud('negado');

        $evento = SolicitudReferenciaEvento::create([
            'solicitud_referencia_id' => $enEspera->id,
            'tipo' => 'en_espera',
            'titulo' => 'Aceptado',
        ]);
        $aceptadoEn = now()->subMinutes(45);
        $evento->timestamps = false;
        $evento->created_at = $aceptadoEn;
        $evento->save();

        $this->actingAs($this->staff())
            ->getJson('/api/solicitudes-referencia/en-espera-tiempo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $enEspera->id)
            ->assertJsonPath('data.0.paciente', 'Ana Prueba')
            ->assertJsonPath('data.0.eps', 'SANITAS EPS')
            ->assertJsonPath('data.0.aceptado_en', $aceptadoEn->toIso8601String());
    }

    public function test_incluye_el_tiempo_de_llegada_y_su_inicio_para_la_cuenta_regresiva(): void
    {
        $solicitud = $this->solicitud('en_espera');
        $solicitud->tiempo_llegada_horas = 6;
        $solicitud->tiempo_llegada_inicia_en = now()->subMinutes(10)->startOfSecond();
        $solicitud->save();

        $this->actingAs($this->staff())
            ->getJson('/api/solicitudes-referencia/en-espera-tiempo')
            ->assertOk()
            ->assertJsonPath('data.0.tiempo_llegada_horas', 6)
            ->assertJsonPath('data.0.tiempo_llegada_inicia_en', now()->subMinutes(10)->startOfSecond()->toIso8601String());
    }

    public function test_sin_aceptados_responde_lista_vacia(): void
    {
        $this->solicitud('pendiente');

        $this->actingAs($this->staff())
            ->getJson('/api/solicitudes-referencia/en-espera-tiempo')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
