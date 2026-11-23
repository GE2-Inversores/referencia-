<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SolicitudReferencia;
use App\Services\InformeReferenciaService;
use App\Support\XlsxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InformeController extends Controller
{
    public function __construct(private InformeReferenciaService $informes) {}

    public function index(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->periodo($request);

        return response()->json(['data' => $this->informes->generar($desde, $hasta)]);
    }

    /**
     * Movimiento de la Bandeja de correo para su propio filtro de fechas;
     * por defecto, el día de hoy.
     */
    public function correos(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('dashboard.view'), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $hoy = CarbonImmutable::now(config('app.display_timezone', config('app.timezone')))->startOfDay();
        $desde = CarbonImmutable::parse($validated['desde'] ?? $hoy->toDateString())->startOfDay();
        $hasta = CarbonImmutable::parse($validated['hasta'] ?? $validated['desde'] ?? $hoy->toDateString())->startOfDay();

        return response()->json(['data' => $this->informes->correos($desde, $hasta)]);
    }

    public function exportar(Request $request): BinaryFileResponse
    {
        [$desde, $hasta] = $this->periodo($request);

        $informe = $this->informes->generar($desde, $hasta, conDetalle: true);
        $ruta = tempnam(sys_get_temp_dir(), 'informe');

        XlsxWriter::guardar($ruta, $this->informes->hojasExcel($informe));

        return response()
            ->download($ruta, "informe-referencias_{$desde->toDateString()}_{$hasta->toDateString()}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * Excel con todas las negadas del historial en el formato de la hoja
     * NEGADOS del Drive; no depende del período del informe.
     */
    public function exportarBase(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasPermission('dashboard.view'), Response::HTTP_FORBIDDEN);

        $ruta = tempnam(sys_get_temp_dir(), 'base');

        XlsxWriter::guardar($ruta, ['NEGADOS' => $this->informes->filasBaseReferencia()], presentacion: true);

        return response()
            ->download($ruta, 'BASE REFERENCIA_'.now()->toDateString().'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * Período del informe; por defecto el mes en curso.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periodo(Request $request): array
    {
        abort_unless($request->user()->hasPermission('dashboard.view'), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $hoy = CarbonImmutable::now(config('app.display_timezone', config('app.timezone')));
        $primera = SolicitudReferencia::query()->min('fecha');

        // Sin fechas el informe cubre todo el historial; el filtro recorta.
        return [
            CarbonImmutable::parse($validated['desde'] ?? substr((string) $primera, 0, 10) ?: $hoy->toDateString())->startOfDay(),
            CarbonImmutable::parse($validated['hasta'] ?? $hoy->toDateString())->startOfDay(),
        ];
    }
}
