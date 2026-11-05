<?php

namespace Tests\Feature;

use App\Mail\NuevaSolicitudReferenciaInterna;
use App\Models\SolicitudReferencia;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class SolicitudReferenciaEmailTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_request_is_rolled_back_when_notification_email_fails(): void
    {
        Storage::fake('local');
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new RuntimeException('SMTP unavailable'));

        $response = $this->withHeader('Accept', 'application/json')
            ->post('/api/externo/solicitudes', $this->validPayload([
                'adjuntos' => [
                    UploadedFile::fake()->create('historia.pdf', 20, 'application/pdf'),
                ],
            ]));

        $response
            ->assertStatus(503)
            ->assertJsonPath('message', 'No se pudo enviar el correo de notificación. La solicitud no se registró; intente enviarla nuevamente.');

        $this->assertDatabaseCount('solicitudes_referencia', 0);
        $this->assertDatabaseCount('solicitud_referencia_eventos', 0);
        $this->assertDatabaseCount('solicitud_referencia_adjuntos', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('solicitudes-referencia'));
    }

    public function test_request_is_saved_when_notification_email_succeeds(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/externo/solicitudes', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('message', 'Solicitud enviada correctamente');

        $this->assertDatabaseCount('solicitudes_referencia', 1);
        $this->assertDatabaseCount('solicitud_referencia_eventos', 1);
        Mail::assertSent(NuevaSolicitudReferenciaInterna::class);
    }

    public function test_vectors_is_a_valid_isolation_type_and_unknown_ones_are_rejected(): void
    {
        Mail::fake();

        $this->postJson('/api/externo/solicitudes', $this->validPayload([
            'requiere_aislamiento' => true,
            'tipo_aislamiento' => 'vectores',
        ]))->assertCreated();

        $this->assertSame('vectores', SolicitudReferencia::query()->firstOrFail()->tipo_aislamiento);

        $this->postJson('/api/externo/solicitudes', $this->validPayload([
            'requiere_aislamiento' => true,
            'tipo_aislamiento' => 'inventado',
        ]))->assertStatus(422)->assertJsonStructure(['data' => ['errors' => ['tipo_aislamiento']]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'fecha' => '2026-10-29',
            'hora' => '09:30',
            'primer_nombre' => 'Ana',
            'primer_apellido' => 'Prueba',
            'genero' => 'F',
            'edad' => 35,
            'tipo_documento' => 'CC',
            'numero_documento' => '12345678',
            'eps' => 'SANITAS EPS',
            'diagnostico' => 'Diagnóstico de prueba',
            'diagnosticos' => [
                ['codigo_cie10' => 'J18.9', 'descripcion' => 'J18.9 — Diagnóstico de prueba'],
            ],
            'municipio_capita' => 'Cali, Valle del Cauca',
            'especialidad_requerida' => 'Medicina Interna',
            'tipo_referencia' => 'Referencia redonda',
            'servicio_ubicacion_actual' => 'URGENCIAS',
            'quien_remitente' => 'Profesional de prueba',
            'telefono_contacto' => '3001234567',
            'resumen_historia_clinica' => 'Resumen clínico de prueba.',
            'urgencia_vital' => false,
            'clinica_id' => null,
        ], $overrides);
    }
}
