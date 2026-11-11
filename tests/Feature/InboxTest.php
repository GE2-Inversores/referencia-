<?php

namespace Tests\Feature;

use App\Models\InboxMensaje;
use App\Models\SolicitudReferencia;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::query()->where('user_name', 'superadmin')->firstOrFail();
    }

    private function correo(array $extra = []): InboxMensaje
    {
        return InboxMensaje::create(array_merge([
            'gmail_id' => 'g'.uniqid(),
            'thread_id' => 't1',
            'remitente' => 'EPS Salud <eps@example.com>',
            'remitente_email' => 'eps@example.com',
            'para' => 'referencia@cacsantabarbara.co',
            'asunto' => 'Respuesta autorización',
            'snippet' => 'La autorización fue…',
            'cuerpo_texto' => 'Contenido del correo',
            'tiene_adjuntos' => false,
            'recibido_en' => now(),
            'leido' => false,
        ], $extra));
    }

    public function test_invitado_no_puede_ver_la_bandeja(): void
    {
        $this->getJson('/api/inbox')->assertUnauthorized();
    }

    public function test_lista_los_correos_mas_recientes_primero(): void
    {
        $this->correo(['asunto' => 'Viejo', 'recibido_en' => now()->subDay()]);
        $this->correo(['asunto' => 'Nuevo', 'recibido_en' => now()]);

        $this->actingAs($this->staff())
            ->getJson('/api/inbox')
            ->assertOk()
            ->assertJsonPath('data.0.asunto', 'Nuevo')
            ->assertJsonPath('meta.no_leidos', 2);
    }

    public function test_filtro_no_leidos(): void
    {
        $this->correo(['asunto' => 'Leído', 'leido' => true]);
        $this->correo(['asunto' => 'Sin leer']);

        $this->actingAs($this->staff())
            ->getJson('/api/inbox?filtro=no_leidos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.asunto', 'Sin leer');
    }

    public function test_abrir_un_correo_lo_marca_como_leido(): void
    {
        $correo = $this->correo();

        $this->actingAs($this->staff())
            ->getJson("/api/inbox/{$correo->id}")
            ->assertOk()
            ->assertJsonPath('data.asunto', 'Respuesta autorización');

        $this->assertTrue($correo->fresh()->leido);
    }

    public function test_contador_de_no_leidos(): void
    {
        $this->correo();
        $this->correo(['leido' => true]);
        $this->correo();

        $this->actingAs($this->staff())
            ->getJson('/api/inbox/no-leidos')
            ->assertOk()
            ->assertJsonPath('no_leidos', 2);
    }

    public function test_sincronizar_sin_configurar_devuelve_error_claro(): void
    {
        $this->actingAs($this->staff())
            ->postJson('/api/inbox/sincronizar')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Gmail no está configurado: falta el buzón o las credenciales en Configuración → Correo.');
    }

    private function datosSolicitud(): array
    {
        return [
            'tipo_documento' => 'CC',
            'numero_documento' => '123456',
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'edad' => 45,
            'genero' => 'F',
            'eps' => 'SANITAS',
            'telefono_paciente' => '3001234567',
            'municipio' => 'Bucaramanga',
            'direccion' => 'Calle 1',
            'urgencia_vital' => true,
            'historia_clinica' => 'Paciente remitido por correo.',
            'diagnostico' => 'Neumonía',
            'especialidad' => 'Medicina interna',
            'tipo_referencia' => 'Referencia redonda',
            'servicio' => 'UCI',
        ];
    }

    public function test_registra_solicitud_pendiente_desde_un_correo(): void
    {
        $correo = $this->correo(['recibido_en' => '2026-11-08 09:30:00']);

        $this->actingAs($this->staff())
            ->postJson("/api/inbox/{$correo->id}/solicitud", $this->datosSolicitud())
            ->assertOk();

        $solicitud = SolicitudReferencia::where('inbox_mensaje_id', $correo->id)->firstOrFail();
        $this->assertSame('pendiente', $solicitud->estado);
        $this->assertSame('Ana', $solicitud->primer_nombre);
        $this->assertSame('Pérez', $solicitud->primer_apellido);
        $this->assertSame('SANITAS', $solicitud->eps);
        $this->assertSame('eps@example.com', $solicitud->correo_contacto);
        $this->assertSame('correo', $solicitud->via_contacto);
        $this->assertSame('correo', $solicitud->origen);
        $this->assertSame('2026-11-08', $solicitud->fecha->toDateString());
        $this->assertSame('09:30', substr((string) $solicitud->hora, 0, 5));

        $this->assertSame('radicada', $correo->fresh()->gestion);
    }

    public function test_gestionar_guarda_el_correo_en_la_carpeta_elegida_o_la_de_defecto(): void
    {
        $staff = $this->staff();
        $porDefecto = $this->correo();
        $elegida = $this->correo();

        $this->actingAs($staff)->postJson("/api/inbox/{$porDefecto->id}/solicitud", $this->datosSolicitud())->assertOk();
        $this->actingAs($staff)->postJson("/api/inbox/{$elegida->id}/solicitud", $this->datosSolicitud() + ['carpeta' => 'Urgentes'])->assertOk();

        $this->assertSame('Aceptaciones', $porDefecto->fresh()->carpeta);
        $this->assertSame('Urgentes', $elegida->fresh()->carpeta);

        $this->actingAs($staff)->getJson('/api/inbox?carpeta=Urgentes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['nombre' => 'Urgentes', 'total' => 1]);
    }

    public function test_mueve_de_carpeta_un_correo_gestionado_pero_no_uno_pendiente(): void
    {
        $staff = $this->staff();
        $gestionado = $this->correo();
        $pendiente = $this->correo();

        $this->actingAs($staff)->postJson("/api/inbox/{$gestionado->id}/solicitud", $this->datosSolicitud())->assertOk();

        $this->actingAs($staff)->postJson("/api/inbox/{$gestionado->id}/carpeta", ['carpeta' => 'Revisadas'])
            ->assertOk()
            ->assertJsonPath('data.carpeta', 'Revisadas');

        $this->actingAs($staff)->postJson("/api/inbox/{$pendiente->id}/carpeta", ['carpeta' => 'Revisadas'])
            ->assertStatus(422);

        $this->assertNull($pendiente->fresh()->carpeta);
    }

    public function test_negar_usa_la_carpeta_negaciones_por_defecto_y_entrada_lista_los_pendientes(): void
    {
        $staff = $this->staff();
        $negado = $this->correo();
        $pendiente = $this->correo();

        $this->actingAs($staff)->postJson("/api/inbox/{$negado->id}/negar", [
            'grupo_negacion' => 'no_red',
            'motivo_negacion' => 'Paciente no pertenece a la red',
        ])->assertOk();

        $this->assertSame('Negaciones', $negado->fresh()->carpeta);

        $this->actingAs($staff)->getJson('/api/inbox?carpeta=entrada')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pendiente->id);
    }

    public function test_correo_con_adjuntos_registra_solicitud_aunque_no_haya_buzon(): void
    {
        $correo = $this->correo([
            'tiene_adjuntos' => true,
            'adjuntos' => [['nombre' => 'historia.pdf', 'tipo' => 'application/pdf', 'tamano' => 1234]],
        ]);

        // Sin Gmail configurado la descarga falla limpio — la solicitud
        // igual queda creada; los adjuntos se piden a la clínica después.
        $this->actingAs($this->staff())
            ->postJson("/api/inbox/{$correo->id}/solicitud", $this->datosSolicitud())
            ->assertOk();

        $this->assertDatabaseHas('solicitudes_referencia', ['inbox_mensaje_id' => $correo->id]);
    }

    public function test_negar_un_correo_lo_marca_como_negada(): void
    {
        $correo = $this->correo();

        $this->actingAs($this->staff())
            ->postJson("/api/inbox/{$correo->id}/negar", [
                'grupo_negacion' => 'poblacion_objeto',
                'motivo_negacion' => 'No cupo en UCI',
                'observaciones' => 'Sin camas disponibles hoy',
            ])
            ->assertOk();

        $correo->refresh();
        $this->assertSame('negada', $correo->gestion);
        $this->assertNotNull($correo->gestion_at);
        $this->assertStringContainsString('No cupo en UCI', (string) $correo->gestion_detalle);
    }

    public function test_negar_exige_grupo_y_motivo(): void
    {
        $correo = $this->correo();

        $this->actingAs($this->staff())
            ->postJson("/api/inbox/{$correo->id}/negar", [])
            ->assertStatus(422);
    }

    public function test_no_permite_negar_un_correo_ya_gestionado(): void
    {
        $correo = $this->correo(['gestion' => 'negada']);
        $staff = $this->staff();

        $this->actingAs($staff)
            ->postJson("/api/inbox/{$correo->id}/negar", [
                'grupo_negacion' => 'no_red',
                'motivo_negacion' => 'Fuera de la red',
            ])
            ->assertStatus(422);
    }

    public function test_crear_solicitud_valida_los_campos_obligatorios(): void
    {
        $correo = $this->correo();

        $this->actingAs($this->staff())
            ->postJson("/api/inbox/{$correo->id}/solicitud", [])
            ->assertStatus(422);
    }

    public function test_no_permite_dos_solicitudes_del_mismo_correo(): void
    {
        $correo = $this->correo();
        $staff = $this->staff();

        $this->actingAs($staff)
            ->postJson("/api/inbox/{$correo->id}/solicitud", $this->datosSolicitud())
            ->assertOk();

        $this->actingAs($staff)
            ->postJson("/api/inbox/{$correo->id}/solicitud", $this->datosSolicitud())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este correo ya tiene una solicitud registrada.');
    }
}
