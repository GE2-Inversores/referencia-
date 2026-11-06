<?php

namespace Tests\Feature;

use App\Models\SolicitudReferencia;
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
