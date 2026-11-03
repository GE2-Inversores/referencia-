<?php

namespace App\Http\Controllers\Api\Clinica;

use App\Http\Controllers\Controller;
use App\Mail\NuevaEvolucionPaciente;
use App\Mail\NuevaSolicitudReferenciaInterna;
use App\Mail\SolicitudRecibida;
use App\Models\CiudadGomedisys;
use App\Models\Clinica;
use App\Models\ClinicaSession;
use App\Models\DiagnosticoCie10;
use App\Models\Notification;
use App\Models\SolicitudReferencia;
use App\Models\SolicitudReferenciaAdjunto;
use App\Models\SolicitudReferenciaEvento;
use App\Models\User;
use App\Services\NominatimGeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SolicitudReferenciaController extends Controller
{
    public function __construct(private NominatimGeocodingService $geocoding) {}

    /**
     * Destinatarios de las notificaciones internas (nueva solicitud, nueva
     * evolución). Si MAIL_REFERENCIA_INTERNA está definida en el .env se
     * envía SOLO a ese correo; si no, a los usuarios activos con permiso
     * clinicas.view, descartando correos inválidos o de dominios de prueba
     * (un solo destinatario inválido hace que el servidor rechace todo).
     *
     * @return array<int, string>
     */
    private function destinatariosInternos(): array
    {
        $correoFijo = trim((string) config('mail.referencia_interna', ''));

        if ($correoFijo !== '' && self::correoValido($correoFijo)) {
            return [$correoFijo];
        }

        $destinatarios = User::permission('clinicas.view')
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->all();

        $destinatarios = array_values(array_filter($destinatarios, fn (string $correo): bool => self::correoValido($correo)));

        return $destinatarios !== []
            ? $destinatarios
            : [config('mail.referencia_interna')];
    }

    /** Correo con formato válido y sin dominios reservados para pruebas (example.*, *.test). */
    private static function correoValido(string $correo): bool
    {
        if (! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return ! preg_match('/@(example\.(com|net|org)|[^.]+\.test)$/i', $correo);
    }

    private function getClinicaId(Request $request): ?int
    {
        $token = $request->bearerToken() ?? $request->header('X-Clinica-Token');

        if (! $token) {
            return null;
        }

        $session = ClinicaSession::where('token', hash('sha256', $token))
            ->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        return $session?->clinica_id;
    }

    public function index(Request $request): JsonResponse
    {
        $clinicaId = $this->getClinicaId($request);

        if (! $clinicaId) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $solicitudes = SolicitudReferencia::where('clinica_id', $clinicaId)
            ->with(['adjuntos', 'eventos', 'diagnosticos'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $solicitudes]);
    }

    /**
     * Busca en el catálogo real de diagnósticos CIE-10 (importado directo
     * de la tabla `diagnostics` de Gomedisys — ver migración
     * create_diagnosticos_cie10_table) por código o por descripción. Antes
     * el formulario filtraba una lista fija de 332 códigos de categoría
     * incompletos (ej. "B34" en vez de "B34.2"), que Gomedisys nunca
     * reconocía — ahora busca contra los ~12.500 códigos específicos que sí
     * existen allá.
     */
    public function buscarDiagnosticosCie10(Request $request): JsonResponse
    {
        $buscar = trim((string) $request->query('buscar', ''));

        if ($buscar === '') {
            return response()->json(['data' => []]);
        }

        $resultados = DiagnosticoCie10::query()
            ->where('codigo', 'not like', 'R%')
            ->where(function ($q) use ($buscar) {
                $q->where('codigo', 'like', $buscar.'%')
                    ->orWhere('descripcion', 'like', '%'.$buscar.'%');
            })
            ->orderByRaw('CASE WHEN codigo LIKE ? THEN 0 ELSE 1 END', [$buscar.'%'])
            ->orderBy('descripcion')
            ->limit(20)
            ->get(['codigo', 'descripcion']);

        return response()->json(['data' => $resultados]);
    }

    /**
     * Catálogo completo de municipios de Gomedisys (importado directo de su
     * tabla `generalPoliticalDivisions` — ver migración
     * create_ciudades_gomedisys_table), para la lista desplegable del campo
     * "Municipio". Se manda completo (~1.100 filas) para que el formulario
     * solo permita elegir de la lista real en vez de escribir texto libre,
     * que podía tener errores de formato o nombres que Gomedisys no
     * reconocía al buscar.
     */
    public function listarCiudadesGomedisys(Request $request): JsonResponse
    {
        $ciudades = CiudadGomedisys::orderBy('nombre')->get(['nombre']);

        return response()->json(['data' => $ciudades]);
    }

    /**
     * Lista de clínicas registradas para el desplegable "Institución que
     * refiere" del formulario público. Solo se expone id + nombre +
     * ubicación (el correo es dato de contacto interno, no se publica).
     * Las rechazadas no aparecen — toda clínica registrada queda activa
     * de inmediato (no hay flujo de aprobación).
     */
    public function listarClinicas(): JsonResponse
    {
        $clinicas = Clinica::where('estado', '!=', 'rechazada')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'ciudad', 'departamento']);

        return response()->json(['data' => $clinicas]);
    }

    /**
     * Registro público de una clínica desde el modal "Datos de la clínica
     * que refiere" del formulario. No pide NIT (el personal puede
     * completarlo después en el módulo de clínicas): queda activa de una
     * — no hay aprobación —, aparece en el desplegable y en el mapa de
     * una, y su correo es al que llegan las notificaciones de estado de
     * sus solicitudes.
     */
    public function registrarClinica(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'municipio' => ['nullable', 'string', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:150'],
            'persona_cargo' => ['nullable', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:30'],
        ]);

        // Misma institución = misma clínica (match sin importar mayúsculas)
        // — así dos remisiones no crean duplicados.
        [$ciudad, $departamento] = $validated['municipio']
            ? array_pad(array_map('trim', explode(',', $validated['municipio'], 2)), 2, null)
            : [null, null];

        $clinica = Clinica::query()
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower(trim($validated['nombre']))])
            ->first();

        if (! $clinica) {
            $clinica = Clinica::create([
                'nombre' => trim($validated['nombre']),
                'ciudad' => $ciudad,
                'departamento' => $departamento,
                'direccion' => $validated['direccion'] ?? null,
                'email' => $validated['email'],
                'telefono' => $validated['telefono'] ?? null,
                'representante_legal' => $validated['persona_cargo'] ?? null,
                'is_active' => true,
                'estado' => 'activa',
            ]);

            $coords = $this->geocoding->geocode($clinica->direccion, $clinica->ciudad, $clinica->departamento);

            if ($coords) {
                $clinica->update([
                    'latitud' => $coords['lat'],
                    'longitud' => $coords['lon'],
                    'geocoded_at' => now(),
                ]);
            }
        }

        return response()->json([
            'data' => $clinica->only(['id', 'nombre', 'ciudad', 'departamento']),
            'message' => 'Clínica registrada correctamente.',
        ], 201);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha' => ['nullable', 'date'],
            'hora' => ['nullable', 'date_format:H:i'],
            'primer_nombre' => ['required', 'string', 'max:80'],
            'segundo_nombre' => ['nullable', 'string', 'max:80'],
            'primer_apellido' => ['required', 'string', 'max:80'],
            'segundo_apellido' => ['nullable', 'string', 'max:80'],
            'genero' => ['required', 'in:M,F'],
            'edad' => ['required', 'integer', 'min:0', 'max:120'],
            'tipo_documento' => ['required', 'string', 'max:5'],
            'numero_documento' => ['required', 'string', 'max:30'],
            'eps' => ['required', 'string', 'max:120'],
            'diagnostico' => ['nullable', 'string', 'max:400'],
            'diagnosticos' => ['nullable', 'array'],
            'diagnosticos.*.codigo_cie10' => ['nullable', 'string', 'max:20'],
            'diagnosticos.*.descripcion' => ['required', 'string', 'max:400'],
            'municipio_capita' => ['required', 'string', 'max:120'],
            // Mismos límites que #addressPatient / #telecomPatient en Gomedisys.
            'direccion_paciente' => ['nullable', 'string', 'max:25'],
            'telefono_paciente' => ['nullable', 'string', 'max:15', 'regex:/^[0-9]*$/'],
            'especialidad_requerida' => ['required', 'string', 'max:120'],
            'tipo_referencia' => ['required', 'string', 'in:Referencia redonda,Referencia integral'],
            'urgencia_vital' => ['sometimes', 'boolean'],
            'servicio_ubicacion_actual' => ['required', 'string', 'max:60'],
            'servicio_remision' => ['nullable', 'string', 'max:60'],
            'institucion_remitente' => ['nullable', 'string', 'max:150'],
            // Clínica elegida del desplegable "Institución que refiere" —
            // vincula la solicitud para que las notificaciones lleguen al
            // correo registrado de esa clínica.
            'clinica_id' => ['nullable', 'integer', 'exists:clinicas,id'],
            'municipio_remitente' => ['nullable', 'string', 'max:120'],
            'requiere_aislamiento' => ['nullable', 'boolean'],
            'tipo_aislamiento' => ['nullable', 'string', 'in:contacto,respiratorio,cohortizado'],
            'quien_remitente' => ['required', 'string', 'max:200'],
            'telefono_contacto' => ['required', 'string', 'max:30'],
            'correo_contacto' => ['nullable', 'email', 'max:150'],
            'resumen_historia_clinica' => ['required', 'string'],
            'via_contacto' => ['nullable', 'string', 'max:20'],
            'gestante' => ['nullable', 'boolean'],
            'condicion_especial' => ['nullable', 'string', 'max:250'],
            'observaciones' => ['nullable', 'string'],
            'adjuntos' => ['nullable', 'array', 'max:10'],
            'adjuntos.*' => ['file', 'mimes:pdf,jpg,jpeg,png,gif,webp,bmp,tiff,svg,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar', 'max:10240'],
        ]);

        // El formulario de remisión es público: la clínica puede venir de una
        // sesión de clínica válida o del desplegable "Institución que refiere".
        $clinicaId = $this->getClinicaId($request) ?? ($validated['clinica_id'] ?? null);
        $clinica = $clinicaId ? Clinica::find($clinicaId) : null;

        if ($clinica) {
            $validated['institucion_remitente'] = $clinica->nombre;
            // El correo de la clínica es al que llegan las notificaciones.
            $validated['correo_contacto'] = $clinica->email;
        }

        $validated['fecha'] ??= now()->toDateString();
        $validated['hora'] ??= now()->format('H:i');
        $validated['clinica_id'] = $clinicaId;
        $validated['estado'] = 'pendiente';
        // Normaliza el espacio alrededor de la coma ("Palmira,Valle del
        // Cauca" → "Palmira, Valle del Cauca") — Gomedisys siempre muestra
        // sus municipios con ese formato exacto, y sin el espacio la
        // extensión que autocompleta el registro no encuentra la opción
        // aunque sí exista en su catálogo.
        $validated['municipio_capita'] = trim(preg_replace('/\s*,\s*/', ', ', $validated['municipio_capita']));

        $adjuntos = $validated['adjuntos'] ?? [];
        $diagnosticos = $validated['diagnosticos'] ?? [];
        unset($validated['adjuntos'], $validated['diagnosticos']);

        $rutasAdjuntos = [];
        $falloEnvioCorreo = false;

        try {
            $solicitud = DB::transaction(function () use ($validated, $adjuntos, $diagnosticos, $clinica, &$rutasAdjuntos, &$falloEnvioCorreo): SolicitudReferencia {
                $solicitud = SolicitudReferencia::create($validated);

                foreach ($diagnosticos as $dx) {
                    $solicitud->diagnosticos()->create([
                        'codigo_cie10' => $dx['codigo_cie10'] ?? '',
                        'descripcion' => $dx['descripcion'],
                    ]);
                }

                foreach ($adjuntos as $adjunto) {
                    $ruta = $adjunto->store("solicitudes-referencia/{$solicitud->id}", 'local');
                    $rutasAdjuntos[] = $ruta;

                    $solicitud->adjuntos()->create([
                        'nombre_original' => $adjunto->getClientOriginalName(),
                        'ruta' => $ruta,
                        'mime_type' => $adjunto->getMimeType(),
                        'tamano' => $adjunto->getSize(),
                    ]);
                }

                SolicitudReferenciaEvento::create([
                    'solicitud_referencia_id' => $solicitud->id,
                    'tipo' => 'creada',
                    'titulo' => 'Solicitud enviada',
                    'descripcion' => 'La institución envió la solicitud para revisión.',
                ]);

                // Al estar en la transacción, las notificaciones internas
                // también se revierten si no se logra enviar el correo.
                $institucion = $clinica?->nombre ?? $validated['institucion_remitente'] ?? 'Entidad externa';
                $paciente = trim("{$validated['primer_nombre']} {$validated['primer_apellido']}");
                $this->notificarUsuarios(
                    titulo: '🏥Nueva solicitud de referencia',
                    mensaje: "La institución \"{$institucion}\" solicita referencia para el paciente {$paciente} — {$validated['especialidad_requerida']}.",
                    tipo: 'warning',
                    link: "/solicitudes-referencia?resaltar={$solicitud->id}",
                );

                $destinatarios = $this->destinatariosInternos();

                $solicitud->load(['adjuntos', 'eventos', 'diagnosticos']);

                try {
                    // Envío síncrono intencional: la solicitud solo se confirma
                    // si el servidor de correo acepta el mensaje.
                    Mail::to($destinatarios)->send(new NuevaSolicitudReferenciaInterna($clinica, $solicitud));
                } catch (Throwable $exception) {
                    $falloEnvioCorreo = true;

                    throw $exception;
                }

                return $solicitud;
            });
        } catch (Throwable $exception) {
            if ($rutasAdjuntos !== []) {
                try {
                    Storage::disk('local')->delete($rutasAdjuntos);
                } catch (Throwable $cleanupException) {
                    Log::warning('No se pudieron eliminar adjuntos de una solicitud revertida', [
                        'error' => $cleanupException->getMessage(),
                    ]);
                }
            }

            if (! $falloEnvioCorreo) {
                throw $exception;
            }

            Log::error('No se pudo enviar el correo de una nueva solicitud; se revirtió el registro', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo enviar el correo de notificación. La solicitud no se registró; intente enviarla nuevamente.',
            ], 503);
        }

        // Confirmación a la clínica: va por fuera de la transacción — si el
        // correo de la clínica falla, la solicitud ya registrada se conserva.
        $correoClinica = $solicitud->correoNotificacion();

        if ($correoClinica && self::correoValido($correoClinica)) {
            try {
                Mail::to($correoClinica)->send(new SolicitudRecibida($solicitud));
            } catch (Throwable $exception) {
                Log::warning('No se pudo enviar la confirmación de recepción a la clínica', [
                    'solicitud_id' => $solicitud->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return response()->json(['data' => $solicitud, 'message' => 'Solicitud enviada correctamente'], 201);
    }

    private function notificarUsuarios(string $titulo, string $mensaje, string $tipo = 'info', ?string $link = null): void
    {
        $usuarios = User::permission('clinicas.view')->where('is_active', true)->get();

        $now = now();
        $rows = $usuarios->map(fn (User $u) => [
            'user_id' => $u->id,
            'type' => $tipo,
            'title' => $titulo,
            'message' => $mensaje,
            'link' => $link,
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows) {
            Notification::insert($rows);
        }
    }

    /**
     * Referencias aún pendientes con sus notas de evolución — el listado
     * del módulo "Evolución" del portal público. Con sesión de clínica se
     * devuelven todas las de esa clínica; sin sesión (portal público) hay
     * que buscar primero: el acceso es por el ID único de la referencia
     * (ID000865 → 865) o la cédula exacta del paciente — nunca se lista
     * todo sin un dato que identifique al paciente.
     */
    public function evoluciones(Request $request): JsonResponse
    {
        $clinicaId = $this->getClinicaId($request);
        $buscar = trim((string) $request->query('buscar', ''));

        if (! $clinicaId && strlen($buscar) < 3) {
            return response()->json(['data' => []]);
        }

        $query = SolicitudReferencia::whereIn('estado', ['pendiente', 'pendiente_cama', 'en_espera'])
            ->with([
                'evoluciones',
                'diagnosticos',
                'clinica:id,nombre',
                'adjuntos:id,solicitud_referencia_id,nombre_original,mime_type,tamano',
            ])
            ->orderBy('created_at', 'desc');

        if ($clinicaId) {
            $query->where('clinica_id', $clinicaId);
        }

        if ($buscar !== '') {
            $digitos = preg_replace('/\D/', '', $buscar);
            $query->where(function ($q) use ($buscar, $digitos, $clinicaId): void {
                // Sin sesión la cédula debe ser exacta — un LIKE parcial
                // dejaría adivinar documentos ajenos por fuerza bruta.
                if ($clinicaId) {
                    $q->where('numero_documento', 'like', '%'.$buscar.'%');
                } else {
                    $q->where('numero_documento', $buscar);
                }
                if ($digitos !== '') {
                    $q->orWhere('id', (int) $digitos);
                }
            });
        }

        return response()->json(['data' => $query->limit(25)->get()]);
    }

    /**
     * Nota de evolución diaria que la clínica registra sobre un paciente
     * cuya referencia sigue pendiente. Solo se permite mientras la
     * solicitud no haya terminado (completada o negada) y queda también en
     * la línea de tiempo interna como evento "evolucion". El acceso
     * público se respalda en que solo se llega al registro conociendo el
     * ID de la referencia o la cédula del paciente.
     */
    public function agregarEvolucion(Request $request, int $id): JsonResponse
    {
        $clinicaId = $this->getClinicaId($request);

        $validated = $request->validate([
            'nota' => ['required_without:soportes', 'nullable', 'string', 'max:2000'],
            'soportes' => ['required_without:nota', 'nullable', 'array', 'max:5'],
            'soportes.*' => ['file', 'mimes:pdf,jpg,jpeg,png,gif,webp,bmp,tiff,svg,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar', 'max:10240'],
        ]);

        $solicitud = SolicitudReferencia::whereIn('estado', ['pendiente', 'pendiente_cama', 'en_espera'])
            ->when($clinicaId, fn ($q) => $q->where('clinica_id', $clinicaId))
            ->findOrFail($id);

        $soportes = $validated['soportes'] ?? [];
        $nota = trim((string) ($validated['nota'] ?? ''));

        if ($nota === '') {
            $nota = count($soportes) === 1
                ? 'La clínica adjuntó un soporte clínico.'
                : 'La clínica adjuntó '.count($soportes).' soportes clínicos.';
        }

        // La evolución puede llevar la historia clínica como archivo: se
        // guarda como soporte de la solicitud, igual que los adjuntos que
        // vienen en el formulario de remisión.
        $evolucion = $solicitud->evoluciones()->create(['nota' => $nota]);

        foreach ($soportes as $soporte) {
            $solicitud->adjuntos()->create([
                'solicitud_evolucion_id' => $evolucion->id,
                'nombre_original' => $soporte->getClientOriginalName(),
                'ruta' => $soporte->store("solicitudes-referencia/{$solicitud->id}", 'local'),
                'mime_type' => $soporte->getMimeType(),
                'tamano' => $soporte->getSize(),
            ]);
        }

        SolicitudReferenciaEvento::create([
            'solicitud_referencia_id' => $solicitud->id,
            'tipo' => 'evolucion',
            'titulo' => 'Evolución del paciente',
            'descripcion' => $evolucion->nota.(count($soportes) ? ' (+'.count($soportes).' soporte(s))' : ''),
        ]);

        // Aviso por correo al equipo interno — mismos destinatarios que la
        // notificación de nueva solicitud. Si el correo falla no se bloquea
        // el registro de la nota (ya quedó guardada arriba).
        try {
            $destinatarios = $this->destinatariosInternos();

            Mail::to($destinatarios)->send(new NuevaEvolucionPaciente($solicitud->load('clinica'), $evolucion));
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar el correo de evolución', [
                'solicitud_id' => $solicitud->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'data' => $evolucion,
            'solicitud' => $solicitud->load(['evoluciones', 'adjuntos:id,solicitud_referencia_id,nombre_original,mime_type,tamano']),
            'message' => 'Evolución registrada',
        ], 201);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $clinicaId = $this->getClinicaId($request);

        // Sin sesión, where('clinica_id', null) compila como "IS NULL" y
        // expondría las solicitudes creadas sin clínica registrada —
        // mismo bloqueo que index().
        if (! $clinicaId) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $solicitud = SolicitudReferencia::where('clinica_id', $clinicaId)
            ->with(['adjuntos', 'eventos', 'diagnosticos'])
            ->findOrFail($id);

        return response()->json(['data' => $solicitud]);
    }

    public function descargarAdjunto(int $solicitudId, int $adjuntoId, Request $request): BinaryFileResponse
    {
        $clinicaId = $this->getClinicaId($request);

        if (! $clinicaId) {
            abort(401);
        }

        $solicitud = SolicitudReferencia::where('clinica_id', $clinicaId)->findOrFail($solicitudId);

        $adjunto = SolicitudReferenciaAdjunto::where('solicitud_referencia_id', $solicitud->id)
            ->findOrFail($adjuntoId);

        $path = storage_path('app/private/'.$adjunto->ruta);

        if (! file_exists($path)) {
            abort(404, 'Archivo no encontrado');
        }

        return response()->file($path, [
            'Content-Type' => $adjunto->mime_type,
            'Content-Disposition' => 'inline; filename="'.$adjunto->nombre_original.'"',
        ]);
    }
}
