<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InboxMensaje;
use App\Models\Notification;
use App\Models\SolicitudReferencia;
use App\Models\SolicitudReferenciaEvento;
use App\Models\User;
use App\Services\GmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class InboxController extends Controller
{
    public function index(Request $request, GmailService $gmail): JsonResponse
    {
        $consulta = InboxMensaje::query()
            ->with(['solicitud:id,inbox_mensaje_id,estado,nombre_quien_responde,created_at'])
            ->latest('recibido_en');

        if ($request->filled('q')) {
            $texto = '%'.str_replace(['%', '_'], ['\%', '\_'], $request->string('q')).'%';
            $consulta->where(function ($w) use ($texto): void {
                $w->where('asunto', 'like', $texto)
                    ->orWhere('remitente', 'like', $texto)
                    ->orWhere('remitente_email', 'like', $texto);
            });
        }

        if ($request->input('filtro') === 'no_leidos') {
            $consulta->where('leido', false);
        }

        if ($request->filled('carpeta')) {
            $request->input('carpeta') === 'entrada'
                ? $consulta->whereNull('carpeta')
                : $consulta->where('carpeta', $request->string('carpeta'));
        }

        $pagina = $consulta->paginate(30);

        return response()->json([
            'data' => $pagina->items(),
            'meta' => [
                'total' => $pagina->total(),
                'pagina' => $pagina->currentPage(),
                'paginas' => $pagina->lastPage(),
                'no_leidos' => InboxMensaje::where('leido', false)->count(),
                'carpetas' => $this->carpetas(),
            ],
            'gmail' => [
                'configurado' => $gmail->configurado(),
                'buzon' => $gmail->buzon(),
                'ultima_sync' => $gmail->ultimaSync(),
                'ultimo_error' => $gmail->ultimoError(),
            ],
        ]);
    }

    /**
     * Carpetas de gestión con su conteo: las dos de siempre más las que el
     * personal haya creado al gestionar un correo.
     *
     * @return array<int, array{nombre: string, total: int}>
     */
    private function carpetas(): array
    {
        $conteos = InboxMensaje::whereNotNull('carpeta')
            ->selectRaw('carpeta, count(*) as total')
            ->groupBy('carpeta')
            ->pluck('total', 'carpeta');

        return collect(['Aceptaciones', 'Negaciones'])
            ->merge($conteos->keys())
            ->unique()
            ->map(fn (string $nombre): array => ['nombre' => $nombre, 'total' => (int) ($conteos[$nombre] ?? 0)])
            ->values()
            ->all();
    }

    private function nombreCarpeta(Request $request, string $defecto): string
    {
        $nombre = trim((string) $request->input('carpeta'));

        return $nombre !== '' ? mb_substr($nombre, 0, 60) : $defecto;
    }

    /**
     * Cambia de carpeta un correo ya gestionado (radicado o negado).
     */
    public function moverCarpeta(Request $request, InboxMensaje $mensaje): JsonResponse
    {
        $validated = $request->validate(['carpeta' => ['required', 'string', 'max:60']]);

        if (! $mensaje->gestion && ! SolicitudReferencia::where('inbox_mensaje_id', $mensaje->id)->exists()) {
            return response()->json(['message' => 'Solo se pueden mover a una carpeta los correos ya gestionados.'], 422);
        }

        $mensaje->update(['carpeta' => trim($validated['carpeta'])]);

        return response()->json([
            'data' => $mensaje->fresh(),
            'carpetas' => $this->carpetas(),
            'message' => "Correo movido a la carpeta «{$mensaje->carpeta}».",
        ]);
    }

    public function show(InboxMensaje $mensaje): JsonResponse
    {
        if (! $mensaje->leido) {
            $mensaje->update(['leido' => true]);
        }

        $mensaje->load(['solicitud:id,inbox_mensaje_id,estado,nombre_quien_responde,created_at']);

        return response()->json(['data' => $mensaje]);
    }

    public function noLeidos(): JsonResponse
    {
        return response()->json(['no_leidos' => InboxMensaje::where('leido', false)->count()]);
    }

    /**
     * Registra una solicitud de referencia a partir de un correo de la
     * bandeja: el personal diligencia el formulario a mano y la solicitud
     * queda en la cola de "Nueva solicitud", marcada con el correo origen.
     */
    public function crearSolicitud(Request $request, InboxMensaje $mensaje, GmailService $gmail): JsonResponse
    {
        if (SolicitudReferencia::where('inbox_mensaje_id', $mensaje->id)->exists()) {
            return response()->json(['message' => 'Este correo ya tiene una solicitud registrada.'], 422);
        }

        $validated = $request->validate([
            'tipo_documento' => ['required', 'string', 'max:5'],
            'numero_documento' => ['required', 'string', 'max:30'],
            'nombres' => ['required', 'string', 'max:80'],
            'apellidos' => ['required', 'string', 'max:80'],
            'edad' => ['required', 'integer', 'min:0', 'max:120'],
            'genero' => ['required', Rule::in(['M', 'F'])],
            'eps' => ['required', 'string', 'max:120'],
            'telefono_paciente' => ['nullable', 'string', 'max:15', 'regex:/^[0-9]*$/'],
            'municipio' => ['required', 'string', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:25'],
            'urgencia_vital' => ['nullable', 'boolean'],
            'historia_clinica' => ['required', 'string'],
            'diagnostico' => ['required', 'string', 'max:400'],
            'especialidad' => ['required', 'string', 'max:120'],
            'tipo_referencia' => ['required', Rule::in(['Referencia redonda', 'Referencia integral'])],
            'servicio' => ['required', 'string', 'max:60'],
            'aislamiento' => ['nullable', 'boolean'],
            'tipo_aislamiento' => ['nullable', Rule::in(['contacto', 'respiratorio', 'cohortizado', 'vectores'])],
            'gestante' => ['nullable', 'boolean'],
            'condicion_especial' => ['nullable', 'string', 'max:250'],
            'carpeta' => ['nullable', 'string', 'max:60'],
        ]);

        $recibido = $mensaje->recibido_en ? $mensaje->recibido_en->copy() : now();
        $quienResponde = $this->nombreUsuario($request);

        $carpeta = $this->nombreCarpeta($request, 'Aceptaciones');

        $solicitud = DB::transaction(function () use ($validated, $mensaje, $recibido, $quienResponde, $carpeta): SolicitudReferencia {
            $solicitud = SolicitudReferencia::create([
                'inbox_mensaje_id' => $mensaje->id,
                'origen' => 'correo',
                'fecha' => $recibido->toDateString(),
                'hora' => $recibido->format('H:i'),
                'primer_nombre' => $validated['nombres'],
                'primer_apellido' => $validated['apellidos'],
                'genero' => $validated['genero'],
                'edad' => $validated['edad'],
                'tipo_documento' => $validated['tipo_documento'],
                'numero_documento' => $validated['numero_documento'],
                'eps' => $validated['eps'],
                'diagnostico' => $validated['diagnostico'],
                'municipio_capita' => trim(preg_replace('/\s*,\s*/', ', ', $validated['municipio'])),
                'direccion_paciente' => $validated['direccion'] ?? null,
                'telefono_paciente' => $validated['telefono_paciente'] ?? null,
                'especialidad_requerida' => $validated['especialidad'],
                'tipo_referencia' => $validated['tipo_referencia'],
                'urgencia_vital' => $validated['urgencia_vital'] ?? false,
                'servicio_ubicacion_actual' => $validated['servicio'],
                'institucion_remitente' => mb_substr($mensaje->remitente ?? $mensaje->remitente_email ?? 'Correo', 0, 150),
                'correo_contacto' => mb_substr($mensaje->remitente_email ?? '', 0, 150),
                'quien_remitente' => mb_substr($mensaje->remitente ?? 'Correo', 0, 200),
                'requiere_aislamiento' => $validated['aislamiento'] ?? false,
                'tipo_aislamiento' => ($validated['aislamiento'] ?? false) ? ($validated['tipo_aislamiento'] ?? null) : null,
                'gestante' => $validated['gestante'] ?? false,
                'condicion_especial' => $validated['condicion_especial'] ?? null,
                'resumen_historia_clinica' => $validated['historia_clinica'],
                'via_contacto' => 'correo',
                'estado' => 'pendiente',
                'nombre_quien_responde' => $quienResponde ?: null,
            ]);

            $solicitud->diagnosticos()->create([
                'codigo_cie10' => '',
                'descripcion' => $validated['diagnostico'],
            ]);

            SolicitudReferenciaEvento::create([
                'solicitud_referencia_id' => $solicitud->id,
                'tipo' => 'creada',
                'titulo' => 'Solicitud registrada desde correo',
                'descripcion' => "Radicada manualmente desde la Bandeja: «{$mensaje->asunto}» de {$mensaje->remitente_email}.",
            ]);

            $paciente = trim("{$validated['nombres']} {$validated['apellidos']}");
            $usuarios = User::permission('clinicas.view')->where('is_active', true)->get();
            $now = now();
            $rows = $usuarios->map(fn (User $u) => [
                'user_id' => $u->id,
                'type' => 'warning',
                'title' => '📧 Solicitud de referencia por correo',
                'message' => "Desde la Bandeja se registró la solicitud de {$paciente} — {$validated['especialidad']}.",
                'link' => "/solicitudes-referencia?resaltar={$solicitud->id}",
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            if ($rows) {
                Notification::insert($rows);
            }

            $mensaje->update([
                'gestion' => 'radicada',
                'gestion_por' => $quienResponde,
                'gestion_at' => now(),
                'gestion_detalle' => "Solicitud #{$solicitud->id} registrada.",
                'carpeta' => $carpeta,
            ]);

            return $solicitud;
        });

        $this->anexarAdjuntosDelCorreo($solicitud, $mensaje, $gmail);

        return response()->json([
            'data' => $solicitud,
            'message' => "Solicitud #{$solicitud->id} registrada en la cola de Nueva solicitud.",
        ]);
    }

    /**
     * Pasa a la solicitud los adjuntos del correo gestionado — la bandeja
     * solo guarda la metadata, el binario se descarga de Gmail/IMAP acá.
     * Si la descarga falla la solicitud igual queda creada (los adjuntos
     * se pueden pedir a la clínica después).
     */
    private function anexarAdjuntosDelCorreo(SolicitudReferencia $solicitud, InboxMensaje $mensaje, GmailService $gmail): void
    {
        if (! $mensaje->tiene_adjuntos) {
            return;
        }

        foreach ($gmail->descargarAdjuntos($mensaje) as $adjunto) {
            $nombre = basename(str_replace('\\', '/', $adjunto['nombre'])) ?: 'adjunto';
            $ruta = "solicitudes-referencia/{$solicitud->id}/".Str::random(8).'_'.$nombre;

            try {
                Storage::disk('local')->put($ruta, $adjunto['contenido']);
            } catch (Throwable) {
                continue;
            }

            $solicitud->adjuntos()->create([
                'nombre_original' => $nombre,
                'ruta' => $ruta,
                'mime_type' => $adjunto['tipo'] ?: 'application/octet-stream',
                'tamano' => strlen($adjunto['contenido']),
            ]);
        }
    }

    /**
     * Marca el correo como negado: no genera solicitud porque el panel
     * de negación no recoge datos del paciente, pero deja constancia
     * de quién la negó y por qué para todo el equipo.
     */
    public function negar(Request $request, InboxMensaje $mensaje): JsonResponse
    {
        if ($mensaje->gestion || SolicitudReferencia::where('inbox_mensaje_id', $mensaje->id)->exists()) {
            return response()->json(['message' => 'Este correo ya fue gestionado.'], 422);
        }

        $validated = $request->validate([
            'grupo_negacion' => ['required', Rule::in(['no_red', 'poblacion_objeto'])],
            'motivo_negacion' => ['required', 'string', 'max:250'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'correos_extra' => ['nullable', 'array', 'max:10'],
            'correos_extra.*' => ['email', 'max:150'],
            'carpeta' => ['nullable', 'string', 'max:60'],
        ]);

        $destinatarios = array_values(array_unique(array_filter([
            $mensaje->remitente_email,
            ...($validated['correos_extra'] ?? []),
        ])));

        if ($destinatarios === []) {
            return response()->json(['message' => 'No hay un correo de destino para notificar la negación.'], 422);
        }

        $grupo = $validated['grupo_negacion'] === 'no_red' ? 'Paciente no RED' : 'Población objeto';
        $quienResponde = $this->nombreUsuario($request);
        $detalle = "Grupo: {$grupo} · Motivo: {$validated['motivo_negacion']}";
        if (! empty($validated['observaciones'])) {
            $detalle .= " · Obs: {$validated['observaciones']}";
        }

        try {
            Mail::send(
                'emails.inbox-negada',
                [
                    'mensaje' => $mensaje,
                    'motivo' => $validated['motivo_negacion'],
                    'observaciones' => $validated['observaciones'] ?? null,
                    'quienResponde' => $quienResponde,
                ],
                function ($message) use ($mensaje, $destinatarios) {
                    $to = $message->to($destinatarios);
                    if ($replyTo = config('mail.saliente_reply_to')) {
                        $to->replyTo($replyTo, config('mail.saliente_reply_to_nombre'));
                    }
                    $to->subject('Re: '.($mensaje->asunto ?: 'Solicitud de referencia'));
                }
            );
        } catch (Throwable) {
            return response()->json(['message' => 'No se pudo enviar el correo de negación. Intente de nuevo en unos minutos.'], 503);
        }

        $mensaje->update([
            'gestion' => 'negada',
            'gestion_por' => $quienResponde,
            'gestion_at' => now(),
            'gestion_detalle' => $detalle.' · Notificado a: '.implode(', ', $destinatarios),
            'carpeta' => $this->nombreCarpeta($request, 'Negaciones'),
        ]);

        return response()->json([
            'data' => $mensaje->fresh(),
            'message' => 'Negación enviada a '.implode(', ', $destinatarios),
        ]);
    }

    private function nombreUsuario(Request $request): ?string
    {
        $usuario = $request->user();
        if (! $usuario) {
            return null;
        }

        return trim(implode(' ', array_filter([
            $usuario->first_name,
            $usuario->middle_name,
            $usuario->last_name,
            $usuario->sur_name,
        ]))) ?: null;
    }

    public function sincronizar(GmailService $gmail): JsonResponse
    {
        if (! $gmail->configurado()) {
            return response()->json([
                'message' => 'Gmail no está configurado: falta el buzón o las credenciales en Configuración → Correo.',
            ], 422);
        }

        try {
            // Candado: la Bandeja dispara este endpoint periódicamente desde
            // cada sesión abierta; si ya hay un sync en curso se retorna sin
            // duplicar la conexión con Gmail.
            $r = Cache::lock('inbox-sincronizar', 120)->get(
                fn (): array => $gmail->sincronizar(limite: 100)
            ) ?? ['agregados' => 0, 'examinados' => 0];

            return response()->json([
                'data' => $r,
                'no_leidos' => InboxMensaje::where('leido', false)->count(),
            ]);
        } catch (Throwable $e) {
            $gmail->registrarError($e->getMessage());

            return response()->json(['message' => 'No se pudo sincronizar: '.$e->getMessage()], 500);
        }
    }
}
