<?php

namespace Tests\Feature;

use App\Mail\SolicitudEvolucionClinica;
use App\Models\Clinica;
use App\Models\Setting;
use App\Models\SolicitudReferencia;
use App\Models\User;
use App\Services\MailConfigService;
use Closure;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class CorreoExtraEstadoTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::query()->where('user_name', 'superadmin')->firstOrFail();
    }

    private function solicitud(string $estado = 'pendiente', ?string $correoClinica = 'clinica@example.com'): SolicitudReferencia
    {
        $clinica = $correoClinica ? Clinica::create([
            'nombre' => 'Clínica de prueba',
            'email' => $correoClinica,
            'is_active' => true,
            'estado' => 'activa',
        ]) : null;

        return SolicitudReferencia::create([
            'clinica_id' => $clinica?->id,
            'fecha' => '2026-11-06',
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

    /**
     * Reemplaza Mail::send con una captura real de los destinatarios que la
     * clausura del controlador pone en el mensaje.
     *
     * @param  array<int, array{view: string, to: array<int, string>, reply_to: array<int, string>}>  $enviados
     */
    private function capturarEnvios(array &$enviados): void
    {
        Mail::shouldReceive('send')->andReturnUsing(
            function (string $view, array $data, Closure $callback) use (&$enviados): void {
                $email = new Email;
                $callback(new Message($email));

                $enviados[] = [
                    'view' => $view,
                    'to' => array_map(static fn ($address): string => $address->getAddress(), $email->getTo()),
                    'reply_to' => array_map(static fn ($address): string => $address->getAddress(), $email->getReplyTo()),
                ];
            }
        );
    }

    public function test_denial_email_goes_to_clinic_and_extra_addresses(): void
    {
        $enviados = [];
        $this->capturarEnvios($enviados);
        $solicitud = $this->solicitud();

        $this->actingAs($this->staff())
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/negar", [
                'hora_respuesta' => '10:00',
                'grupo_negacion' => 'no_red',
                'motivo_negacion' => 'Sin cupo',
                'eps' => 'SANITAS EPS',
                'nombre_quien_responde' => 'Super Administrador',
                'correos_extra' => ['extra@example.com', 'otro@example.com'],
            ])
            ->assertOk();

        $this->assertCount(1, $enviados);
        $this->assertSame('emails.solicitud-negada', $enviados[0]['view']);
        $this->assertSame(['clinica@example.com', 'extra@example.com', 'otro@example.com'], $enviados[0]['to']);
    }

    public function test_single_extra_address_keeps_working_for_compat(): void
    {
        $enviados = [];
        $this->capturarEnvios($enviados);
        $solicitud = $this->solicitud();

        $this->actingAs($this->staff())
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/negar", [
                'hora_respuesta' => '10:00',
                'grupo_negacion' => 'no_red',
                'motivo_negacion' => 'Sin cupo',
                'eps' => 'SANITAS EPS',
                'nombre_quien_responde' => 'Super Administrador',
                'correo_extra' => 'extra@example.com',
            ])
            ->assertOk();

        $this->assertSame(['clinica@example.com', 'extra@example.com'], $enviados[0]['to']);
    }

    public function test_state_email_goes_to_clinic_only_without_extra_address(): void
    {
        $enviados = [];
        $this->capturarEnvios($enviados);
        $solicitud = $this->solicitud();

        $this->actingAs($this->staff())
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/negar", [
                'hora_respuesta' => '10:00',
                'grupo_negacion' => 'no_red',
                'motivo_negacion' => 'Sin cupo',
                'eps' => 'SANITAS EPS',
                'nombre_quien_responde' => 'Super Administrador',
            ])
            ->assertOk();

        $this->assertSame(['clinica@example.com'], $enviados[0]['to']);
    }

    public function test_duplicate_extra_address_is_sent_only_once(): void
    {
        $enviados = [];
        $this->capturarEnvios($enviados);
        $solicitud = $this->solicitud();

        $this->actingAs($this->staff())
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/negar", [
                'hora_respuesta' => '10:00',
                'grupo_negacion' => 'no_red',
                'motivo_negacion' => 'Sin cupo',
                'eps' => 'SANITAS EPS',
                'nombre_quien_responde' => 'Super Administrador',
                'correo_extra' => 'clinica@example.com',
            ])
            ->assertOk();

        $this->assertSame(['clinica@example.com'], $enviados[0]['to']);
    }

    public function test_extra_email_falls_back_to_contact_email_without_clinic(): void
    {
        $enviados = [];
        $this->capturarEnvios($enviados);
        $solicitud = $this->solicitud('pendiente', null);
        $solicitud->update(['correo_contacto' => 'contacto@example.com']);

        $this->actingAs($this->staff())
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/negar", [
                'hora_respuesta' => '10:00',
                'grupo_negacion' => 'no_red',
                'motivo_negacion' => 'Sin cupo',
                'eps' => 'SANITAS EPS',
                'nombre_quien_responde' => 'Super Administrador',
                'correo_extra' => 'extra@example.com',
            ])
            ->assertOk();

        $this->assertSame(['contacto@example.com', 'extra@example.com'], $enviados[0]['to']);
    }

    public function test_invalid_extra_email_is_rejected(): void
    {
        Mail::fake();
        $solicitud = $this->solicitud();

        $respuesta = $this->actingAs($this->staff())
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/negar", [
                'hora_respuesta' => '10:00',
                'grupo_negacion' => 'no_red',
                'motivo_negacion' => 'Sin cupo',
                'eps' => 'SANITAS EPS',
                'nombre_quien_responde' => 'Super Administrador',
                'correos_extra' => ['no-es-un-correo'],
            ])
            ->assertStatus(422);

        $this->assertArrayHasKey('correos_extra.0', $respuesta->json('data.errors'));
        $this->assertSame('pendiente', $solicitud->fresh()->estado);
    }

    public function test_state_email_carries_the_configured_reply_to(): void
    {
        $enviados = [];
        $this->capturarEnvios($enviados);
        $solicitud = $this->solicitud();

        $this->actingAs($this->staff())
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/negar", [
                'hora_respuesta' => '10:00',
                'grupo_negacion' => 'no_red',
                'motivo_negacion' => 'Sin cupo',
                'eps' => 'SANITAS EPS',
                'nombre_quien_responde' => 'Super Administrador',
            ])
            ->assertOk();

        $this->assertSame(['referencia@cacsantabarbara.co'], $enviados[0]['reply_to']);
    }

    public function test_empty_reply_to_setting_sends_without_reply_to_header(): void
    {
        $enviados = [];
        $this->capturarEnvios($enviados);
        $solicitud = $this->solicitud();
        $staff = $this->staff();

        Setting::set('mail_reply_to_address', null);
        app(MailConfigService::class)->configure();

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/negar", [
                'hora_respuesta' => '10:00',
                'grupo_negacion' => 'no_red',
                'motivo_negacion' => 'Sin cupo',
                'eps' => 'SANITAS EPS',
                'nombre_quien_responde' => 'Super Administrador',
            ])
            ->assertOk();

        $this->assertSame([], $enviados[0]['reply_to']);
    }

    public function test_evolution_email_includes_extra_recipient(): void
    {
        Mail::fake();
        $solicitud = $this->solicitud('pendiente_cama');

        $this->actingAs($this->staff())
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/solicitar-evolucion", [
                'correo_extra' => 'extra@example.com',
            ])
            ->assertOk();

        Mail::assertSent(SolicitudEvolucionClinica::class, function (SolicitudEvolucionClinica $mail): bool {
            return $mail->hasTo('clinica@example.com') && $mail->hasTo('extra@example.com');
        });
    }
}
