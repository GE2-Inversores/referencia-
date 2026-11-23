<?php

namespace Tests\Feature;

use App\Models\InboxEstadistica;
use App\Models\InboxMensaje;
use App\Models\SolicitudReferencia;
use App\Models\SolicitudReferenciaEvento;
use App\Models\User;
use App\Support\XlsxWriter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class InformeReferenciasTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::query()->where('user_name', 'superadmin')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function solicitud(string $estado, string $fecha = '2026-11-03', array $atributos = []): SolicitudReferencia
    {
        return SolicitudReferencia::create(array_merge([
            'fecha' => $fecha,
            'hora' => '09:00',
            'primer_nombre' => 'Ana',
            'primer_apellido' => 'Prueba',
            'genero' => 'F',
            'edad' => 35,
            'tipo_documento' => 'CC',
            'numero_documento' => (string) random_int(10000000, 99999999),
            'eps' => 'SANITAS EPS',
            'municipio_capita' => 'Cali, Valle del Cauca',
            'especialidad_requerida' => 'Medicina Interna',
            'servicio_ubicacion_actual' => 'URGENCIAS',
            'resumen_historia_clinica' => 'Resumen.',
            'institucion_remitente' => 'Clínica A',
            'estado' => $estado,
        ], $atributos));
    }

    private function datosDePrueba(): void
    {
        $this->solicitud('completado', atributos: ['hora_respuesta' => '09:30']);
        $this->solicitud('en_espera', atributos: ['hora_respuesta' => '10:00', 'eps' => 'NUEVA EPS']);
        $this->solicitud('negado', atributos: ['grupo_negacion' => 'poblacion_objeto', 'motivo_negacion' => 'No cupo en UCI', 'hora_respuesta' => '09:10']);
        $this->solicitud('negado', atributos: ['grupo_negacion' => 'poblacion_objeto', 'motivo_negacion' => 'no cupo en uci ', 'hora_respuesta' => '09:20']);
        $this->solicitud('negado', atributos: ['grupo_negacion' => 'no_red', 'motivo_negacion' => 'Paciente de otra red']);
        $this->solicitud('negado', atributos: ['motivo_no_ingreso' => 'Alta Voluntaria', 'motivo_negacion' => 'No ingreso: Alta Voluntaria']);
        $this->solicitud('cancelado');
        $this->solicitud('pendiente_cama');
        $this->solicitud('pendiente');
        $this->solicitud('completado', '2026-10-01');
    }

    public function test_report_counts_accepted_denied_and_reasons_within_the_period(): void
    {
        $this->datosDePrueba();

        $respuesta = $this->actingAs($this->staff())
            ->getJson('/api/informes?desde=2026-11-01&hasta=2026-11-30')
            ->assertOk();

        $respuesta->assertJsonPath('data.resumen.total', 9)
            ->assertJsonPath('data.resumen.aceptadas', 2)
            ->assertJsonPath('data.resumen.negadas', 4)
            ->assertJsonPath('data.resumen.negadas_decision', 3)
            ->assertJsonPath('data.resumen.negadas_no_ingreso', 1)
            ->assertJsonPath('data.resumen.canceladas', 1)
            ->assertJsonPath('data.resumen.pendientes_cupo', 1)
            ->assertJsonPath('data.resumen.nuevas', 1)
            ->assertJsonPath('data.resumen.tasa_aceptacion', 33.3)
            ->assertJsonPath('data.resumen.tasa_negacion', 66.7);

        $respuesta->assertJsonPath('data.motivos_negacion.0.motivo', 'No cupo en UCI')
            ->assertJsonPath('data.motivos_negacion.0.total', 2)
            ->assertJsonPath('data.motivos_negacion.0.grupo', 'Población objeto')
            ->assertJsonPath('data.motivos_negacion.0.pct', 66.7)
            ->assertJsonPath('data.motivos_no_ingreso.0.motivo', 'Alta Voluntaria')
            ->assertJsonCount(4, 'data.casos_negados');

        $this->assertSame(2, collect($respuesta->json('data.grupos_negacion'))->firstWhere('grupo', 'poblacion_objeto')['total']);
    }

    public function test_mailbox_movement_has_its_own_endpoint_and_defaults_to_today(): void
    {
        InboxEstadistica::create(['fecha' => now()->toDateString(), 'recibidos' => 7, 'eliminados' => 2, 'contestados' => 1]);
        InboxEstadistica::create(['fecha' => now()->subDays(3)->toDateString(), 'recibidos' => 100]);
        $staff = $this->staff();

        $this->actingAs($staff)->getJson('/api/informes/correos')
            ->assertOk()
            ->assertJsonPath('data', ['total' => 7, 'gestionados' => 0, 'negados' => 0, 'pendientes' => 0, 'eliminados' => 2, 'archivados' => 4, 'contestados' => 1]);

        $this->actingAs($staff)->getJson('/api/informes/correos?desde='.now()->subDays(3)->toDateString().'&hasta='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.total', 107);
    }

    public function test_archived_in_gmail_is_received_minus_stored_minus_deleted(): void
    {
        InboxEstadistica::create(['fecha' => '2026-11-25', 'recibidos' => 10, 'eliminados' => 2]);
        InboxEstadistica::create(['fecha' => '2026-11-10', 'recibidos' => 500]);
        $base = ['remitente' => 'a', 'remitente_email' => 'a@b.co', 'asunto' => 'A', 'recibido_en' => '2026-11-25 10:00:00'];
        InboxMensaje::create(array_merge($base, ['gmail_id' => '1', 'thread_id' => '1']));
        InboxMensaje::create(array_merge($base, ['gmail_id' => '2', 'thread_id' => '2', 'gestion' => 'negada']));
        $staff = $this->staff();

        $this->actingAs($staff)->getJson('/api/informes/correos?desde=2026-11-25&hasta=2026-11-25')
            ->assertOk()
            ->assertJsonPath('data.total', 10)
            ->assertJsonPath('data.archivados', 6);

        // Antes del 20/11 los eliminados no se contaban: no se inventa una cifra.
        $this->actingAs($staff)->getJson('/api/informes/correos?desde=2026-11-01&hasta=2026-11-12')
            ->assertOk()
            ->assertJsonPath('data.total', 500)
            ->assertJsonPath('data.archivados', null);
    }

    public function test_every_mailbox_row_adds_up_to_the_total(): void
    {
        InboxEstadistica::create(['fecha' => '2026-11-25', 'recibidos' => 10, 'eliminados' => 2, 'contestados' => 1]);
        $base = ['remitente' => 'a', 'remitente_email' => 'a@b.co', 'asunto' => 'A', 'recibido_en' => '2026-11-25 10:00:00'];
        InboxMensaje::create(array_merge($base, ['gmail_id' => '1', 'thread_id' => '1']));
        InboxMensaje::create(array_merge($base, ['gmail_id' => '2', 'thread_id' => '2', 'contestado_at' => now()]));
        InboxMensaje::create(array_merge($base, ['gmail_id' => '3', 'thread_id' => '3', 'gestion' => 'radicada', 'contestado_at' => now()]));

        $datos = $this->actingAs($this->staff())->getJson('/api/informes/correos?desde=2026-11-25&hasta=2026-11-25')
            ->assertOk()
            ->assertJsonPath('data.pendientes', 1)
            ->assertJsonPath('data.contestados', 2)
            ->assertJsonPath('data.gestionados', 1)
            ->json('data');

        $this->assertSame($datos['total'], $datos['gestionados'] + $datos['negados'] + $datos['pendientes'] + $datos['contestados'] + $datos['eliminados'] + $datos['archivados']);
    }

    public function test_pending_requests_are_broken_down_by_destination_service(): void
    {
        $this->solicitud('pendiente_cama', '2026-11-03', ['servicio_remision' => 'UCI']);
        $this->solicitud('pendiente_cama', '2026-11-04', ['servicio_remision' => 'Urgencias adultos']);
        $this->solicitud('pendiente_cama', '2026-11-05', ['servicio_remision' => 'UCI']);
        $this->solicitud('pendiente', '2026-11-05', ['servicio_remision' => 'UCI']);

        $respuesta = $this->actingAs($this->staff())
            ->getJson('/api/informes?desde=2026-11-01&hasta=2026-11-30')
            ->assertOk()
            ->assertJsonPath('data.detalle_pendientes.total', 3);

        $porServicio = collect($respuesta->json('data.detalle_pendientes.por_servicio'))->pluck('total', 'nombre');
        $this->assertSame(2, $porServicio['UCI']);
        $this->assertSame(1, $porServicio['URGENCIAS']);
    }

    public function test_report_includes_the_mailbox_movement_for_the_period(): void
    {
        InboxEstadistica::create(['fecha' => '2026-11-18', 'recibidos' => 480, 'eliminados' => 150, 'contestados' => 40]);
        InboxEstadistica::create(['fecha' => '2026-11-19', 'recibidos' => 520, 'eliminados' => 200, 'contestados' => 60]);
        InboxEstadistica::create(['fecha' => '2026-10-01', 'recibidos' => 999]);
        $base = ['gmail_id' => 'x', 'remitente' => 'a', 'remitente_email' => 'a@b.co', 'asunto' => 'A', 'recibido_en' => '2026-11-18 10:00:00'];
        InboxMensaje::create(array_merge($base, ['gmail_id' => '1', 'thread_id' => '1', 'gestion' => 'radicada']));
        InboxMensaje::create(array_merge($base, ['gmail_id' => '2', 'thread_id' => '2', 'gestion' => 'reenviada']));
        InboxMensaje::create(array_merge($base, ['gmail_id' => '3', 'thread_id' => '3', 'gestion' => 'negada']));
        InboxMensaje::create(array_merge($base, ['gmail_id' => '4', 'thread_id' => '4', 'recibido_en' => '2026-10-01 10:00:00', 'gestion' => 'radicada']));

        $this->actingAs($this->staff())
            ->getJson('/api/informes?desde=2026-11-01&hasta=2026-11-30')
            ->assertOk()
            ->assertJsonPath('data.correos', ['total' => 1000, 'gestionados' => 2, 'negados' => 1, 'pendientes' => 0, 'eliminados' => 350, 'archivados' => 0, 'contestados' => 100]);
    }

    public function test_no_red_card_groups_insurers_without_the_contract_type(): void
    {
        foreach (['ASMET SALUD PP', 'ASMET SALUD EVENTO', 'NUEVA EPS PGP', 'NUEVA EPS EVENTO', 'NUEVA EPS PGP', 'SOS'] as $eps) {
            $this->solicitud('negado', atributos: ['eps' => $eps, 'grupo_negacion' => 'no_red', 'motivo_negacion' => 'Fuera de la red']);
        }

        $filas = collect(
            $this->actingAs($this->staff())
                ->getJson('/api/informes?desde=2026-11-01&hasta=2026-11-30')
                ->assertOk()
                ->json('data.no_red_por_eps')
        )->pluck('total', 'nombre');

        $this->assertSame(
            ['NUEVA EPS' => 3, 'ASMET SALUD' => 2, 'SOS' => 1],
            $filas->all(),
        );
    }

    public function test_report_breaks_down_by_eps_and_specialty(): void
    {
        $this->datosDePrueba();

        $respuesta = $this->actingAs($this->staff())
            ->getJson('/api/informes?desde=2026-11-01&hasta=2026-11-30')
            ->assertOk();

        $nuevaEps = collect($respuesta->json('data.por_eps'))->firstWhere('nombre', 'NUEVA EPS');
        $this->assertSame(1, $nuevaEps['total']);
        $this->assertSame(1, $nuevaEps['aceptadas']);
        $this->assertEquals(100, $nuevaEps['tasa_aceptacion']);

        $this->assertSame(9, $respuesta->json('data.por_especialidad.0.total'));
        $this->assertSame('Clínica A', $respuesta->json('data.por_clinica.0.nombre'));
    }

    public function test_accepted_detail_groups_by_insurer_service_and_specialty(): void
    {
        $this->solicitud('completado', atributos: ['eps' => 'NUEVA EPS', 'sede' => 'URGENCIAS', 'lugar_llegada' => 'urgencias']);
        $this->solicitud('en_espera', atributos: ['eps' => 'NUEVA EPS', 'sede' => 'Urgencias', 'lugar_llegada' => 'URGENCIAS ', 'especialidad_requerida' => 'Cardiología']);
        $this->solicitud('en_espera', atributos: ['eps' => 'SANITAS EPS', 'sede' => 'CAC SANTA BARBARA', 'lugar_llegada' => 'UCI']);
        $this->solicitud('completado', atributos: ['eps' => 'SANITAS EPS']);
        $this->solicitud('negado', atributos: ['eps' => 'NUEVA EPS', 'motivo_negacion' => 'x']);

        $respuesta = $this->actingAs($this->staff())
            ->getJson('/api/informes?desde=2026-11-01&hasta=2026-11-30')
            ->assertOk();

        $respuesta->assertJsonPath('data.detalle_aceptadas.total', 4)
            ->assertJsonPath('data.detalle_aceptadas.aceptado', 2)
            ->assertJsonPath('data.detalle_aceptadas.completadas', 2)
            ->assertJsonPath('data.detalle_aceptadas.por_eps.0.total', 2)
            ->assertJsonPath('data.detalle_aceptadas.por_eps.0.pct', 50);

        $porEps = collect($respuesta->json('data.detalle_aceptadas.por_eps'))->keyBy('nombre');
        $this->assertSame(1, $porEps['NUEVA EPS']['completadas']);
        $this->assertSame(1, $porEps['NUEVA EPS']['aceptado']);

        $sedes = collect($respuesta->json('data.detalle_aceptadas.por_sede'))->keyBy('nombre');
        $this->assertSame(2, $sedes['URGENCIAS']['total']);
        $this->assertSame(1, $sedes['CAC SANTA BARBARA']['total']);
        $this->assertSame(1, $sedes['SIN SEDE REGISTRADA']['total']);

        $servicios = collect($respuesta->json('data.detalle_aceptadas.por_servicio'))->keyBy('nombre');
        $this->assertSame(2, $servicios['URGENCIAS']['total']);
        $this->assertSame(1, $servicios['UCI']['total']);
        $this->assertSame(1, $servicios['Sin datos']['total']);
        $this->assertSame(0, $servicios['HOSPITALIZACION']['total']);
        $this->assertSame(0, $servicios['UCIN']['total']);
        $this->assertSame(0, $servicios['ANGIOGRAFIA/HEMODINAMIA']['total']);
        $this->assertSame(0, $servicios['Otros']['total']);
        $this->assertCount(7, $servicios);

        $this->assertSame(1, collect($respuesta->json('data.detalle_aceptadas.por_especialidad'))->firstWhere('nombre', 'Cardiología')['total']);
        $this->assertCount(4, $respuesta->json('data.casos_aceptados'));
        $this->assertSame('NUEVA EPS', $respuesta->json('data.detalle_negadas.por_eps.0.nombre'));
        $this->assertSame(1, $respuesta->json('data.detalle_negadas.por_eps.0.total'));
    }

    public function test_report_counts_effective_admissions_and_lists_no_shows_with_reason(): void
    {
        $this->solicitud('completado', atributos: ['numero_ingreso' => 'ING-100']);
        $this->solicitud('completado', atributos: ['numero_ingreso' => 'ING-200']);
        $this->solicitud('en_espera');
        $this->solicitud('negado', atributos: ['motivo_no_ingreso' => 'Alta Voluntaria', 'motivo_negacion' => 'No ingreso: Alta Voluntaria']);
        $this->solicitud('negado', atributos: ['grupo_negacion' => 'no_red', 'motivo_negacion' => 'Paciente de otra red']);

        $respuesta = $this->actingAs($this->staff())
            ->getJson('/api/informes?desde=2026-11-01&hasta=2026-11-30')
            ->assertOk();

        $respuesta->assertJsonPath('data.resumen.ingresos_efectivos', 2)
            ->assertJsonPath('data.resumen.pendientes_ingreso', 1)
            ->assertJsonPath('data.resumen.negadas_no_ingreso', 1)
            ->assertJsonPath('data.resumen.tasa_ingreso', 66.7);

        $respuesta->assertJsonCount(2, 'data.casos_ingresos')
            ->assertJsonCount(1, 'data.casos_no_ingreso')
            ->assertJsonPath('data.casos_no_ingreso.0.motivo_no_ingreso', 'Alta Voluntaria')
            ->assertJsonPath('data.casos_ingresos.0.numero_ingreso', 'ING-100');
    }

    public function test_daily_series_fills_days_without_requests_and_reports_cancellation_rate(): void
    {
        $this->solicitud('completado', '2026-11-01');
        $this->solicitud('cancelado', '2026-11-03');

        $respuesta = $this->actingAs($this->staff())
            ->getJson('/api/informes?desde=2026-11-01&hasta=2026-11-04')
            ->assertOk();

        $this->assertSame(['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04'], array_column($respuesta->json('data.por_dia'), 'fecha'));
        $this->assertSame([1, 0, 1, 0], array_column($respuesta->json('data.por_dia'), 'recibidas'));
        $this->assertEquals(50, $respuesta->json('data.resumen.tasa_cancelacion'));
    }

    public function test_report_includes_totals_of_the_previous_period_of_equal_length(): void
    {
        $this->solicitud('completado', '2026-10-29');
        $this->solicitud('negado', '2026-10-30', ['motivo_negacion' => 'x']);
        $this->solicitud('negado', '2026-10-31', ['motivo_negacion' => 'x']);
        $this->solicitud('completado', '2026-11-02');
        $this->solicitud('completado', '2026-10-28');

        $this->actingAs($this->staff())
            ->getJson('/api/informes?desde=2026-11-01&hasta=2026-11-03')
            ->assertOk()
            ->assertJsonPath('data.anterior.desde', '2026-10-29')
            ->assertJsonPath('data.anterior.hasta', '2026-10-31')
            ->assertJsonPath('data.anterior.total', 3)
            ->assertJsonPath('data.anterior.aceptadas', 1)
            ->assertJsonPath('data.anterior.negadas', 2)
            ->assertJsonPath('data.resumen.total', 1);
    }

    public function test_daily_series_counts_non_effective_admissions(): void
    {
        $this->solicitud('negado', '2026-11-02', ['motivo_no_ingreso' => 'Alta Voluntaria', 'motivo_negacion' => 'No ingreso: Alta Voluntaria']);
        $this->solicitud('negado', '2026-11-02', ['motivo_negacion' => 'Sin cupo', 'grupo_negacion' => 'no_red']);
        $this->solicitud('completado', '2026-11-02');

        $respuesta = $this->actingAs($this->staff())
            ->getJson('/api/informes?desde=2026-11-01&hasta=2026-11-03')
            ->assertOk();

        $dia = collect($respuesta->json('data.por_dia'))->firstWhere('fecha', '2026-11-02');
        $this->assertSame(1, $dia['no_efectivos']);
        $this->assertSame(2, $dia['negadas']);
    }

    public function test_response_time_uses_the_request_and_answer_hours(): void
    {
        $this->solicitud('completado', atributos: ['hora' => '09:00', 'hora_respuesta' => '09:30']);
        $this->solicitud('negado', atributos: ['hora' => '23:50', 'hora_respuesta' => '00:10', 'motivo_negacion' => 'x']);

        $this->actingAs($this->staff())
            ->getJson('/api/informes?desde=2026-11-01&hasta=2026-11-30')
            ->assertOk()
            ->assertJsonPath('data.tiempo_respuesta.respondidas', 2)
            ->assertJsonPath('data.tiempo_respuesta.promedio_min', 25);
    }

    public function test_report_requires_dashboard_permission_and_a_valid_range(): void
    {
        $this->getJson('/api/informes')->assertStatus(401);

        $staff = $this->staff();

        $this->actingAs($staff)
            ->getJson('/api/informes?desde=2026-11-30&hasta=2026-11-01')
            ->assertStatus(422);

        $sinPermiso = User::factory()->create();
        $this->actingAs($sinPermiso)->getJson('/api/informes')->assertStatus(403);
    }

    public function test_excel_export_is_a_valid_workbook_with_all_sheets(): void
    {
        $this->datosDePrueba();

        $respuesta = $this->actingAs($this->staff())
            ->get('/api/informes/exportar?desde=2026-11-01&hasta=2026-11-30')
            ->assertOk();

        $this->assertStringContainsString('spreadsheetml.sheet', $respuesta->headers->get('content-type'));

        $ruta = $respuesta->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($ruta));

        $libro = (string) $zip->getFromName('xl/workbook.xml');
        foreach (['Resumen', 'Motivos de negación', 'Aceptadas por EPS', 'Aceptadas por servicio', 'Negadas por EPS', 'Por EPS', 'Por clínica', 'Por especialidad', 'Ingresos efectivos', 'No ingresaron', 'Casos aceptados', 'Casos negados', 'Detalle'] as $hoja) {
            $this->assertStringContainsString('name="'.$hoja.'"', $libro);
        }

        $motivos = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
        $this->assertStringContainsString('No cupo en UCI', $motivos);
        $zip->close();
    }

    public function test_negados_base_export_uses_the_drive_layout_with_every_denial(): void
    {
        $this->datosDePrueba();

        $respuesta = $this->actingAs($this->staff())
            ->get('/api/informes/exportar-base?desde=2026-11-01&hasta=2026-11-02')
            ->assertOk();

        $this->assertStringContainsString('BASE REFERENCIA', $respuesta->headers->get('content-disposition'));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($respuesta->baseResponse->getFile()->getPathname()));

        $this->assertStringContainsString('name="NEGADOS"', (string) $zip->getFromName('xl/workbook.xml'));

        $hoja = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        foreach (['Fecha', 'Nombres y apellidos', 'Municipio que capita', 'Fecha de negado', 'Hora de negado', 'Motivación de negación', 'Nombre de quien rechaza la remisión'] as $columna) {
            $this->assertStringContainsString($columna, $hoja);
        }

        // Encabezado + las 4 negadas (3 por decisión y 1 que no ingresó), sin importar el período pedido.
        $this->assertSame(5, substr_count($hoja, '<row '));
        $zip->close();
    }

    public function test_negados_base_export_falls_back_to_the_current_service_when_there_is_no_destination(): void
    {
        $this->solicitud('negado', '2026-11-05', ['servicio_ubicacion_actual' => 'UCIN', 'servicio_remision' => null, 'motivo_negacion' => 'No cupo']);

        $respuesta = $this->actingAs($this->staff())->get('/api/informes/exportar-base')->assertOk();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($respuesta->baseResponse->getFile()->getPathname()));
        $hoja = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        // Una vez en «Servicio ubicacion actual» y otra en «Servicio al que se remite».
        $this->assertSame(2, substr_count($hoja, '>UCIN<'));
    }

    public function test_base_export_is_one_workbook_with_a_denied_tab_and_an_accepted_tab(): void
    {
        $aceptada = $this->solicitud('en_espera', '2026-11-05', ['lugar_llegada' => 'UCI', 'hora_respuesta' => '08:15:00', 'nombre_quien_responde' => 'Brenda Avila', 'primer_nombre' => 'Aceptada', 'codigo_aceptacion' => 'REF1']);
        $ingreso = $this->solicitud('completado', '2026-11-06', ['lugar_llegada' => 'Urgencias', 'primer_nombre' => 'Ingreso', 'numero_ingreso' => 361863]);
        $this->solicitud('negado', '2026-11-07', ['primer_nombre' => 'Negada', 'motivo_negacion' => 'No cupo']);
        $this->solicitud('negado', '2026-11-07', ['primer_nombre' => 'NoLlego', 'motivo_no_ingreso' => 'Alta Voluntaria']);
        $this->solicitud('pendiente_cama', '2026-11-08', ['primer_nombre' => 'Pendiente']);

        // La aceptación y la llegada salen de la línea de tiempo: cuando le
        // dieron «Confirmar aceptación» y cuando confirmaron el ingreso.
        foreach ([[$aceptada, 'en_espera', '2026-11-10 10:45:00'], [$ingreso, 'completado', '2026-11-11 14:30:00']] as [$solicitud, $tipo, $marca]) {
            $evento = SolicitudReferenciaEvento::create(['solicitud_referencia_id' => $solicitud->id, 'tipo' => $tipo, 'titulo' => 't', 'descripcion' => 'd']);
            $evento->forceFill(['created_at' => $marca])->save();
        }

        $respuesta = $this->actingAs($this->staff())->get('/api/informes/exportar-base')->assertOk();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($respuesta->baseResponse->getFile()->getPathname()));
        $libro = (string) $zip->getFromName('xl/workbook.xml');
        $negados = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $aceptados = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
        $zip->close();

        $this->assertStringContainsString('name="NEGADOS"', $libro);
        $this->assertStringContainsString('name="ACEPTADOS"', $libro);

        // NEGADOS lleva 17 columnas y ACEPTADOS las 25 del Drive (con el día y la hora reales de llegada).
        preg_match('/<row r="1".*?<\/row>/s', $negados, $encabezadoNegados);
        preg_match('/<row r="1".*?<\/row>/s', $aceptados, $encabezadoAceptados);
        $this->assertSame(17, substr_count($encabezadoNegados[0], '<c '));
        $this->assertSame(25, substr_count($encabezadoAceptados[0], '<c '));

        $this->assertStringContainsString('Hora de negado', $negados);
        $this->assertStringContainsString('NEGADA PRUEBA', $negados);
        $this->assertStringNotContainsString('ACEPTADA PRUEBA', $negados);
        // Las dos negadas salen aquí: la de decisión y la que no llegó.
        $this->assertSame(3, substr_count($negados, '<row '));

        foreach (['Diagnóstico', 'Institución de origen', 'Resumen de historia clínica', 'Hora de aceptado', 'Fecha de llegada', 'Hora de llegada', 'Estado', 'Motivo no ingreso', 'Otras observaciones', 'Código aceptación', 'N° ingreso', 'Nombre de quien acepta la remisión', 'ACEPTADA PRUEBA', 'INGRESO PRUEBA', 'NOLLEGO PRUEBA', 'BRENDA AVILA', 'REF1', 'Alta Voluntaria', '10:45 AM', '9:00 AM'] as $esperado) {
            $this->assertStringContainsString($esperado, $aceptados);
        }
        $this->assertStringNotContainsString('Fecha de aceptado', $aceptados);

        // La llegada real fue el 11/11 a las 2:30 PM (evento de la línea de tiempo).
        $this->assertStringContainsString('11/11/2026', $aceptados);
        $this->assertStringContainsString('2:30 PM', $aceptados);
        // La que ingresó marca SI; la que está en espera y la que no llegó, NO.
        $this->assertStringContainsString('>SI<', $aceptados);
        $this->assertSame(2, substr_count($aceptados, '>NO<'));
        $this->assertStringContainsString('<v>361863</v>', $aceptados);
        $this->assertStringNotContainsString('NEGADA PRUEBA', $aceptados);
        $this->assertStringNotContainsString('PENDIENTE PRUEBA', $aceptados);
        $this->assertSame(4, substr_count($aceptados, '<row '));
    }

    public function test_xlsx_writer_presentation_mode_is_well_formed_and_styled(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        XlsxWriter::guardar($ruta, ['NEGADOS' => [['DIA', 'Paciente'], [1, 'ANA'], [2, null], [3, 'LUIS']]], presentacion: true, columnasIzquierda: [1]);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($ruta));

        foreach (['xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/workbook.xml'] as $parte) {
            $this->assertNotFalse(simplexml_load_string((string) $zip->getFromName($parte)), "XML mal formado: {$parte}");
        }

        $estilos = (string) $zip->getFromName('xl/styles.xml');
        $this->assertStringContainsString('horizontal="center"', $estilos);
        $this->assertStringContainsString('<sz val="12"/>', $estilos);

        $hoja = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('<autoFilter ref="A1:B4"/>', $hoja);
        $this->assertStringContainsString('state="frozen"', $hoja);
        // Las celdas vacías también llevan estilo para conservar el borde.
        $this->assertStringContainsString('<c r="B3" s="5"/>', $hoja);
        $this->assertStringContainsString('ht="22" customHeight="1"', $hoja);
        // Solo el encabezado parte el texto en varias líneas; las filas de datos son de una línea.
        $this->assertSame(1, substr_count($estilos, 'wrapText="1"'));

        $zip->close();
        unlink($ruta);
    }

    public function test_xlsx_writer_produces_well_formed_xml_and_escapes_text(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        XlsxWriter::guardar($ruta, ['A/B:C' => [['Texto', 'Número'], ['<b> & "comillas" '."\x01", 12.5]]]);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($ruta));

        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml'] as $parte) {
            $this->assertNotFalse(simplexml_load_string((string) $zip->getFromName($parte)), "XML mal formado: {$parte}");
        }

        $hoja = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('&lt;b&gt; &amp; &quot;comillas&quot;', $hoja);
        $this->assertStringContainsString('<v>12.5</v>', $hoja);
        $this->assertStringNotContainsString("\x01", $hoja);
        $this->assertStringContainsString('name="A B C"', (string) $zip->getFromName('xl/workbook.xml'));

        $zip->close();
        unlink($ruta);
    }
}
