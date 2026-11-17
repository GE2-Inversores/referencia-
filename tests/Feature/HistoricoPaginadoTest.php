<?php

namespace Tests\Feature;

use App\Models\Clinica;
use App\Models\SolicitudReferencia;
use App\Models\SolicitudReferenciaEvento;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoricoPaginadoTest extends TestCase
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
    private function solicitud(string $estado, array $atributos = []): SolicitudReferencia
    {
        return SolicitudReferencia::create(array_merge([
            'fecha' => '2026-11-05',
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
        ], $atributos));
    }

    public function test_pagina_el_historico_y_solo_trae_estados_resueltos(): void
    {
        foreach (range(1, 12) as $i) {
            $this->solicitud('completado', ['numero_documento' => (string) (1000 + $i)]);
        }
        $this->solicitud('pendiente');
        $this->solicitud('en_espera');

        $this->actingAs($this->staff())
            ->getJson('/api/solicitudes-referencia/historico?por_pagina=10&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.pagina', 2)
            ->assertJsonPath('meta.paginas', 2)
            ->assertJsonPath('meta.resumen.total', 12);
    }

    public function test_la_pagina_no_trae_el_texto_largo_de_la_historia_clinica(): void
    {
        $this->solicitud('negado');

        $fila = $this->actingAs($this->staff())
            ->getJson('/api/solicitudes-referencia/historico')
            ->assertOk()
            ->json('data.0');

        $this->assertArrayNotHasKey('resumen_historia_clinica', $fila);
        $this->assertArrayHasKey('primer_nombre', $fila);
    }

    public function test_los_contadores_ignoran_los_filtros(): void
    {
        $this->solicitud('completado');
        $this->solicitud('negado');
        $this->solicitud('negado');
        $this->solicitud('cancelado');

        $this->actingAs($this->staff())
            ->getJson('/api/solicitudes-referencia/historico?estado=negado')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.resumen.total', 4)
            ->assertJsonPath('meta.resumen.completadas', 1)
            ->assertJsonPath('meta.resumen.negadas', 2)
            ->assertJsonPath('meta.resumen.canceladas', 1);
    }

    public function test_filtra_por_clinica_especialidad_eps_origen_y_fechas(): void
    {
        $clinica = Clinica::create(['nit' => '900000001', 'nombre' => 'Clínica Norte', 'email' => 'norte@example.com']);
        $this->solicitud('completado', ['clinica_id' => $clinica->id]);
        $this->solicitud('completado', ['especialidad_requerida' => 'Cardiología']);
        $this->solicitud('completado', ['eps' => 'NUEVA EPS']);
        $this->solicitud('completado', ['origen' => 'correo']);
        $viejo = $this->solicitud('completado');
        $viejo->forceFill(['created_at' => '2026-01-10 10:00:00'])->save();
        $staff = $this->staff();

        $consulta = fn (string $q): int => $this->actingAs($staff)->getJson("/api/solicitudes-referencia/historico?{$q}")->assertOk()->json('meta.total');

        $this->assertSame(1, $consulta("clinica_id={$clinica->id}"));
        $this->assertSame(1, $consulta('especialidad=Cardiolog%C3%ADa'));
        $this->assertSame(1, $consulta('eps=NUEVA+EPS'));
        $this->assertSame(1, $consulta('origen=correo'));
        $this->assertSame(1, $consulta('desde=2026-01-01&hasta=2026-01-31'));
    }

    public function test_busca_por_nombre_documento_con_puntos_y_clinica(): void
    {
        $clinica = Clinica::create(['nit' => '900000002', 'nombre' => 'Hospital Raul Orejuela', 'email' => 'raul@example.com']);
        $this->solicitud('completado', ['primer_nombre' => 'Kelly', 'segundo_nombre' => 'Johanna', 'primer_apellido' => 'Correa', 'numero_documento' => '1148444423']);
        $this->solicitud('negado', ['primer_nombre' => 'Pedro', 'clinica_id' => $clinica->id]);
        $staff = $this->staff();

        $consulta = fn (string $q): int => $this->actingAs($staff)->getJson('/api/solicitudes-referencia/historico?buscar='.urlencode($q))->assertOk()->json('meta.total');

        $this->assertSame(1, $consulta('kelly johanna'));
        $this->assertSame(1, $consulta('1.148.444.423'));
        $this->assertSame(1, $consulta('orejuela'));
        $this->assertSame(0, $consulta('inexistente'));
    }

    public function test_ordena_por_paciente(): void
    {
        $this->solicitud('completado', ['primer_nombre' => 'Zoe']);
        $this->solicitud('completado', ['primer_nombre' => 'Alba']);

        $this->actingAs($this->staff())
            ->getJson('/api/solicitudes-referencia/historico?orden=paciente&dir=asc')
            ->assertOk()
            ->assertJsonPath('data.0.primer_nombre', 'Alba')
            ->assertJsonPath('data.1.primer_nombre', 'Zoe');
    }

    public function test_solo_trae_los_eventos_que_marcan_la_resolucion(): void
    {
        $solicitud = $this->solicitud('completado');
        SolicitudReferenciaEvento::create(['solicitud_referencia_id' => $solicitud->id, 'tipo' => 'nota', 'titulo' => 'Nota', 'descripcion' => 'x']);
        SolicitudReferenciaEvento::create(['solicitud_referencia_id' => $solicitud->id, 'tipo' => 'completado', 'titulo' => 'Ok', 'descripcion' => 'x']);

        $this->actingAs($this->staff())
            ->getJson('/api/solicitudes-referencia/historico')
            ->assertOk()
            ->assertJsonCount(1, 'data.0.eventos')
            ->assertJsonPath('data.0.eventos.0.tipo', 'completado');
    }

    public function test_entrega_las_opciones_de_filtro_solo_cuando_se_piden(): void
    {
        $clinica = Clinica::create(['nit' => '900000001', 'nombre' => 'Clínica Norte', 'email' => 'norte@example.com']);
        $this->solicitud('completado', ['clinica_id' => $clinica->id, 'especialidad_requerida' => 'Cardiología']);
        $this->solicitud('pendiente', ['especialidad_requerida' => 'Neurología']);
        $staff = $this->staff();

        $this->actingAs($staff)->getJson('/api/solicitudes-referencia/historico')
            ->assertOk()->assertJsonMissingPath('meta.filtros');

        $this->actingAs($staff)->getJson('/api/solicitudes-referencia/historico?filtros=1')
            ->assertOk()
            ->assertJsonPath('meta.filtros.clinicas.0.id', $clinica->id)
            ->assertJsonPath('meta.filtros.especialidades', ['Cardiología']);
    }

    public function test_entrega_serie_de_14_dias_y_comparacion_semanal(): void
    {
        $this->solicitud('completado');
        $this->solicitud('negado');
        $anterior = $this->solicitud('completado');
        $anterior->forceFill(['created_at' => now()->subDays(10)])->save();

        $meta = $this->actingAs($this->staff())
            ->getJson('/api/solicitudes-referencia/historico')
            ->assertOk()
            ->assertJsonCount(14, 'meta.serie')
            ->json('meta');

        $this->assertSame([2, 1], $meta['deltas']['total']);
        $this->assertSame([1, 1], $meta['deltas']['completado']);
        $this->assertSame([1, 0], $meta['deltas']['negado']);
        $this->assertSame(1, $meta['serie'][13]['completadas']);
        $this->assertSame(1, $meta['serie'][13]['negadas']);
    }

    public function test_ids_devuelve_todo_lo_filtrado_sin_paginar(): void
    {
        foreach (range(1, 15) as $i) {
            $this->solicitud('negado');
        }
        $this->solicitud('completado');

        $this->actingAs($this->staff())
            ->getJson('/api/solicitudes-referencia/historico/ids?estado=negado')
            ->assertOk()
            ->assertJsonCount(15, 'data');
    }

    public function test_rechaza_parametros_invalidos(): void
    {
        $this->actingAs($this->staff())
            ->getJson('/api/solicitudes-referencia/historico?por_pagina=9999')
            ->assertStatus(422);
    }

    public function test_la_cola_activa_no_trae_adjuntos_ni_evoluciones_pero_el_detalle_si(): void
    {
        // El listado ordena con FIELD() de MySQL: se emula en SQLite para poder probarlo.
        DB::connection()->getPdo()->sqliteCreateFunction('FIELD', fn (string $valor, string ...$lista): int => ($i = array_search($valor, $lista, true)) === false ? 0 : $i + 1);
        $solicitud = $this->solicitud('pendiente');
        $solicitud->adjuntos()->create(['nombre_original' => 'hc.pdf', 'ruta' => 'x/hc.pdf', 'mime_type' => 'application/pdf', 'tamano' => 10]);
        SolicitudReferenciaEvento::create(['solicitud_referencia_id' => $solicitud->id, 'tipo' => 'nota', 'titulo' => 'Nota', 'descripcion' => 'texto largo']);
        $solicitud->evoluciones()->create(['nota' => 'Paciente estable']);
        $staff = $this->staff();

        $fila = $this->actingAs($staff)->getJson('/api/solicitudes-referencia')->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('adjuntos', $fila);
        $this->assertArrayNotHasKey('evoluciones', $fila);
        $this->assertCount(1, $fila['eventos']);
        $this->assertArrayNotHasKey('descripcion', $fila['eventos'][0]);
        $this->assertSame('pendiente', $fila['estado']);

        $this->actingAs($staff)->getJson("/api/solicitudes-referencia/{$solicitud->id}")
            ->assertOk()
            ->assertJsonPath('data.adjuntos.0.nombre_original', 'hc.pdf')
            ->assertJsonPath('data.evoluciones.0.nota', 'Paciente estable')
            ->assertJsonPath('data.eventos.0.descripcion', 'texto largo');
    }

    public function test_el_detalle_incluye_los_adjuntos(): void
    {
        $solicitud = $this->solicitud('completado');
        $solicitud->adjuntos()->create(['nombre_original' => 'hc.pdf', 'ruta' => 'x/hc.pdf', 'mime_type' => 'application/pdf', 'tamano' => 10]);

        $this->actingAs($this->staff())
            ->getJson("/api/solicitudes-referencia/{$solicitud->id}")
            ->assertOk()
            ->assertJsonPath('data.adjuntos.0.nombre_original', 'hc.pdf')
            ->assertJsonPath('data.resumen_historia_clinica', 'Resumen.');
    }
}
