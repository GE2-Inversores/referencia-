<?php

namespace Tests\Feature;

use App\Mail\RespuestaEvolucionClinica;
use App\Mail\SolicitudEvolucionClinica;
use App\Models\InboxMensaje;
use App\Models\SolicitudEvolucion;
use App\Models\SolicitudReferencia;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RespuestaEvolucionTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::query()->where('user_name', 'superadmin')->firstOrFail();
    }

    private function solicitudConEvolucion(User $staff, string $nota = 'Paciente estable, sin fiebre.'): SolicitudEvolucion
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
            'tipo_documento' => 'CC', 'numero_documento' => '987654321', 'nombres' => 'Ana', 'apellidos' => 'Pérez',
            'edad' => 45, 'genero' => 'F', 'eps' => 'SANITAS EPS', 'municipio' => 'Bucaramanga',
            'historia_clinica' => 'Remitido por correo.', 'diagnostico' => 'Neumonía',
            'especialidad' => 'Medicina interna', 'tipo_referencia' => 'Referencia redonda', 'servicio' => 'UCI',
        ])->assertOk();

        $solicitud = SolicitudReferencia::where('inbox_mensaje_id', $correo->id)->firstOrFail();

        return $solicitud->evoluciones()->create(['nota' => $nota]);
    }

    public function test_solicitar_evolucion_envia_el_mensaje_libre_tambien_a_los_correos_adicionales(): void
    {
        Mail::fake();
        $staff = $this->staff();
        $solicitud = $this->solicitudConEvolucion($staff)->solicitud;
        $solicitud->update(['estado' => 'pendiente_cama']);

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/solicitar-evolucion", [
                'mensaje' => 'Por favor enviar la evolución de las últimas 24 horas.',
                'correos_extra' => ['jefe.enfermeria@example.com', 'norte@example.com'],
            ])
            ->assertOk();

        Mail::assertSent(SolicitudEvolucionClinica::class, fn (SolicitudEvolucionClinica $mail): bool => $mail->hasTo('norte@example.com')
            && $mail->hasTo('jefe.enfermeria@example.com')
            && $mail->mensaje === 'Por favor enviar la evolución de las últimas 24 horas.');

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/solicitar-evolucion", ['correos_extra' => ['no-es-un-correo']])
            ->assertStatus(422);
    }

    public function test_el_personal_responde_una_evolucion_y_se_envia_el_correo(): void
    {
        Mail::fake();
        $staff = $this->staff();
        $evolucion = $this->solicitudConEvolucion($staff);

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$evolucion->solicitud_referencia_id}/evoluciones/{$evolucion->id}/responder", [
                'mensaje' => 'Gracias. Por favor envíen los gases arteriales de hoy.',
            ])
            ->assertOk()
            ->assertJsonPath('correo_enviado', true)
            ->assertJsonPath('data.respuestas.0.mensaje', 'Gracias. Por favor envíen los gases arteriales de hoy.');

        $respuesta = $evolucion->respuestas()->firstOrFail();
        $this->assertTrue($respuesta->correo_enviado);
        $this->assertNotNull($respuesta->respondido_por);

        Mail::assertSent(RespuestaEvolucionClinica::class, fn (RespuestaEvolucionClinica $mail): bool => $mail->hasTo('norte@example.com')
            && $mail->respuesta->mensaje === 'Gracias. Por favor envíen los gases arteriales de hoy.');

        $this->assertDatabaseHas('solicitud_referencia_eventos', [
            'solicitud_referencia_id' => $evolucion->solicitud_referencia_id,
            'tipo' => 'evolucion_respuesta',
        ]);
    }

    public function test_la_clinica_ve_la_respuesta_junto_a_su_evolucion_en_el_modulo(): void
    {
        Mail::fake();
        $staff = $this->staff();
        $evolucion = $this->solicitudConEvolucion($staff);

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$evolucion->solicitud_referencia_id}/evoluciones/{$evolucion->id}/responder", ['mensaje' => 'Recibido, gracias.'])
            ->assertOk();

        $this->getJson('/api/externo/evoluciones?buscar=987654321')
            ->assertOk()
            ->assertJsonPath('data.0.evoluciones.0.respuestas.0.mensaje', 'Recibido, gracias.');
    }

    public function test_exige_mensaje_y_no_responde_evoluciones_de_otra_solicitud(): void
    {
        Mail::fake();
        $staff = $this->staff();
        $evolucion = $this->solicitudConEvolucion($staff);
        $otra = $this->solicitudConEvolucion($staff, 'Otra evolución');

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$evolucion->solicitud_referencia_id}/evoluciones/{$evolucion->id}/responder", ['mensaje' => ''])
            ->assertStatus(422);

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$evolucion->solicitud_referencia_id}/evoluciones/{$otra->id}/responder", ['mensaje' => 'Hola'])
            ->assertNotFound();

        Mail::assertNothingSent();
        $this->assertSame(0, $evolucion->respuestas()->count());
    }

    public function test_si_el_correo_falla_la_respuesta_igual_queda_guardada(): void
    {
        $staff = $this->staff();
        $evolucion = $this->solicitudConEvolucion($staff);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$evolucion->solicitud_referencia_id}/evoluciones/{$evolucion->id}/responder", ['mensaje' => 'Respuesta importante'])
            ->assertOk()
            ->assertJsonPath('correo_enviado', false);

        $this->assertFalse($evolucion->respuestas()->firstOrFail()->correo_enviado);
    }
}
