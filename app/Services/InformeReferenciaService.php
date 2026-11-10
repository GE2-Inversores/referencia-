<?php

namespace App\Services;

use App\Models\SolicitudReferencia;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Arma el informe de gestión de referencias para el comité: cuántas se
 * aceptaron, cuántas se negaron y por qué, desglosado por EPS, clínica y
 * especialidad. El período se filtra por la fecha de la solicitud.
 */
class InformeReferenciaService
{
    private const ESTADOS = [
        'pendiente' => 'Nueva solicitud',
        'pendiente_cama' => 'Pendiente (en espera de cupo)',
        'en_espera' => 'Aceptado',
        'completado' => 'Completada',
        'negado' => 'Negada',
        'cancelado' => 'Cancelada',
    ];

    private const GRUPOS_NEGACION = [
        'no_red' => 'Paciente no RED',
        'poblacion_objeto' => 'Población objeto',
    ];

    /** Servicios de la clínica (catálogo `SERVICIOS` del aplicativo). */
    private const SERVICIOS = ['URGENCIAS', 'HOSPITALIZACION', 'UCI', 'UCIN', 'ANGIOGRAFIA/HEMODINAMIA'];

    /** Motivos de "no llegó el paciente" (catálogo `MOTIVOS_NO_INGRESO` del aplicativo). */
    private const MOTIVOS_NO_INGRESO_CATALOGO = [
        'N/A', 'Alta Médica', 'Alta Voluntaria', 'Desestimiento', 'EPS Autoriza Estancia',
        'Fallecimiento', 'Médico cancela trámite', 'No trasladable', 'Otros Motivos',
        'Silencio Administrativo', 'Superó tiempo de respuesta', 'Ubicado en Otra IPS',
        'Direccionamiento CAC',
    ];

    /** Motivos de negación por "Población objeto" (catálogo del aplicativo). */
    private const MOTIVOS_POBLACION_CATALOGO = [
        'No disponibilidad de especialidad', 'No cupo en UCI', 'No cupo en UCIN',
        'Direccionamiento administrativo', 'No disponibilidad de cama en piso',
        'No disponibilidad de insumo/equipo', 'Exclusión del PGP', 'No transladable',
        'Sin respuesta por parte de intensivista', 'Paciente no comentado por la EPS',
        'Soportes incompletos',
    ];

    /**
     * @return array<string, mixed>
     */
    public function generar(CarbonInterface $desde, CarbonInterface $hasta, bool $conDetalle = false): array
    {
        $solicitudes = SolicitudReferencia::query()
            ->with(['clinica:id,nombre', 'diagnosticos'])
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        $negadas = $solicitudes->where('estado', 'negado');
        $noIngreso = $negadas->filter(fn (SolicitudReferencia $s): bool => filled($s->motivo_no_ingreso));
        $negadasDecision = $negadas->reject(fn (SolicitudReferencia $s): bool => filled($s->motivo_no_ingreso));

        $informe = [
            'periodo' => ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()],
            'generado_en' => now()->toIso8601String(),
            'resumen' => $this->resumen($solicitudes, $negadas, $noIngreso, $negadasDecision),
            'por_estado' => $this->porEstado($solicitudes),
            'grupos_negacion' => $this->gruposNegacion($negadasDecision),
            'motivos_negacion' => $this->motivosNegacion($negadasDecision),
            'motivos_no_ingreso' => $this->motivosNoIngreso($noIngreso),
            'no_red_por_eps' => $this->conteo(
                $negadasDecision->reject(fn (SolicitudReferencia $s): bool => $s->grupo_negacion === 'poblacion_objeto'),
                fn (SolicitudReferencia $s): string => $s->eps ?: 'Sin EPS'
            ),
            'motivos_poblacion' => $this->motivosCatalogo($negadasDecision->where('grupo_negacion', 'poblacion_objeto'), self::MOTIVOS_POBLACION_CATALOGO, 'motivo_negacion'),
            'motivos_no_red' => $this->motivosCatalogo($negadasDecision->where('grupo_negacion', 'no_red'), [], 'motivo_negacion'),
            'por_eps' => $this->desglose($solicitudes, fn (SolicitudReferencia $s): string => $s->eps ?: 'Sin EPS'),
            'por_municipio' => $this->conteo($solicitudes, fn (SolicitudReferencia $s): string => $s->municipio_capita ?: 'Sin datos'),
            'poblacion' => [
                'objeto' => $solicitudes->where('grupo_negacion', 'poblacion_objeto')->count(),
                'no_red' => max(0, $solicitudes->count() - $solicitudes->where('grupo_negacion', 'poblacion_objeto')->count()),
            ],
            'por_clinica' => $this->desglose($solicitudes, fn (SolicitudReferencia $s): string => $s->clinica?->nombre ?? ($s->institucion_remitente ?: 'Sin institución')),
            'por_especialidad' => $this->desglose($solicitudes, fn (SolicitudReferencia $s): string => $s->especialidad_requerida ?: 'Sin especialidad'),
            'por_origen' => $this->desglose($solicitudes, fn (SolicitudReferencia $s): string => $s->origen === 'correo' ? 'Correo' : 'Aplicativo'),
            'por_dia' => $this->porDia($solicitudes, $desde, $hasta),
            'tiempo_respuesta' => $this->tiempoRespuesta($solicitudes),
            'anterior' => $this->periodoAnterior($desde, $hasta),
            'detalle_aceptadas' => $this->detalleAceptadas($solicitudes->whereIn('estado', ['en_espera', 'completado'])),
            'detalle_negadas' => [
                'por_eps' => $this->conteo($negadas, fn (SolicitudReferencia $s): string => $s->eps ?: 'Sin EPS'),
                'por_especialidad' => $this->conteo($negadas, fn (SolicitudReferencia $s): string => $s->especialidad_requerida ?: 'Sin especialidad'),
                'por_clinica' => $this->conteo($negadas, fn (SolicitudReferencia $s): string => $s->clinica?->nombre ?? ($s->institucion_remitente ?: 'Sin institución')),
            ],
            'casos_aceptados' => $solicitudes->whereIn('estado', ['en_espera', 'completado'])->map(fn (SolicitudReferencia $s): array => $this->fila($s))->values()->all(),
            'casos_ingresos' => $solicitudes->where('estado', 'completado')->map(fn (SolicitudReferencia $s): array => $this->fila($s))->values()->all(),
            'casos_no_ingreso' => $noIngreso->map(fn (SolicitudReferencia $s): array => $this->fila($s))->values()->all(),
            'casos_negados' => $negadas->map(fn (SolicitudReferencia $s): array => $this->fila($s))->values()->all(),
        ];

        if ($conDetalle) {
            $informe['detalle'] = $solicitudes->map(fn (SolicitudReferencia $s): array => $this->fila($s))->values()->all();
        }

        return $informe;
    }

    /**
     * Hojas del libro de Excel (nombre de hoja => filas; la primera fila es el encabezado).
     *
     * @param  array<string, mixed>  $informe
     * @return array<string, array<int, array<int, string|int|float|null>>>
     */
    public function hojasExcel(array $informe): array
    {
        $r = $informe['resumen'];

        $hojas = [
            'Resumen' => [
                ['Indicador', 'Valor'],
                ['Período', "{$informe['periodo']['desde']} a {$informe['periodo']['hasta']}"],
                ['Solicitudes recibidas', $r['total']],
                ['Aceptadas (aceptado + completada)', $r['aceptadas']],
                ['Pendientes por cupo', $r['pendientes_cupo']],
                ['Nuevas sin respuesta', $r['nuevas']],
                ['Negadas', $r['negadas']],
                ['  Negadas por decisión', $r['negadas_decision']],
                ['  Negadas por no ingreso del paciente', $r['negadas_no_ingreso']],
                ['Canceladas', $r['canceladas']],
                ['Tasa de aceptación (%)', $r['tasa_aceptacion']],
                ['Tasa de negación (%)', $r['tasa_negacion']],
                ['Tasa de cancelación (% del total)', $r['tasa_cancelacion']],
                ['Ingresos efectivos (paciente ingresó)', $r['ingresos_efectivos']],
                ['Aceptados pendientes de ingreso', $r['pendientes_ingreso']],
                ['Pacientes que no ingresaron', $r['negadas_no_ingreso']],
                ['Tasa de ingreso efectivo (%)', $r['tasa_ingreso']],
                ['Tiempo promedio de respuesta (min)', $informe['tiempo_respuesta']['promedio_min']],
                ['Tiempo mediano de respuesta (min)', $informe['tiempo_respuesta']['mediana_min']],
                [],
                ['Estado', 'Solicitudes', '% del total'],
                ...array_map(fn (array $e): array => [$e['label'], $e['total'], $e['pct']], $informe['por_estado']),
            ],
            'Motivos de negación' => [
                ['Grupo', 'Motivo', 'Cantidad', '% de negadas por decisión'],
                ...array_map(fn (array $m): array => [$m['grupo'], $m['motivo'], $m['total'], $m['pct']], $informe['motivos_negacion']),
                [],
                ['Resumen por grupo', '', 'Cantidad', '%'],
                ...array_map(fn (array $g): array => [$g['label'], '', $g['total'], $g['pct']], $informe['grupos_negacion']),
                [],
                ['No ingreso del paciente', 'Motivo', 'Cantidad', '%'],
                ...array_map(fn (array $m): array => ['', $m['motivo'], $m['total'], $m['pct']], $informe['motivos_no_ingreso']),
            ],
        ];

        foreach (['Por EPS' => 'por_eps', 'Por clínica' => 'por_clinica', 'Por especialidad' => 'por_especialidad', 'Por origen' => 'por_origen'] as $hoja => $clave) {
            $hojas[$hoja] = [
                ['Nombre', 'Total', 'Aceptadas', 'Negadas', 'Canceladas', 'Pendientes / nuevas', 'Tasa de aceptación (%)'],
                ...array_map(fn (array $f): array => [$f['nombre'], $f['total'], $f['aceptadas'], $f['negadas'], $f['canceladas'], $f['pendientes'], $f['tasa_aceptacion']], $informe[$clave]),
            ];
        }

        $hojas['Por día'] = [
            ['Fecha', 'Recibidas', 'Aceptadas', 'Negadas'],
            ...array_map(fn (array $d): array => [$d['fecha'], $d['recibidas'], $d['aceptadas'], $d['negadas']], $informe['por_dia']),
        ];

        $a = $informe['detalle_aceptadas'];
        $filasConteo = fn (array $filas): array => array_map(fn (array $f): array => [$f['nombre'], $f['total'], $f['aceptado'], $f['completadas'], $f['pct']], $filas);

        $hojas['Aceptadas por EPS'] = [
            ['Aseguradora', 'Aceptadas', 'Aceptado', 'Completadas', '% de las aceptadas'],
            ['TOTAL', $a['total'], $a['aceptado'], $a['completadas'], 100],
            ...$filasConteo($a['por_eps']),
        ];

        $hojas['Aceptadas por servicio'] = [
            ['Sede de aceptación', 'Aceptadas', 'Aceptado', 'Completadas', '% de las aceptadas'],
            ...$filasConteo($a['por_sede']),
            [],
            ['Servicio de llegada', 'Aceptadas', 'Aceptado', 'Completadas', '% de las aceptadas'],
            ...$filasConteo($a['por_servicio']),
            [],
            ['Especialidad', 'Aceptadas', 'Aceptado', 'Completadas', '% de las aceptadas'],
            ...$filasConteo($a['por_especialidad']),
        ];

        $filasNegadas = fn (array $filas): array => array_map(fn (array $f): array => [$f['nombre'], $f['total'], $f['pct']], $filas);

        $hojas['Negadas por EPS'] = [
            ['Aseguradora', 'Negadas', '% de las negadas'],
            ['TOTAL', $r['negadas'], 100],
            ...$filasNegadas($informe['detalle_negadas']['por_eps']),
            [],
            ['Especialidad', 'Negadas', '% de las negadas'],
            ...$filasNegadas($informe['detalle_negadas']['por_especialidad']),
            [],
            ['Institución que refiere', 'Negadas', '% de las negadas'],
            ...$filasNegadas($informe['detalle_negadas']['por_clinica']),
        ];

        $encabezadoCaso = ['ID', 'Fecha', 'Hora', 'Paciente', 'Documento', 'Origen', 'EPS', 'Institución', 'Especialidad', 'Diagnóstico', 'Estado', 'Grupo de negación', 'Motivo de negación', 'Motivo de no ingreso', 'Observaciones', 'Respondió', 'Hora de respuesta', 'Sede', 'Servicio de llegada', 'Código de aceptación', 'Hora de llegada'];
        $filaCaso = fn (array $c): array => [$c['id'], $c['fecha'], $c['hora'], $c['paciente'], $c['documento'], $c['origen'], $c['eps'], $c['institucion'], $c['especialidad'], $c['diagnostico'], $c['estado'], $c['grupo_negacion'], $c['motivo_negacion'], $c['motivo_no_ingreso'], $c['observaciones'], $c['responde'], $c['hora_respuesta'], $c['sede'], $c['servicio_llegada'], $c['codigo_aceptacion'], $c['hora_llegada']];

        $hojas['Ingresos efectivos'] = [
            ['ID', 'Fecha', 'Paciente', 'Documento', 'EPS', 'Institución', 'Especialidad', 'Sede', 'Servicio de llegada', 'Código de aceptación', 'Número de ingreso'],
            ...array_map(fn (array $c): array => [$c['id'], $c['fecha'], $c['paciente'], $c['documento'], $c['eps'], $c['institucion'], $c['especialidad'], $c['sede'], $c['servicio_llegada'], $c['codigo_aceptacion'], $c['numero_ingreso']], $informe['casos_ingresos']),
        ];

        $hojas['No ingresaron'] = [
            ['ID', 'Fecha', 'Paciente', 'Documento', 'EPS', 'Institución', 'Especialidad', 'Motivo de no ingreso', 'Observaciones', 'Respondió'],
            ...array_map(fn (array $c): array => [$c['id'], $c['fecha'], $c['paciente'], $c['documento'], $c['eps'], $c['institucion'], $c['especialidad'], $c['motivo_no_ingreso'], $c['observaciones'], $c['responde']], $informe['casos_no_ingreso']),
            [],
            ['Resumen por motivo', '', '', '', '', '', '', 'Cantidad', '% de los no ingresos'],
            ...array_map(fn (array $m): array => ['', '', '', '', '', '', '', $m['motivo'].' — '.$m['total'], $m['pct']], $informe['motivos_no_ingreso']),
        ];

        $hojas['Casos aceptados'] = [$encabezadoCaso, ...array_map($filaCaso, $informe['casos_aceptados'])];
        $hojas['Casos negados'] = [$encabezadoCaso, ...array_map($filaCaso, $informe['casos_negados'])];

        if (isset($informe['detalle'])) {
            $hojas['Detalle'] = [$encabezadoCaso, ...array_map($filaCaso, $informe['detalle'])];
        }

        return $hojas;
    }

    /**
     * @param  Collection<int, SolicitudReferencia>  $solicitudes
     * @param  Collection<int, SolicitudReferencia>  $negadas
     * @param  Collection<int, SolicitudReferencia>  $noIngreso
     * @param  Collection<int, SolicitudReferencia>  $negadasDecision
     * @return array<string, int|float>
     */
    private function resumen(Collection $solicitudes, Collection $negadas, Collection $noIngreso, Collection $negadasDecision): array
    {
        $aceptadas = $solicitudes->whereIn('estado', ['en_espera', 'completado'])->count();
        $resueltas = $aceptadas + $negadas->count();

        return [
            'total' => $solicitudes->count(),
            'aceptadas' => $aceptadas,
            'aceptado' => $solicitudes->where('estado', 'en_espera')->count(),
            'completadas' => $solicitudes->where('estado', 'completado')->count(),
            'pendientes_cupo' => $solicitudes->where('estado', 'pendiente_cama')->count(),
            'nuevas' => $solicitudes->where('estado', 'pendiente')->count(),
            'negadas' => $negadas->count(),
            'negadas_decision' => $negadasDecision->count(),
            'negadas_no_ingreso' => $noIngreso->count(),
            'canceladas' => $solicitudes->where('estado', 'cancelado')->count(),
            'tasa_aceptacion' => $this->porcentaje($aceptadas, $resueltas),
            'tasa_negacion' => $this->porcentaje($negadas->count(), $resueltas),
            'tasa_cancelacion' => $this->porcentaje($solicitudes->where('estado', 'cancelado')->count(), $solicitudes->count()),
            'ingresos_efectivos' => $solicitudes->where('estado', 'completado')->count(),
            'pendientes_ingreso' => $solicitudes->where('estado', 'en_espera')->count(),
            'tasa_ingreso' => $this->porcentaje(
                $solicitudes->where('estado', 'completado')->count(),
                $solicitudes->where('estado', 'completado')->count() + $noIngreso->count(),
            ),
        ];
    }

    /**
     * @param  Collection<int, SolicitudReferencia>  $solicitudes
     * @return array<int, array{estado: string, label: string, total: int, pct: float}>
     */
    private function porEstado(Collection $solicitudes): array
    {
        $total = $solicitudes->count();

        return collect(self::ESTADOS)
            ->map(fn (string $label, string $estado): array => [
                'estado' => $estado,
                'label' => $label,
                'total' => $solicitudes->where('estado', $estado)->count(),
                'pct' => $this->porcentaje($solicitudes->where('estado', $estado)->count(), $total),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SolicitudReferencia>  $negadasDecision
     * @return array<int, array{grupo: string, label: string, total: int, pct: float}>
     */
    private function gruposNegacion(Collection $negadasDecision): array
    {
        $total = $negadasDecision->count();

        return $negadasDecision
            ->groupBy(fn (SolicitudReferencia $s): string => $s->grupo_negacion ?: 'sin_grupo')
            ->map(fn (Collection $grupo, string $clave): array => [
                'grupo' => $clave,
                'label' => self::GRUPOS_NEGACION[$clave] ?? 'Sin clasificar',
                'total' => $grupo->count(),
                'pct' => $this->porcentaje($grupo->count(), $total),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SolicitudReferencia>  $negadasDecision
     * @return array<int, array{grupo: string, motivo: string, total: int, pct: float}>
     */
    private function motivosNegacion(Collection $negadasDecision): array
    {
        $total = $negadasDecision->count();

        return $negadasDecision
            ->groupBy(fn (SolicitudReferencia $s): string => (self::GRUPOS_NEGACION[$s->grupo_negacion] ?? 'Sin clasificar').'|'.$this->normalizar((string) $s->motivo_negacion))
            ->map(function (Collection $grupo): array {
                /** @var SolicitudReferencia $primera */
                $primera = $grupo->first();

                return [
                    'grupo' => self::GRUPOS_NEGACION[$primera->grupo_negacion] ?? 'Sin clasificar',
                    'motivo' => trim((string) $primera->motivo_negacion) ?: 'Sin motivo registrado',
                    'total' => $grupo->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->map(fn (array $m): array => $m + ['pct' => $this->porcentaje($m['total'], $total)])
            ->all();
    }

    /**
     * @param  Collection<int, SolicitudReferencia>  $noIngreso
     * @return array<int, array{motivo: string, total: int, pct: float}>
     */
    private function motivosNoIngreso(Collection $noIngreso): array
    {
        return $this->motivosCatalogo($noIngreso, self::MOTIVOS_NO_INGRESO_CATALOGO, 'motivo_no_ingreso');
    }

    /**
     * Cuenta un catálogo de motivos: primero los que tienen casos (de mayor a
     * menor), luego el resto del catálogo en orden, y al final cualquier
     * motivo guardado que ya no esté en el catálogo.
     *
     * @param  Collection<int, SolicitudReferencia>  $items
     * @param  array<int, string>  $catalogo
     * @return array<int, array{motivo: string, total: int, pct: float}>
     */
    private function motivosCatalogo(Collection $items, array $catalogo, string $campo): array
    {
        $total = $items->count();
        $conteos = $items->groupBy(fn (SolicitudReferencia $s): string => $this->normalizar((string) $s->{$campo}));
        $norm = collect($catalogo)->map(fn (string $m): string => $this->normalizar($m));

        $filas = collect($catalogo)->map(function (string $motivo) use ($conteos, $total): array {
            $cantidad = $conteos->get($this->normalizar($motivo), collect())->count();

            return ['motivo' => $motivo, 'total' => $cantidad, 'pct' => $this->porcentaje($cantidad, $total)];
        });

        $extras = $conteos
            ->reject(fn (Collection $grupo, string $clave): bool => $norm->contains($clave))
            ->map(fn (Collection $grupo): array => [
                'motivo' => trim((string) $grupo->first()->{$campo}) ?: 'Sin motivo registrado',
                'total' => $grupo->count(),
                'pct' => $this->porcentaje($grupo->count(), $total),
            ]);

        return $filas->concat($extras)
            ->sortBy(fn (array $f): int => $f['total'] === 0 ? 1_000_000 : -$f['total'])
            ->values()
            ->all();
    }

    /**
     * A qué servicio de la clínica se aceptó cada solicitud. `lugar_llegada`
     * es texto libre, así que se clasifica en el catálogo real de servicios.
     *
     * @param  Collection<int, SolicitudReferencia>  $aceptadas
     * @return array<int, array{nombre: string, total: int, aceptado: int, completadas: int, pct: float}>
     */
    private function serviciosLlegada(Collection $aceptadas): array
    {
        $total = $aceptadas->count();
        $orden = [...self::SERVICIOS, 'Otros', 'Sin datos'];
        $conteos = array_fill_keys($orden, ['total' => 0, 'aceptado' => 0, 'completadas' => 0]);

        foreach ($aceptadas as $s) {
            $servicio = $this->clasificarServicio($s->lugar_llegada);
            $conteos[$servicio]['total']++;
            if ($s->estado === 'en_espera') {
                $conteos[$servicio]['aceptado']++;
            }
            if ($s->estado === 'completado') {
                $conteos[$servicio]['completadas']++;
            }
        }

        return collect($conteos)
            ->map(fn (array $c, string $nombre): array => $c + ['nombre' => $nombre, 'pct' => $this->porcentaje($c['total'], $total)])
            ->values()
            ->all();
    }

    private function clasificarServicio(?string $lugar): string
    {
        $texto = mb_strtoupper(trim((string) $lugar));

        if ($texto === '') {
            return 'Sin datos';
        }

        foreach (['UCIN', 'UCI', 'URGENCIA', 'ANGIOGRAFIA', 'HEMODINAMIA', 'HOSPITALIZ'] as $prefijo) {
            if (str_contains($texto, $prefijo)) {
                return match ($prefijo) {
                    'URGENCIA' => 'URGENCIAS',
                    'HOSPITALIZ' => 'HOSPITALIZACION',
                    'ANGIOGRAFIA', 'HEMODINAMIA' => 'ANGIOGRAFIA/HEMODINAMIA',
                    default => $prefijo,
                };
            }
        }

        return 'Otros';
    }

    /**
     * @param  Collection<int, SolicitudReferencia>  $solicitudes
     * @param  callable(SolicitudReferencia): string  $clave
     * @return array<int, array<string, int|float|string>>
     */
    private function desglose(Collection $solicitudes, callable $clave): array
    {
        return $solicitudes
            ->groupBy(fn (SolicitudReferencia $s): string => $this->normalizar($clave($s)))
            ->map(function (Collection $grupo) use ($clave): array {
                $aceptadas = $grupo->whereIn('estado', ['en_espera', 'completado'])->count();
                $negadas = $grupo->where('estado', 'negado')->count();

                return [
                    'nombre' => $clave($grupo->first()),
                    'total' => $grupo->count(),
                    'aceptadas' => $aceptadas,
                    'negadas' => $negadas,
                    'canceladas' => $grupo->where('estado', 'cancelado')->count(),
                    'pendientes' => $grupo->whereIn('estado', ['pendiente', 'pendiente_cama'])->count(),
                    'tasa_aceptacion' => $this->porcentaje($aceptadas, $aceptadas + $negadas),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SolicitudReferencia>  $solicitudes
     * @return array<int, array{fecha: string, recibidas: int, aceptadas: int, negadas: int}>
     */
    private function porDia(Collection $solicitudes, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $porFecha = $solicitudes->groupBy(fn (SolicitudReferencia $s): string => $s->fecha->toDateString());

        return collect(CarbonPeriod::create($desde, $hasta))
            ->map(function (CarbonInterface $dia) use ($porFecha): array {
                $fecha = $dia->toDateString();
                $delDia = $porFecha->get($fecha, collect());

                return [
                    'fecha' => $fecha,
                    'recibidas' => $delDia->count(),
                    'aceptadas' => $delDia->whereIn('estado', ['en_espera', 'completado'])->count(),
                    'completadas' => $delDia->where('estado', 'completado')->count(),
                    'negadas' => $delDia->where('estado', 'negado')->count(),
                    'canceladas' => $delDia->where('estado', 'cancelado')->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Totales del período inmediatamente anterior, de igual duración, para
     * comparar ("vs. período anterior").
     *
     * @return array<string, int|string|null>
     */
    private function periodoAnterior(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $dias = (int) $desde->diffInDays($hasta) + 1;
        $anteriorHasta = $desde->copy()->subDay();
        $anteriorDesde = $anteriorHasta->copy()->subDays($dias - 1);

        $solicitudes = SolicitudReferencia::query()
            ->select(['id', 'fecha', 'hora', 'hora_respuesta', 'estado'])
            ->whereDate('fecha', '>=', $anteriorDesde->toDateString())
            ->whereDate('fecha', '<=', $anteriorHasta->toDateString())
            ->get();

        return [
            'desde' => $anteriorDesde->toDateString(),
            'hasta' => $anteriorHasta->toDateString(),
            'total' => $solicitudes->count(),
            'aceptadas' => $solicitudes->whereIn('estado', ['en_espera', 'completado'])->count(),
            'completadas' => $solicitudes->where('estado', 'completado')->count(),
            'negadas' => $solicitudes->where('estado', 'negado')->count(),
            'canceladas' => $solicitudes->where('estado', 'cancelado')->count(),
            'pendientes_cupo' => $solicitudes->where('estado', 'pendiente_cama')->count(),
            'tiempo_promedio_min' => $this->tiempoRespuesta($solicitudes)['promedio_min'],
        ];
    }

    /**
     * Minutos entre la solicitud y la respuesta. Si la hora de respuesta es
     * menor que la de la solicitud, la respuesta fue después de medianoche.
     *
     * @param  Collection<int, SolicitudReferencia>  $solicitudes
     * @return array{respondidas: int, promedio_min: int|null, mediana_min: int|null}
     */
    private function tiempoRespuesta(Collection $solicitudes): array
    {
        $minutos = $solicitudes
            ->filter(fn (SolicitudReferencia $s): bool => filled($s->hora) && filled($s->hora_respuesta))
            ->map(function (SolicitudReferencia $s): int {
                $diferencia = $this->aMinutos($s->hora_respuesta) - $this->aMinutos($s->hora);

                return $diferencia < 0 ? $diferencia + 1440 : $diferencia;
            })
            ->sort()
            ->values();

        if ($minutos->isEmpty()) {
            return ['respondidas' => 0, 'promedio_min' => null, 'mediana_min' => null];
        }

        return [
            'respondidas' => $minutos->count(),
            'promedio_min' => (int) round($minutos->avg()),
            'mediana_min' => (int) $minutos[intdiv($minutos->count(), 2)],
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function fila(SolicitudReferencia $s): array
    {
        $diagnostico = $s->diagnosticos->first();

        return [
            'id' => $s->id,
            'fecha' => $s->fecha->toDateString(),
            'hora' => substr((string) $s->hora, 0, 5),
            'paciente' => trim(preg_replace('/\s+/', ' ', "{$s->primer_nombre} {$s->segundo_nombre} {$s->primer_apellido} {$s->segundo_apellido}")),
            'documento' => trim("{$s->tipo_documento} {$s->numero_documento}"),
            'origen' => $s->origen === 'correo' ? 'Correo' : 'Aplicativo',
            'eps' => $s->eps,
            'institucion' => $s->clinica?->nombre ?? $s->institucion_remitente,
            'especialidad' => $s->especialidad_requerida,
            'diagnostico' => $diagnostico
                ? trim("{$diagnostico->codigo_cie10} {$diagnostico->descripcion}")
                : $s->diagnostico,
            'estado' => self::ESTADOS[$s->estado] ?? $s->estado,
            'grupo_negacion' => self::GRUPOS_NEGACION[$s->grupo_negacion] ?? null,
            'motivo_negacion' => $s->motivo_negacion,
            'motivo_no_ingreso' => $s->motivo_no_ingreso,
            'observaciones' => $s->observaciones_respuesta,
            'responde' => $s->nombre_quien_responde,
            'hora_respuesta' => $s->hora_respuesta ? substr((string) $s->hora_respuesta, 0, 5) : null,
            'sede' => $s->sede,
            'servicio_llegada' => $s->lugar_llegada,
            'codigo_aceptacion' => in_array($s->estado, ['en_espera', 'completado'], true) ? $s->codigo_aceptacion : null,
            'hora_llegada' => $s->hora_llegada ? substr((string) $s->hora_llegada, 0, 5) : null,
            'numero_ingreso' => $s->numero_ingreso,
        ];
    }

    /**
     * Detalle de las solicitudes aceptadas: de qué aseguradora son, a qué
     * sede y servicio de llegada se aceptaron y para qué especialidad.
     *
     * @param  Collection<int, SolicitudReferencia>  $aceptadas
     * @return array<string, mixed>
     */
    private function detalleAceptadas(Collection $aceptadas): array
    {
        $mayusculas = fn (?string $texto, string $vacio): string => filled(trim((string) $texto)) ? mb_strtoupper(trim((string) $texto)) : $vacio;

        return [
            'total' => $aceptadas->count(),
            'aceptado' => $aceptadas->where('estado', 'en_espera')->count(),
            'completadas' => $aceptadas->where('estado', 'completado')->count(),
            'por_eps' => $this->conteo($aceptadas, fn (SolicitudReferencia $s): string => $s->eps ?: 'Sin EPS'),
            'por_sede' => $this->conteo($aceptadas, fn (SolicitudReferencia $s): string => $mayusculas($s->sede, 'SIN SEDE REGISTRADA')),
            'por_servicio' => $this->serviciosLlegada($aceptadas),
            'por_especialidad' => $this->conteo($aceptadas, fn (SolicitudReferencia $s): string => $s->especialidad_requerida ?: 'Sin especialidad'),
        ];
    }

    /**
     * Cuenta solicitudes por una clave (sin distinguir mayúsculas ni espacios
     * repetidos), con cuántas siguen en "Aceptado" y cuántas ya se completaron.
     *
     * @param  Collection<int, SolicitudReferencia>  $items
     * @param  callable(SolicitudReferencia): string  $clave
     * @return array<int, array{nombre: string, total: int, aceptado: int, completadas: int, pct: float}>
     */
    private function conteo(Collection $items, callable $clave): array
    {
        $total = $items->count();

        return $items
            ->groupBy(fn (SolicitudReferencia $s): string => $this->normalizar($clave($s)))
            ->map(fn (Collection $grupo): array => [
                'nombre' => $clave($grupo->first()),
                'total' => $grupo->count(),
                'aceptado' => $grupo->where('estado', 'en_espera')->count(),
                'completadas' => $grupo->where('estado', 'completado')->count(),
                'pct' => $this->porcentaje($grupo->count(), $total),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    private function aMinutos(?string $hora): int
    {
        [$h, $m] = array_map('intval', explode(':', (string) $hora) + [0, 0]);

        return $h * 60 + $m;
    }

    private function porcentaje(int $parte, int $total): float
    {
        return $total > 0 ? round($parte / $total * 100, 1) : 0.0;
    }

    private function normalizar(string $texto): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $texto)));
    }
}
