<?php

namespace Tests\Feature;

use App\Models\InboxEstadistica;
use App\Models\InboxMensaje;
use App\Models\Setting;
use App\Models\SolicitudReferencia;
use App\Models\User;
use App\Services\GmailService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Mockery\MockInterface;
use Symfony\Component\Mime\Email;
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
            // Hilo propio por defecto: compartir thread_id los agrupa en la lista.
            'thread_id' => 't'.uniqid(),
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

    public function test_agrupa_los_correos_del_mismo_hilo(): void
    {
        $this->correo(['asunto' => 'Primero del hilo', 'thread_id' => 'hilo-1', 'recibido_en' => now()->subHour(), 'leido' => true]);
        $this->correo(['asunto' => 'Último del hilo', 'thread_id' => 'hilo-1', 'recibido_en' => now(), 'leido' => false]);
        $this->correo(['asunto' => 'Otro correo', 'thread_id' => 'hilo-2', 'recibido_en' => now()->subHours(2)]);

        $this->actingAs($this->staff())
            ->getJson('/api/inbox')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.asunto', 'Último del hilo')
            ->assertJsonPath('data.0.hilo_total', 2)
            ->assertJsonPath('data.0.hilo_sin_leer', 1)
            ->assertJsonPath('data.1.hilo_total', 1);
    }

    public function test_hilo_sin_leer_aunque_el_ultimo_ya_este_leido(): void
    {
        $this->correo(['asunto' => 'Sin leer del hilo', 'thread_id' => 'hilo-1', 'recibido_en' => now()->subHour(), 'leido' => false]);
        $this->correo(['asunto' => 'Último del hilo', 'thread_id' => 'hilo-1', 'recibido_en' => now(), 'leido' => true]);

        $this->actingAs($this->staff())
            ->getJson('/api/inbox')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.hilo_sin_leer', 1);
    }

    public function test_hilo_devuelve_los_mensajes_de_la_conversacion_en_orden(): void
    {
        $primero = $this->correo(['asunto' => 'Primero', 'thread_id' => 'hilo-1', 'recibido_en' => now()->subHour()]);
        $this->correo(['asunto' => 'Segundo', 'thread_id' => 'hilo-1', 'recibido_en' => now()]);
        $this->correo(['asunto' => 'Ajeno', 'thread_id' => 'hilo-2']);

        $this->actingAs($this->staff())
            ->getJson("/api/inbox/{$primero->id}/hilo")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.asunto', 'Primero')
            ->assertJsonPath('data.1.asunto', 'Segundo');
    }

    public function test_identificador_correo_normaliza_el_message_id(): void
    {
        $conCorchetes = new InboxMensaje(['message_id' => '<abc@eps.co>']);
        $sinCorchetes = new InboxMensaje(['message_id' => 'abc@eps.co']);
        // Las filas viejas no tienen message_id: su thread_id era el Message-ID recortado.
        $vieja = new InboxMensaje(['thread_id' => '<abc@eps.co']);
        $sinId = new InboxMensaje(['thread_id' => 'hilo-gmail-123']);

        $this->assertSame('<abc@eps.co>', $conCorchetes->identificadorCorreo());
        $this->assertSame('<abc@eps.co>', $sinCorchetes->identificadorCorreo());
        $this->assertSame('<abc@eps.co', $vieja->identificadorCorreo());
        $this->assertNull($sinId->identificadorCorreo());
    }

    public function test_responder_en_hilo_agrega_in_reply_to_y_references(): void
    {
        $mensaje = new InboxMensaje([
            'message_id' => '<abc@eps.co>',
            'referencias' => '<padre@eps.co>',
        ]);

        $correo = new Message(new Email);
        $mensaje->responderEnHilo($correo);

        $headers = $correo->getSymfonyMessage()->getHeaders();
        $this->assertSame('<abc@eps.co>', $headers->get('In-Reply-To')?->getBodyAsString());
        $this->assertSame('<padre@eps.co> <abc@eps.co>', $headers->get('References')?->getBodyAsString());
    }

    public function test_responder_en_hilo_sin_identificador_no_toca_encabezados(): void
    {
        $mensaje = new InboxMensaje(['thread_id' => 'hilo-gmail-123']);

        $correo = new Message(new Email);
        $mensaje->responderEnHilo($correo);

        $headers = $correo->getSymfonyMessage()->getHeaders();
        $this->assertNull($headers->get('In-Reply-To'));
    }

    public function test_lista_incluye_las_etiquetas_del_correo(): void
    {
        $this->correo(['etiquetas' => ['\\Inbox', 'PACIENTES NO RED']]);

        $this->actingAs($this->staff())
            ->getJson('/api/inbox')
            ->assertOk()
            ->assertJsonPath('data.0.etiquetas', ['\\Inbox', 'PACIENTES NO RED']);
    }

    public function test_respuesta_imap_sin_corchetes_conserva_hilo_y_etiqueta(): void
    {
        $padre = $this->correo([
            'message_id' => 'original@clinica.test',
            'thread_id' => 'original@clinica.test',
            'etiquetas' => ['PRUEBA'],
            'carpeta' => 'Contestados',
        ]);
        $servicio = app(GmailService::class);
        $crear = new \ReflectionMethod($servicio, 'crearMensaje');
        $crear->invoke($servicio, [
            'gmail_id' => 'imap-respuesta-sin-corchetes',
            'message_id' => 'respuesta@clinica.test',
            'in_reply_to' => 'original@clinica.test',
            'referencias' => 'original@clinica.test',
            'remitente' => 'Clínica',
            'remitente_email' => 'clinica@example.com',
            'asunto' => 'Re: Prueba',
            'recibido_en' => now(),
            'etiquetas' => ['INBOX'],
        ]);

        $respuesta = InboxMensaje::where('gmail_id', 'imap-respuesta-sin-corchetes')->sole();
        $this->assertSame($padre->thread_id, $respuesta->thread_id);
        $this->assertContains('PRUEBA', $respuesta->etiquetas);
    }

    public function test_abrir_respuesta_imap_reune_hilo_separado_y_su_etiqueta(): void
    {
        $padre = $this->correo([
            'message_id' => 'padre@clinica.test',
            'thread_id' => 'padre@clinica.test',
            'etiquetas' => ['PRUEBA'],
            'carpeta' => 'Contestados',
        ]);
        $respuesta = $this->correo([
            'gmail_id' => 'imap-hilo-separado',
            'message_id' => 'hijo@clinica.test',
            'thread_id' => 'hijo@clinica.test',
            'referencias' => 'padre@clinica.test',
            'etiquetas' => ['INBOX'],
        ]);

        $this->actingAs($this->staff())
            ->getJson("/api/inbox/{$respuesta->id}")
            ->assertOk()
            ->assertJsonPath('data.thread_id', $padre->thread_id)
            ->assertJsonPath('data.etiquetas', ['INBOX', 'PRUEBA']);

        $this->getJson("/api/inbox/{$respuesta->id}/hilo")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/inbox')->assertOk()->assertJsonPath('data.0.hilo_total', 2);
    }

    public function test_reconciliar_sin_padre_disponible_no_separa_un_hilo_existente(): void
    {
        $correo = $this->correo([
            'gmail_id' => 'imap-sin-padre',
            'message_id' => 'hijo@clinica.test',
            'thread_id' => 'hilo-conocido',
            'referencias' => '<no-disponible@clinica.test>',
        ]);

        app(GmailService::class)->reconciliarHilo($correo);

        $this->assertSame('hilo-conocido', $correo->fresh()->thread_id);
    }

    public function test_respuesta_en_entrada_muestra_etiquetas_del_hilo_archivado(): void
    {
        $this->correo(['thread_id' => 'prueba-etiqueta', 'carpeta' => 'Contestados', 'etiquetas' => ['PRUEBA', 'SENT']]);
        $nuevo = $this->correo(['thread_id' => 'prueba-etiqueta', 'etiquetas' => ['INBOX']]);
        $this->correo(['thread_id' => 'otro-hilo', 'carpeta' => 'Contestados', 'etiquetas' => ['OTRA']]);

        $this->actingAs($this->staff())
            ->getJson('/api/inbox')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $nuevo->id)
            ->assertJsonPath('data.0.etiquetas', ['INBOX', 'PRUEBA']);
    }

    public function test_meta_sugiere_solo_las_etiquetas_personalizadas(): void
    {
        $this->correo(['etiquetas' => ['\\Inbox', 'PACIENTES NO RED']]);
        $this->correo(['etiquetas' => ['UNREAD', 'Población objeto', 'INBOX']]);

        $this->actingAs($this->staff())
            ->getJson('/api/inbox')
            ->assertOk()
            ->assertJsonPath('meta.etiquetas', ['PACIENTES NO RED', 'Población objeto']);
    }

    public function test_es_etiqueta_sistema_filtra_las_del_buzon(): void
    {
        $this->assertTrue(InboxMensaje::esEtiquetaSistema('INBOX'));
        $this->assertTrue(InboxMensaje::esEtiquetaSistema('\\Seen'));
        $this->assertTrue(InboxMensaje::esEtiquetaSistema('CATEGORY_PERSONAL'));
        $this->assertTrue(InboxMensaje::esEtiquetaSistema('UNREAD'));
        $this->assertFalse(InboxMensaje::esEtiquetaSistema('PACIENTES NO RED'));
        $this->assertFalse(InboxMensaje::esEtiquetaSistema('Población objeto'));
    }

    public function test_cambiar_etiquetas_sin_cambios_responde_sin_tocar_el_buzon(): void
    {
        $correo = $this->correo(['etiquetas' => ['\\Seen', 'PACIENTES NO RED']]);

        $this->actingAs($this->staff())
            ->putJson("/api/inbox/{$correo->id}/etiquetas", ['etiquetas' => ['PACIENTES NO RED']])
            ->assertOk()
            ->assertJsonPath('data.etiquetas', ['PACIENTES NO RED']);
    }

    public function test_etiquetas_del_hilo_se_editan_juntas_sin_afectar_otro_correo_del_mismo_remitente(): void
    {
        $viejo = $this->correo(['thread_id' => 'hilo-paciente-1', 'carpeta' => 'Contestados', 'etiquetas' => ['SENT', 'PRUEBA']]);
        $nuevo = $this->correo(['thread_id' => 'hilo-paciente-1', 'etiquetas' => ['INBOX']]);
        $otroPaciente = $this->correo(['thread_id' => 'hilo-paciente-2', 'etiquetas' => ['INBOX', 'PRUEBA']]);

        $this->partialMock(GmailService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('ponerEtiquetas')->times(4)
                ->andReturnUsing(function (InboxMensaje $correo, array $deseadas): array {
                    $sistema = array_filter($correo->etiquetas ?? [], InboxMensaje::esEtiquetaSistema(...));
                    $correo->update(['etiquetas' => array_values(array_merge($sistema, $deseadas))]);

                    return $deseadas;
                });
        });

        $this->actingAs($this->staff())
            ->getJson("/api/inbox/{$nuevo->id}")
            ->assertOk()
            ->assertJsonPath('data.etiquetas', ['INBOX', 'PRUEBA']);

        $this->putJson("/api/inbox/{$nuevo->id}/etiquetas", ['etiquetas' => ['ACEPTACIONES']])
            ->assertOk()
            ->assertJsonPath('data.etiquetas', ['ACEPTACIONES']);

        $this->assertSame(['SENT', 'ACEPTACIONES'], $viejo->fresh()->etiquetas);
        $this->assertSame(['INBOX', 'ACEPTACIONES'], $nuevo->fresh()->etiquetas);
        $this->assertSame(['INBOX', 'PRUEBA'], $otroPaciente->fresh()->etiquetas);

        $this->putJson("/api/inbox/{$nuevo->id}/etiquetas", ['etiquetas' => []])
            ->assertOk()
            ->assertJsonPath('data.etiquetas', []);

        $this->assertSame(['SENT'], $viejo->fresh()->etiquetas);
        $this->assertSame(['INBOX'], $nuevo->fresh()->etiquetas);
        $lista = $this->getJson('/api/inbox')->assertOk()->json('data');
        $this->assertSame(['INBOX'], collect($lista)->firstWhere('id', $nuevo->id)['etiquetas']);
        $this->assertSame(['INBOX', 'PRUEBA'], collect($lista)->firstWhere('id', $otroPaciente->id)['etiquetas']);
        $this->getJson("/api/inbox/{$nuevo->id}")->assertOk()->assertJsonPath('data.etiquetas', ['INBOX']);
    }

    public function test_cambiar_etiquetas_sin_buzon_devuelve_error_claro(): void
    {
        Setting::set('gmail_modo', 'servicio');
        $correo = $this->correo(['etiquetas' => null]);

        $this->actingAs($this->staff())
            ->putJson("/api/inbox/{$correo->id}/etiquetas", ['etiquetas' => ['PACIENTES NO RED']])
            ->assertStatus(503);
    }

    public function test_necesita_etiquetas_detecta_las_filas_que_solo_tienen_banderas(): void
    {
        $this->assertTrue((new InboxMensaje(['etiquetas' => null]))->necesitaEtiquetas());
        $this->assertTrue((new InboxMensaje(['etiquetas' => ['\\Seen', '\\Flagged']]))->necesitaEtiquetas());
        $this->assertTrue((new InboxMensaje(['etiquetas' => []]))->necesitaEtiquetas());
        // Etiquetas reales del buzón (del sistema o propias): ya está cargada.
        $this->assertFalse((new InboxMensaje(['etiquetas' => ['\\Inbox']]))->necesitaEtiquetas());
        $this->assertFalse((new InboxMensaje(['etiquetas' => ['\\Seen', 'PACIENTES NO RED']]))->necesitaEtiquetas());
    }

    public function test_abrir_un_correo_sin_buzon_no_falla_por_las_etiquetas(): void
    {
        $correo = $this->correo(['etiquetas' => null]);

        $this->actingAs($this->staff())
            ->getJson("/api/inbox/{$correo->id}")
            ->assertOk();
    }

    public function test_responder_contesta_al_remitente_sin_tocar_la_gestion(): void
    {
        $correo = $this->correo(['remitente_email' => 'clinica@example.com']);

        $this->actingAs($this->staff())
            ->postJson("/api/inbox/{$correo->id}/responder", [
                'cuerpo' => 'Recibimos su correo, estamos revisando la disponibilidad.',
                'cc' => ['jefe@example.com'],
            ])
            ->assertOk()
            ->assertJsonPath('data.contestado_por', 'Super Administrador');

        $correo->refresh();
        $this->assertNotNull($correo->contestado_at);
        $this->assertNull($correo->gestion);
        $this->assertSame('Contestados', $correo->carpeta);
        $this->assertStringContainsString('clinica@example.com', (string) $correo->contestado_detalle);
        $this->assertStringContainsString('jefe@example.com', (string) $correo->contestado_detalle);
    }

    public function test_responder_saca_todo_el_hilo_de_la_entrada(): void
    {
        $staff = $this->staff();
        $this->correo(['asunto' => 'Remisión', 'thread_id' => 'hilo-1']);
        $ultimo = $this->correo(['asunto' => 'Re: Remisión', 'thread_id' => 'hilo-1']);
        $gestionado = $this->correo(['asunto' => 'Radicado', 'thread_id' => 'hilo-1', 'gestion' => 'radicada', 'carpeta' => 'Gestionados']);

        $this->actingAs($staff)
            ->postJson("/api/inbox/{$ultimo->id}/responder", ['cuerpo' => 'Gracias, lo revisamos.'])
            ->assertOk();

        // Todo el hilo sin carpeta salió de la Entrada; el gestionado conserva la suya.
        $this->assertSame('Contestados', InboxMensaje::where('asunto', 'Remisión')->first()->carpeta);
        $this->assertSame('Gestionados', $gestionado->fresh()->carpeta);

        $this->actingAs($staff)
            ->getJson('/api/inbox')
            ->assertJsonCount(0, 'data');
        $this->actingAs($staff)
            ->getJson('/api/inbox?carpeta=Contestados')
            ->assertJsonCount(1, 'data');
    }

    public function test_hilo_conserva_cada_respuesta_enviada_y_su_contenido(): void
    {
        $correo = $this->correo();
        $otro = $this->correo();
        $this->actingAs($this->staff());

        foreach (['Primera respuesta', 'Segunda respuesta'] as $cuerpo) {
            $this->postJson("/api/inbox/{$correo->id}/responder", ['cuerpo' => $cuerpo])->assertOk();
        }

        $this->getJson("/api/inbox/{$correo->id}/hilo")
            ->assertOk()
            ->assertJsonCount(2, 'respuestas')
            ->assertJsonPath('respuestas.0.cuerpo_texto', 'Primera respuesta')
            ->assertJsonPath('respuestas.1.cuerpo_texto', 'Segunda respuesta')
            ->assertJsonPath('data.0.cuerpo_texto', 'Contenido del correo');

        $this->getJson("/api/inbox/{$otro->id}/hilo")->assertJsonCount(0, 'respuestas');
    }

    public function test_responder_exige_el_cuerpo_del_mensaje(): void
    {
        $correo = $this->correo();

        $this->actingAs($this->staff())
            ->postJson("/api/inbox/{$correo->id}/responder", ['cuerpo' => ''])
            ->assertStatus(422);
    }

    public function test_el_catalogo_de_etiquetas_incluye_las_guardadas_sin_buzon(): void
    {
        $this->correo(['etiquetas' => ['PACIENTES NO RED', 'Población objeto', '\Seen', '\Inbox']]);

        $this->actingAs($this->staff())
            ->getJson('/api/inbox/etiquetas-buzon')
            ->assertOk()
            ->assertJsonPath('data', ['PACIENTES NO RED', 'Población objeto']);
    }

    public function test_responder_falla_si_el_correo_no_tiene_remitente(): void
    {
        $correo = $this->correo(['remitente_email' => null]);

        $this->actingAs($this->staff())
            ->postJson("/api/inbox/{$correo->id}/responder", ['cuerpo' => 'Hola'])
            ->assertStatus(422);

        $this->assertNull($correo->fresh()->contestado_at);
        $this->assertNull($correo->fresh()->carpeta);
    }

    public function test_responder_con_eps_registra_solicitud_negada_para_el_informe(): void
    {
        $correo = $this->correo(['remitente_email' => 'clinica@example.com']);

        $this->actingAs($this->staff())
            ->postJson("/api/inbox/{$correo->id}/responder", [
                'cuerpo' => 'El paciente es de nuestra red, se remite internamente.',
                'eps' => 'EMSSANAR',
                'motivo' => 'Paciente de la red propia',
            ])
            ->assertOk();

        $correo->refresh();
        $solicitud = SolicitudReferencia::where('inbox_mensaje_id', $correo->id)->sole();

        $this->assertSame('EMSSANAR', $solicitud->eps);
        $this->assertSame('negado', $solicitud->estado);
        $this->assertSame('Paciente de la red propia', $solicitud->motivo_negacion);
        $this->assertSame('poblacion_objeto', $solicitud->grupo_negacion);
        $this->assertSame('negada', $correo->gestion);
        $this->assertSame('Contestados', $correo->carpeta);
    }

    public function test_responder_sin_eps_no_crea_solicitud(): void
    {
        $correo = $this->correo();

        $this->actingAs($this->staff())
            ->postJson("/api/inbox/{$correo->id}/responder", ['cuerpo' => 'Recibido, lo revisamos.'])
            ->assertOk();

        $this->assertSame(0, SolicitudReferencia::where('inbox_mensaje_id', $correo->id)->count());
        $this->assertNull($correo->fresh()->gestion);
    }

    public function test_responder_con_eps_no_duplica_la_solicitud_existente(): void
    {
        $correo = $this->correo();
        $solicitud = SolicitudReferencia::create([
            'inbox_mensaje_id' => $correo->id,
            'origen' => 'correo',
            'fecha' => now()->toDateString(),
            'hora' => now()->format('H:i'),
            'primer_nombre' => 'Ana',
            'primer_apellido' => 'Pérez',
            'genero' => 'F',
            'edad' => 30,
            'tipo_documento' => 'CC',
            'numero_documento' => '12345',
            'eps' => 'SURA',
            'municipio_capita' => 'Bucaramanga',
            'especialidad_requerida' => 'Neurología',
            'servicio_ubicacion_actual' => 'Urgencias',
            'resumen_historia_clinica' => 'Caso',
            'estado' => 'pendiente',
        ]);

        $this->actingAs($this->staff())
            ->postJson("/api/inbox/{$correo->id}/responder", [
                'cuerpo' => 'Recibido.',
                'eps' => 'EMSSANAR',
            ])
            ->assertOk();

        $this->assertSame(1, SolicitudReferencia::where('inbox_mensaje_id', $correo->id)->count());
        $this->assertSame('SURA', $solicitud->fresh()->eps);
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

        $this->assertSame('Gestionados', $porDefecto->fresh()->carpeta);
        $this->assertSame('Urgentes', $elegida->fresh()->carpeta);

        $this->actingAs($staff)->getJson('/api/inbox?carpeta=Urgentes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['nombre' => 'Urgentes', 'total' => 1]);
    }

    public function test_negar_exige_la_eps_con_su_tipo_de_contrato(): void
    {
        $staff = $this->staff();
        $correo = $this->correo();
        $this->actingAs($staff)->postJson("/api/inbox/{$correo->id}/solicitud", $this->datosSolicitud())->assertOk();
        $solicitud = SolicitudReferencia::where('inbox_mensaje_id', $correo->id)->firstOrFail();

        $datos = [
            'hora_respuesta' => '10:30',
            'grupo_negacion' => 'no_red',
            'motivo_negacion' => 'Paciente fuera de la red',
            'nombre_quien_responde' => 'Super Administrador',
        ];

        foreach (['NUEVA EPS', 'asmet salud', 'EMSSANAR', 'SURA'] as $sinContrato) {
            $this->actingAs($staff)
                ->postJson("/api/solicitudes-referencia/{$solicitud->id}/negar", $datos + ['eps' => $sinContrato])
                ->assertStatus(422)
                ->assertJsonPath('data.errors.eps.0', 'Seleccione la EPS con su tipo de contrato (por ejemplo NUEVA EPS PGP o NUEVA EPS EVENTO).');
        }

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/negar", $datos + ['eps' => 'NUEVA EPS PGP'])
            ->assertOk();

        $this->assertSame('NUEVA EPS PGP', $solicitud->fresh()->eps);
    }

    public function test_aceptar_exige_la_eps_con_su_tipo_de_contrato(): void
    {
        $staff = $this->staff();
        $correo = $this->correo();
        $this->actingAs($staff)->postJson("/api/inbox/{$correo->id}/solicitud", $this->datosSolicitud())->assertOk();
        $solicitud = SolicitudReferencia::where('inbox_mensaje_id', $correo->id)->firstOrFail();

        $datos = [
            'sin_disponibilidad' => true,
            'lugar_llegada' => 'UCI',
            'hora_respuesta' => '10:30',
            'nombre_quien_responde' => 'Super Administrador',
        ];

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/aceptar", $datos + ['eps' => 'NUEVA EPS'])
            ->assertStatus(422)
            ->assertJsonPath('data.errors.eps.0', 'Seleccione la EPS con su tipo de contrato (por ejemplo NUEVA EPS PGP o NUEVA EPS EVENTO).');

        $this->actingAs($staff)
            ->postJson("/api/solicitudes-referencia/{$solicitud->id}/aceptar", $datos + ['eps' => 'NUEVA EPS EVENTO'])
            ->assertOk();
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

    public function test_negar_usa_la_carpeta_negados_por_defecto_y_entrada_lista_los_pendientes(): void
    {
        $staff = $this->staff();
        $negado = $this->correo();
        $pendiente = $this->correo();

        $this->actingAs($staff)->postJson("/api/inbox/{$negado->id}/negar", [
            'eps' => 'NUEVA EPS PGP',
            'grupo_negacion' => 'no_red',
            'motivo_negacion' => 'Paciente no pertenece a la red',
        ])->assertOk();

        $this->assertSame('Negados', $negado->fresh()->carpeta);

        // Tanto la vista por defecto como la de Entrada solo muestran lo
        // pendiente — lo gestionado ya vive dentro de su carpeta.
        foreach (['/api/inbox', '/api/inbox?carpeta=entrada'] as $url) {
            $this->actingAs($staff)->getJson($url)
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $pendiente->id);
        }
    }

    public function test_los_contadores_diarios_cuentan_por_dia_de_llegada(): void
    {
        $staff = $this->staff();
        $llegada = now()->subDays(2)->setTime(10, 0);
        $contestado = $this->correo(['recibido_en' => $llegada]);
        $borrado = $this->correo(['recibido_en' => $llegada]);
        $contestadoYBorrado = $this->correo(['recibido_en' => $llegada]);

        // Contestar solo marca el correo: mientras siga en la base se cuenta en vivo, no en el contador.
        $this->actingAs($staff)->postJson("/api/inbox/{$contestado->id}/responder", ['cuerpo' => 'Uno'])->assertOk();
        $this->assertNull(InboxEstadistica::where('fecha', $llegada->toDateString())->first());

        // Borrar un correo sin contestar es «eliminado»; borrar uno contestado sigue siendo «con respuesta».
        $this->actingAs($staff)->postJson("/api/inbox/{$borrado->id}/papelera")->assertOk();
        $this->actingAs($staff)->postJson("/api/inbox/{$contestadoYBorrado->id}/responder", ['cuerpo' => 'Dos'])->assertOk();
        $this->actingAs($staff)->postJson("/api/inbox/{$contestadoYBorrado->id}/papelera")->assertOk();

        $fila = InboxEstadistica::where('fecha', $llegada->toDateString())->firstOrFail();
        $this->assertSame(1, $fila->contestados);
        $this->assertSame(1, $fila->eliminados);
        $this->assertNull(InboxEstadistica::where('fecha', now()->toDateString())->first());
    }

    public function test_la_limpieza_de_medianoche_deja_el_numero_de_descartados(): void
    {
        $llegada = now()->subDay()->setTime(9, 0);
        $descartado = $this->correo(['recibido_en' => $llegada]);
        $this->correo(['recibido_en' => $llegada]);
        $this->correo(['recibido_en' => $llegada, 'contestado_at' => now()]);
        $gestionado = $this->correo(['recibido_en' => $llegada, 'gestion' => 'radicada', 'carpeta' => 'Gestionados']);
        InboxMensaje::query()->update(['created_at' => now()->subDay()]);

        $this->artisan('inbox:prune')->assertSuccessful();

        $this->assertModelMissing($descartado);
        $this->assertModelExists($gestionado);
        $fila = InboxEstadistica::where('fecha', $llegada->toDateString())->firstOrFail();
        $this->assertSame(2, $fila->eliminados);
        $this->assertSame(1, $fila->contestados);
    }

    public function test_eliminar_un_correo_lo_borra_definitivamente_y_no_se_reimporta(): void
    {
        $staff = $this->staff();
        $pendiente = $this->correo();
        $otro = $this->correo();

        // Aun sin gestionar, cualquier correo se puede eliminar: desaparece
        // de la base de datos y deja de listarse.
        $this->actingAs($staff)->postJson("/api/inbox/{$pendiente->id}/papelera")
            ->assertOk()
            ->assertJsonPath('message', 'Correo eliminado definitivamente.');

        $this->assertModelMissing($pendiente);

        $this->actingAs($staff)->getJson('/api/inbox')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $otro->id);

        // El UID queda registrado: ni la sincronización ni la auditoría lo
        // vuelven a traer desde Gmail.
        $this->assertStringContainsString($pendiente->gmail_id, (string) Setting::get('inbox_eliminados'));
    }

    public function test_crear_solicitud_sin_municipio_queda_nulo(): void
    {
        $correo = $this->correo();
        $datos = $this->datosSolicitud();
        unset($datos['municipio']);

        $this->actingAs($this->staff())
            ->postJson("/api/inbox/{$correo->id}/solicitud", $datos)
            ->assertOk();

        $this->assertNull(SolicitudReferencia::where('inbox_mensaje_id', $correo->id)->value('municipio_capita'));
    }

    public function test_verificar_documento_avisa_solicitudes_activas_del_mismo_documento(): void
    {
        $staff = $this->staff();
        $correo = $this->correo();
        $this->actingAs($staff)->postJson("/api/inbox/{$correo->id}/solicitud", $this->datosSolicitud())->assertOk();
        $solicitud = SolicitudReferencia::where('inbox_mensaje_id', $correo->id)->firstOrFail();

        // Una solicitud activa con el mismo documento se reporta como posible duplicado.
        $this->actingAs($staff)
            ->getJson('/api/inbox/verificar-documento?numero_documento=123456&tipo_documento=CC')
            ->assertOk()
            ->assertJsonCount(1, 'duplicadas')
            ->assertJsonPath('duplicadas.0.id', $solicitud->id);

        // Ya resuelta no cuenta: re-remitir a un paciente es legítimo.
        $solicitud->update(['estado' => 'negado']);
        $this->actingAs($staff)
            ->getJson('/api/inbox/verificar-documento?numero_documento=123456')
            ->assertOk()
            ->assertJsonCount(0, 'duplicadas');
    }

    public function test_eliminar_un_correo_gestionado_no_borra_su_solicitud(): void
    {
        $staff = $this->staff();
        $correo = $this->correo();

        $this->actingAs($staff)->postJson("/api/inbox/{$correo->id}/solicitud", $this->datosSolicitud())->assertOk();
        $solicitud = SolicitudReferencia::where('inbox_mensaje_id', $correo->id)->firstOrFail();

        $this->actingAs($staff)->postJson("/api/inbox/{$correo->id}/papelera")->assertOk();

        $this->assertModelMissing($correo);
        $this->assertModelExists($solicitud);
    }

    public function test_negar_la_solicitud_desde_el_modulo_mueve_el_correo_a_negaciones(): void
    {
        $staff = $this->staff();
        $correo = $this->correo();
        $this->actingAs($staff)->postJson("/api/inbox/{$correo->id}/solicitud", $this->datosSolicitud())->assertOk();
        $solicitud = SolicitudReferencia::where('inbox_mensaje_id', $correo->id)->firstOrFail();

        $this->assertSame('Gestionados', $correo->fresh()->carpeta);

        $this->actingAs($staff)->postJson("/api/solicitudes-referencia/{$solicitud->id}/negar", [
            'hora_respuesta' => '10:30',
            'grupo_negacion' => 'no_red',
            'motivo_negacion' => 'Paciente fuera de la red',
            'eps' => 'NUEVA EPS PGP',
            'nombre_quien_responde' => 'Super Administrador',
        ])->assertOk();

        $correo->refresh();
        $this->assertSame('negada', $correo->gestion);
        $this->assertSame('Negados', $correo->carpeta);
        $this->assertStringContainsString('NUEVA EPS PGP', (string) $correo->gestion_detalle);
    }

    public function test_aceptar_la_solicitud_desde_el_modulo_devuelve_el_correo_a_aceptaciones(): void
    {
        $staff = $this->staff();
        $correo = $this->correo();
        $this->actingAs($staff)->postJson("/api/inbox/{$correo->id}/solicitud", $this->datosSolicitud())->assertOk();
        $solicitud = SolicitudReferencia::where('inbox_mensaje_id', $correo->id)->firstOrFail();

        // El correo radicado se puede mover a otra carpeta…
        $this->actingAs($staff)->postJson("/api/inbox/{$correo->id}/carpeta", ['carpeta' => 'Revisadas'])->assertOk();

        // …pero al aceptar la solicitud vuelve a Gestionados.
        $this->actingAs($staff)->postJson("/api/solicitudes-referencia/{$solicitud->id}/aceptar", [
            'sin_disponibilidad' => true,
            'lugar_llegada' => 'UCI',
            'hora_respuesta' => '10:30',
            'nombre_quien_responde' => 'Super Administrador',
            'eps' => 'NUEVA EPS EVENTO',
        ])->assertOk();

        $this->assertSame('Gestionados', $correo->fresh()->carpeta);
    }

    public function test_cambio_de_estado_a_negada_mueve_el_correo_a_negaciones(): void
    {
        $staff = $this->staff();
        $correo = $this->correo();
        $this->actingAs($staff)->postJson("/api/inbox/{$correo->id}/solicitud", $this->datosSolicitud())->assertOk();
        $solicitud = SolicitudReferencia::where('inbox_mensaje_id', $correo->id)->firstOrFail();

        $this->actingAs($staff)->postJson("/api/solicitudes-referencia/{$solicitud->id}/aceptar", [
            'sin_disponibilidad' => true,
            'lugar_llegada' => 'UCI',
            'hora_respuesta' => '10:30',
            'nombre_quien_responde' => 'Super Administrador',
            'eps' => 'NUEVA EPS EVENTO',
        ])->assertOk();

        $this->actingAs($staff)->postJson("/api/solicitudes-referencia/{$solicitud->id}/cambiar-estado", [
            'estado' => 'negado',
            'motivo' => 'Se reevaluó el caso y no procede',
            'nombre_quien_responde' => 'Super Administrador',
            'hora_respuesta' => '11:00',
            'eps' => 'NUEVA EPS EVENTO',
            'grupo_negacion' => 'no_red',
            'motivo_negacion' => 'Paciente fuera de la red',
        ])->assertOk();

        $correo->refresh();
        $this->assertSame('negada', $correo->gestion);
        $this->assertSame('Negados', $correo->carpeta);
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
                'eps' => 'ASMET SALUD EVENTO',
                'grupo_negacion' => 'poblacion_objeto',
                'motivo_negacion' => 'No cupo en UCI',
                'observaciones' => 'Sin camas disponibles hoy',
            ])
            ->assertOk();

        $correo->refresh();
        $this->assertSame('negada', $correo->gestion);
        $this->assertNotNull($correo->gestion_at);
        $this->assertStringContainsString('No cupo en UCI', (string) $correo->gestion_detalle);
        $this->assertStringContainsString('ASMET SALUD EVENTO', (string) $correo->gestion_detalle);
    }

    public function test_negar_en_bandeja_exige_la_eps_con_su_tipo_de_contrato(): void
    {
        $correo = $this->correo();
        $staff = $this->staff();
        $datos = ['grupo_negacion' => 'no_red', 'motivo_negacion' => 'Fuera de la red'];

        $this->actingAs($staff)->postJson("/api/inbox/{$correo->id}/negar", $datos)->assertStatus(422);
        $this->actingAs($staff)->postJson("/api/inbox/{$correo->id}/negar", $datos + ['eps' => 'NUEVA EPS'])->assertStatus(422);

        $this->assertNull($correo->fresh()->gestion);
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

    public function test_reenviar_un_correo_lo_marca_como_reenviada_y_lo_manda_a_su_carpeta(): void
    {
        $correo = $this->correo();

        $this->actingAs($this->staff())
            ->postJson("/api/inbox/{$correo->id}/reenviar", [
                'para' => 'referencia@otraclinica.co',
                'nota' => 'Llegó por error a nuestra central.',
                'avisar_remitente' => true,
            ])
            ->assertOk();

        $correo->refresh();
        $this->assertSame('reenviada', $correo->gestion);
        $this->assertSame('Gestionados', $correo->carpeta);
        $this->assertStringContainsString('referencia@otraclinica.co', (string) $correo->gestion_detalle);
        $this->assertNotNull($correo->gestion_at);
    }

    public function test_reenviar_exige_correo_de_destino_valido_y_no_toca_gestionados(): void
    {
        $correo = $this->correo();
        $gestionado = $this->correo(['gestion' => 'negada']);
        $staff = $this->staff();

        $this->actingAs($staff)->postJson("/api/inbox/{$correo->id}/reenviar", [])->assertStatus(422);
        $this->actingAs($staff)->postJson("/api/inbox/{$correo->id}/reenviar", ['para' => 'no-es-correo'])->assertStatus(422);
        $this->actingAs($staff)->postJson("/api/inbox/{$gestionado->id}/reenviar", ['para' => 'a@b.co'])->assertStatus(422);

        $this->assertNull($correo->fresh()->gestion);
    }

    public function test_descargar_adjunto_da_404_sin_buzon_o_sin_adjunto(): void
    {
        $correo = $this->correo(['tiene_adjuntos' => true, 'adjuntos' => [['nombre' => 'hc.pdf', 'tipo' => 'application/pdf', 'tamano' => 100, 'parte' => '2']]]);

        // Sin Gmail configurado el adjunto no se puede traer del buzón.
        $staff = $this->staff();

        $this->actingAs($staff)
            ->get("/api/inbox/{$correo->id}/adjunto/0")
            ->assertNotFound();

        $this->actingAs($staff)
            ->get("/api/inbox/{$correo->id}/adjunto/5")
            ->assertNotFound();
    }
}
