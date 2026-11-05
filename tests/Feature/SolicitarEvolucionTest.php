<?php

namespace Tests\Feature;

use App\Mail\SolicitudEvolucionClinica;
use App\Models\Clinica;
use App\Models\SolicitudReferencia;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class SolicitarEvolucionTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::query()->where('user_name', 'superadmin')->firstOrFail();
    }

    private function solicitud(string $estado = 'pendiente_cama', ?string $correo = 'clinica@example.com'): SolicitudReferencia
    {
        $clinica = $correo ? Clinica::create([
            'nombre' => 'Clinica de prueba',
            'email' => $correo,
            'is_active' => true,
            'estado' => 'activa',
        ]) : null;

        return SolicitudReferencia::create([
            'clinica_id' => $clinica?->id,
            'fecha' => '2026-10-29',
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

    public function test_staff_can_request_evolution_by_email_with_direct_link(): void
    {
        Mail::fake();
        $solicitud = $this->solicitud();

        $this->actingAs($this->staff())
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/solicitar-evolucion")
            ->assertOk();

        Mail::assertSent(SolicitudEvolucionClinica::class, function (SolicitudEvolucionClinica $mail) use ($solicitud): bool {
            return $mail->hasTo('clinica@example.com')
                && str_ends_with($mail->enlace, "/clinica/evolucion?id={$solicitud->id}");
        });
        $this->assertDatabaseHas('solicitud_referencia_eventos', [
            'solicitud_referencia_id' => $solicitud->id,
            'tipo' => 'evolucion_solicitada',
        ]);
    }

    public function test_optional_message_is_included_in_the_email_and_the_event(): void
    {
        Mail::fake();
        $solicitud = $this->solicitud();

        $this->actingAs($this->staff())
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/solicitar-evolucion", [
                'mensaje' => 'Favor adjuntar la evolución de las últimas 24 horas.',
            ])
            ->assertOk();

        Mail::assertSent(SolicitudEvolucionClinica::class, function (SolicitudEvolucionClinica $mail): bool {
            return $mail->mensaje === 'Favor adjuntar la evolución de las últimas 24 horas.'
                && str_contains($mail->render(), 'Favor adjuntar la evolución de las últimas 24 horas.');
        });
        $this->assertDatabaseHas('solicitud_referencia_eventos', [
            'solicitud_referencia_id' => $solicitud->id,
            'tipo' => 'evolucion_solicitada',
        ]);
    }

    public function test_email_without_message_has_no_message_block_and_overlong_messages_are_rejected(): void
    {
        Mail::fake();
        $staff = $this->staff();
        $solicitud = $this->solicitud();

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/solicitar-evolucion", ['mensaje' => str_repeat('a', 1001)])
            ->assertStatus(422);

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/solicitar-evolucion", ['mensaje' => null])
            ->assertOk();

        Mail::assertSent(SolicitudEvolucionClinica::class, function (SolicitudEvolucionClinica $mail): bool {
            return $mail->mensaje === null && ! str_contains($mail->render(), 'Mensaje del equipo de referencia');
        });
    }

    public function test_can_only_request_evolution_for_pending_cupo_requests(): void
    {
        Mail::fake();
        $staff = $this->staff();

        foreach (['pendiente', 'en_espera', 'completado', 'negado'] as $estado) {
            $solicitud = $this->solicitud($estado);

            $this->actingAs($staff)
                ->postJson("/api/solicitudes-referencia/{$solicitud->id}/solicitar-evolucion")
                ->assertStatus(422);
        }

        Mail::assertNothingSent();
    }

    public function test_cannot_request_evolution_without_clinic_email(): void
    {
        Mail::fake();
        $solicitud = $this->solicitud('pendiente_cama', null);

        $this->actingAs($this->staff())
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/solicitar-evolucion")
            ->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_mail_failure_returns_error_and_records_no_event(): void
    {
        $solicitud = $this->solicitud();
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP unavailable'));

        $this->actingAs($this->staff())
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/solicitar-evolucion")
            ->assertStatus(503);

        $this->assertDatabaseMissing('solicitud_referencia_eventos', ['tipo' => 'evolucion_solicitada']);
    }
}
