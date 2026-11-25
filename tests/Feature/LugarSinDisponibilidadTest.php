<?php

namespace Tests\Feature;

use App\Models\InboxMensaje;
use App\Models\SolicitudReferencia;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LugarSinDisponibilidadTest extends TestCase
{
    use RefreshDatabase;

    private const LUGAR = 'ANGIOGRAFIA/HEMODINAMIA';

    private function staff(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::query()->where('user_name', 'superadmin')->firstOrFail();
    }

    private function solicitud(User $staff): SolicitudReferencia
    {
        $correo = InboxMensaje::create([
            'gmail_id' => 'g'.uniqid(),
            'remitente' => 'Clínica Norte <norte@example.com>',
            'remitente_email' => 'norte@example.com',
            'asunto' => 'Remisión',
            'recibido_en' => now(),
            'leido' => false,
        ]);

        $this->actingAs($staff)->postJson("/api/inbox/{$correo->id}/solicitud", [
            'tipo_documento' => 'CC', 'numero_documento' => '555', 'nombres' => 'Ana', 'apellidos' => 'Pérez',
            'edad' => 45, 'genero' => 'F', 'eps' => 'SANITAS EPS', 'municipio' => 'Bucaramanga',
            'historia_clinica' => 'Remitido por correo.', 'diagnostico' => 'Neumonía',
            'especialidad' => 'Medicina interna', 'tipo_referencia' => 'Referencia redonda', 'servicio' => 'UCI',
        ])->assertOk();

        return SolicitudReferencia::where('inbox_mensaje_id', $correo->id)->firstOrFail();
    }

    private function ultimoCorreoHtml(): string
    {
        $mensajes = app('mail.manager')->mailer()->getSymfonyTransport()->messages();

        return (string) $mensajes->last()->getOriginalMessage()->getHtmlBody();
    }

    /**
     * @return array<string, mixed>
     */
    private function datosSinDisponibilidad(): array
    {
        return [
            'sin_disponibilidad' => true,
            'hora_respuesta' => '10:30',
            'nombre_quien_responde' => 'Super Administrador',
            'eps' => 'SANITAS EPS',
        ];
    }

    public function test_sin_disponibilidad_exige_el_lugar_y_lo_guarda(): void
    {
        $staff = $this->staff();
        $solicitud = $this->solicitud($staff);

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/aceptar", $this->datosSinDisponibilidad())
            ->assertStatus(422)
            ->assertJsonPath('data.errors.lugar_llegada.0', 'The lugar llegada field is required.');

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/aceptar", $this->datosSinDisponibilidad() + ['lugar_llegada' => self::LUGAR])
            ->assertOk();

        $solicitud->refresh();
        $this->assertSame('pendiente_cama', $solicitud->estado);
        $this->assertSame(self::LUGAR, $solicitud->lugar_llegada);
        $this->assertNull($solicitud->codigo_aceptacion);
    }

    public function test_tiempo_de_llegada_se_guarda_independiente_de_la_hora(): void
    {
        $staff = $this->staff();

        foreach ([6, 12] as $horas) {
            $solicitud = $this->solicitud($staff);

            $this->actingAs($staff)
                ->postJson("/api/solicitudes-referencia/{$solicitud->id}/aceptar", [
                    'hora_respuesta' => '10:30',
                    'nombre_quien_responde' => 'Super Administrador',
                    'eps' => 'SANITAS EPS',
                    'sede' => 'URGENCIAS',
                    'codigo_aceptacion' => 'REF-TIEMPO-'.$horas,
                    'hora_llegada' => '14:30',
                    'tiempo_llegada_horas' => (string) $horas,
                    'lugar_llegada' => self::LUGAR,
                ])
                ->assertOk()
                ->assertJsonPath('data.tiempo_llegada_horas', $horas)
                ->assertJsonPath('data.hora_llegada', '14:30');

            $this->assertNotNull($solicitud->fresh()->tiempo_llegada_inicia_en);

            $this->assertSame($horas, $solicitud->fresh()->tiempo_llegada_horas);
            $this->assertSame('14:30', $solicitud->fresh()->hora_llegada);
        }
    }

    public function test_tiempo_de_llegada_rechaza_valores_distintos_de_seis_y_doce(): void
    {
        $staff = $this->staff();
        $solicitud = $this->solicitud($staff);

        foreach ([0, 8, 24, 'otro', 6.5] as $horas) {
            $this->actingAs($staff)
                ->postJson("/api/solicitudes-referencia/{$solicitud->id}/aceptar", $this->datosSinDisponibilidad() + [
                    'lugar_llegada' => self::LUGAR,
                    'tiempo_llegada_horas' => $horas,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('tiempo_llegada_horas', 'data.errors');
        }

        $this->assertSame('pendiente', $solicitud->fresh()->estado);
        $this->assertNull($solicitud->fresh()->tiempo_llegada_horas);
    }

    public function test_el_correo_sin_cupo_no_lleva_el_lugar_y_el_del_codigo_si(): void
    {
        $staff = $this->staff();
        $solicitud = $this->solicitud($staff);

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/aceptar", $this->datosSinDisponibilidad() + ['lugar_llegada' => self::LUGAR])
            ->assertOk();

        $sinCupo = $this->ultimoCorreoHtml();
        $this->assertStringContainsString('en espera de cupo', mb_strtolower($sinCupo));
        $this->assertStringNotContainsString(self::LUGAR, $sinCupo);

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/aceptar", [
                'hora_respuesta' => '11:00',
                'nombre_quien_responde' => 'Super Administrador',
                'eps' => 'SANITAS EPS',
                'sede' => 'URGENCIAS',
                'codigo_aceptacion' => 'REF00099',
                'hora_llegada' => '14:30',
                'lugar_llegada' => self::LUGAR,
            ])
            ->assertOk();

        $this->assertSame('en_espera', $solicitud->fresh()->estado);
        $this->assertStringContainsString(self::LUGAR, $this->ultimoCorreoHtml());
    }
}
