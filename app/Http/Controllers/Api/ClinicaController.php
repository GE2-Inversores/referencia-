<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ClinicaAprobacion;
use App\Mail\ClinicaRechazo;
use App\Models\Clinica;
use App\Services\NominatimGeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

class ClinicaController extends Controller
{
    public function __construct(private NominatimGeocodingService $geocoding) {}

    private function geocodificar(Clinica $clinica): void
    {
        $coords = $this->geocoding->geocode($clinica->direccion, $clinica->ciudad, $clinica->departamento);

        if ($coords) {
            $clinica->update([
                'latitud' => $coords['lat'],
                'longitud' => $coords['lon'],
                'geocoded_at' => now(),
            ]);
        }
    }

    public function index(): JsonResponse
    {
        abort_unless(request()->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        // withCount/withMax en vez de cargar las relaciones completas: solo
        // se necesita el número de referencias enviadas y la fecha del
        // último envío / último acceso al portal, no las filas en sí.
        $clinicas = Clinica::withCount('solicitudes')
            ->withMax('solicitudes', 'created_at')
            ->withMax('sessions', 'created_at')
            ->orderByRaw("FIELD(estado, 'pendiente', 'activa', 'rechazada')")
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $clinicas]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'nit' => ['required', 'string', 'max:20', 'unique:clinicas,nit'],
            'nombre' => ['required', 'string', 'max:255'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'representante_legal' => ['nullable', 'string', 'max:255'],
            'cedula_representante' => ['nullable', 'string', 'max:20'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'especialidades' => ['nullable', 'array'],
            'especialidades.*' => ['string', 'max:100'],
        ], [
            'nit.unique' => 'Ya existe una clínica registrada con este NIT.',
        ]);

        $clinica = Clinica::create([
            'nit' => $validated['nit'],
            'nombre' => $validated['nombre'],
            'razon_social' => $validated['razon_social'] ?? null,
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'ciudad' => $validated['ciudad'] ?? null,
            'departamento' => $validated['departamento'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'representante_legal' => $validated['representante_legal'] ?? null,
            'cedula_representante' => $validated['cedula_representante'] ?? null,
            'observaciones' => $validated['observaciones'] ?? null,
            'especialidades' => $validated['especialidades'] ?? null,
            'is_active' => true,
            'estado' => 'activa',
        ]);

        $this->geocodificar($clinica);

        return response()->json([
            'message' => 'Clínica creada correctamente.',
            'data' => $clinica->fresh(),
        ], 201);
    }

    public function aprobar(Clinica $clinica): JsonResponse
    {
        abort_unless(request()->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        $clinica->update([
            'is_active' => true,
            'estado' => 'activa',
            'motivo_rechazo' => null,
        ]);

        // Notificar a la clínica
        Mail::to($clinica->email)->send(new ClinicaAprobacion($clinica));

        return response()->json(['message' => 'Clínica aprobada correctamente.']);
    }

    public function update(Request $request, Clinica $clinica): JsonResponse
    {
        abort_unless($request->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'nit' => ['nullable', 'string', 'max:20', 'unique:clinicas,nit,'.$clinica->id],
            'nombre' => ['required', 'string', 'max:255'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'representante_legal' => ['nullable', 'string', 'max:255'],
            'cedula_representante' => ['nullable', 'string', 'max:20'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ], [
            'nit.unique' => 'Ya existe una clínica registrada con este NIT.',
        ]);

        $direccionCambio = $clinica->direccion !== ($validated['direccion'] ?? null)
            || $clinica->ciudad !== ($validated['ciudad'] ?? null)
            || $clinica->departamento !== ($validated['departamento'] ?? null);

        $clinica->update($validated);

        if ($direccionCambio) {
            $this->geocodificar($clinica);
        }

        return response()->json([
            'message' => 'Clínica actualizada correctamente.',
            'data' => $clinica->fresh(),
        ]);
    }

    public function rechazar(Request $request, Clinica $clinica): JsonResponse
    {
        abort_unless($request->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        $clinica->update([
            'is_active' => false,
            'estado' => 'rechazada',
            'motivo_rechazo' => $request->motivo,
        ]);

        // Notificar a la clínica
        Mail::to($clinica->email)->send(new ClinicaRechazo($clinica, $request->motivo));

        return response()->json(['message' => 'Clínica rechazada correctamente.']);
    }

    public function reactivar(Clinica $clinica): JsonResponse
    {
        abort_unless(request()->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        $clinica->update([
            'is_active' => true,
            'estado' => 'activa',
            'motivo_rechazo' => null,
        ]);

        return response()->json(['message' => 'Clínica reactivada correctamente.']);
    }

    public function cargaMasiva(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        $request->validate([
            'clinicas' => ['required', 'array', 'min:1'],
            'clinicas.*.nit' => ['required', 'string', 'max:20'],
            'clinicas.*.nombre' => ['required', 'string', 'max:255'],
            'clinicas.*.email' => ['required', 'email'],
            'clinicas.*.telefono' => ['nullable', 'string', 'max:20'],
            'clinicas.*.direccion' => ['nullable', 'string'],
            'clinicas.*.ciudad' => ['nullable', 'string', 'max:100'],
            'clinicas.*.departamento' => ['nullable', 'string', 'max:100'],
            'clinicas.*.representante_legal' => ['nullable', 'string'],
            'clinicas.*.cedula_representante' => ['nullable', 'string', 'max:20'],
            'clinicas.*.especialidades' => ['nullable', 'array'],
        ]);

        $creadas = 0;
        $omitidas = 0;
        $existentes = [];

        foreach ($request->clinicas as $item) {
            $existe = Clinica::where('nit', $item['nit'])->exists();
            if ($existe) {
                $omitidas++;
                $existentes[] = $item['nit'];

                continue;
            }

            Clinica::create([
                'nit' => $item['nit'],
                'nombre' => $item['nombre'],
                'email' => $item['email'],
                'telefono' => $item['telefono'] ?? null,
                'direccion' => $item['direccion'] ?? null,
                'ciudad' => $item['ciudad'] ?? null,
                'departamento' => $item['departamento'] ?? null,
                'representante_legal' => $item['representante_legal'] ?? null,
                'cedula_representante' => $item['cedula_representante'] ?? null,
                'especialidades' => $item['especialidades'] ?? null,
                'is_active' => true,
                'estado' => 'activa',
            ]);
            $creadas++;
        }

        return response()->json([
            'message' => "Carga masiva completada: {$creadas} creadas, {$omitidas} omitidas.",
            'creadas' => $creadas,
            'omitidas' => $omitidas,
            'existentes' => $existentes,
        ]);
    }
}
