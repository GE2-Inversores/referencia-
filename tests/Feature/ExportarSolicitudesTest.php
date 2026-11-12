<?php

namespace Tests\Feature;

use App\Models\InboxMensaje;
use App\Models\SolicitudReferencia;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ExportarSolicitudesTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::query()->where('user_name', 'superadmin')->firstOrFail();
    }

    private function crearSolicitudDesdeCorreo(User $staff, string $servicio): SolicitudReferencia
    {
        $correo = InboxMensaje::create([
            'gmail_id' => 'g'.uniqid(),
            'remitente' => 'EPS Salud <eps@example.com>',
            'remitente_email' => 'eps@example.com',
            'asunto' => 'Remisión',
            'recibido_en' => now(),
            'leido' => false,
        ]);

        $this->actingAs($staff)->postJson("/api/inbox/{$correo->id}/solicitud", [
            'tipo_documento' => 'CC', 'numero_documento' => '123456', 'nombres' => 'Ana', 'apellidos' => 'Pérez',
            'edad' => 45, 'genero' => 'F', 'eps' => 'SANITAS', 'municipio' => 'Bucaramanga',
            'historia_clinica' => 'Remitido por correo.', 'diagnostico' => 'Neumonía',
            'especialidad' => 'Medicina interna', 'tipo_referencia' => 'Referencia redonda', 'servicio' => $servicio,
        ])->assertOk();

        return SolicitudReferencia::where('inbox_mensaje_id', $correo->id)->firstOrFail();
    }

    /**
     * @return array<int, string>
     */
    private function textosDelExcel(string $binario): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($ruta, $binario);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($ruta));
        $hoja = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($ruta);

        preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $hoja, $coincidencias);

        return array_map('html_entity_decode', $coincidencias[1]);
    }

    public function test_exporta_un_excel_real_con_el_servicio_junto_al_diagnostico(): void
    {
        $staff = $this->staff();
        $solicitud = $this->crearSolicitudDesdeCorreo($staff, 'UCI');

        $respuesta = $this->actingAs($staff)->postJson('/api/solicitudes-referencia/exportar', ['ids' => [$solicitud->id]]);

        $respuesta->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $respuesta->headers->get('Content-Type'));
        $this->assertStringEndsWith('.xlsx', (string) $respuesta->headers->get('Content-Disposition'));

        $textos = $this->textosDelExcel($respuesta->streamedContent());

        $this->assertContains('Servicio', $textos);
        $this->assertContains('UCI', $textos);
        $this->assertContains('Ana Pérez', $textos);
        $this->assertContains('Correo', $textos);

        $posicionDiagnostico = array_search('Diagnóstico', $textos, true);
        $this->assertSame('Servicio', $textos[$posicionDiagnostico + 1]);
    }

    public function test_solo_exporta_los_ids_pedidos_y_exige_ids(): void
    {
        $staff = $this->staff();
        $pedida = $this->crearSolicitudDesdeCorreo($staff, 'Urgencias');
        $this->crearSolicitudDesdeCorreo($staff, 'Hospitalización');

        $textos = $this->textosDelExcel(
            $this->actingAs($staff)->postJson('/api/solicitudes-referencia/exportar', ['ids' => [$pedida->id]])->streamedContent()
        );

        $this->assertContains('Urgencias', $textos);
        $this->assertNotContains('Hospitalización', $textos);

        $this->actingAs($staff)->postJson('/api/solicitudes-referencia/exportar', ['ids' => []])->assertStatus(422);
    }
}
