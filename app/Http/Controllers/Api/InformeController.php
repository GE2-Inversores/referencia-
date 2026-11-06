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
