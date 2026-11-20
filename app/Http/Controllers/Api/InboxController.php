<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinica;
use App\Models\InboxEstadistica;
use App\Models\InboxMensaje;
use App\Models\Notification;
use App\Models\SolicitudReferencia;
use App\Models\SolicitudReferenciaEvento;
use App\Models\User;
use App\Services\GmailService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class InboxController extends Controller
{
    /** Segundos durante los que una sincronización reciente evita que la Bandeja abierta vuelva a conectarse a Gmail. */
    private const SYNC_AUTOMATICA_FRESCA = 90;

    public function index(Request $request, GmailService $gmail): JsonResponse
    {
        // Solo las columnas que muestra la lista: el cuerpo y los adjuntos se
        // cargan aparte al abrir el correo (ver show()).
        $base = InboxMensaje::query();

        if ($request->filled('q')) {
            $texto = '%'.str_replace(['%', '_'], ['\%', '\_'], $request->string('q')).'%';
            $base->where(function ($w) use ($texto): void {
                $w->where('asunto', 'like', $texto)
                    ->orWhere('remitente', 'like', $texto)
                    ->orWhere('remitente_email', 'like', $texto);
            });
        }

        if ($request->input('filtro') === 'no_leidos') {
            $base->where('leido', false);
        }

        // La vista por defecto es Entrada: correos sin carpeta. Al gestionar
        // (radicar/negar/reenviar) el correo toma carpeta y sale de la lista
        // — solo se vuelve a ver dentro de esa carpeta.
        $carpeta = (string) $request->input('carpeta', 'entrada');
        if ($carpeta === 'entrada') {
            $base->whereNull('carpeta');
        } else {
            $base->where('carpeta', $carpeta);
        }

        // Vista de hilos: una fila por conversación con el mensaje más
        // reciente del hilo dentro del filtro, el total de mensajes del hilo
        // y cuántos quedan sin leer (contados en toda la bandeja).
        $claveHilo = function (string $tabla): string {
            $solo = InboxMensaje::query()->getModel()->getConnection()->getDriverName() === 'sqlite'
                ? "('solo-' || {$tabla}.id)"
                : "CONCAT('solo-', {$tabla}.id)";

            return "COALESCE(NULLIF({$tabla}.thread_id, ''), {$solo})";
        };

        $grupos = (clone $base)
            ->selectRaw('MAX(id) AS id')
            ->groupByRaw($claveHilo('inbox_mensajes'));

        $consulta = InboxMensaje::query()
            ->select([
                'id', 'gmail_id', 'remitente', 'remitente_email', 'para', 'asunto', 'snippet',
                'tiene_adjuntos', 'recibido_en', 'leido',
                'gestion', 'gestion_por', 'gestion_at', 'gestion_detalle', 'carpeta', 'etiquetas',
                'contestado_at', 'contestado_por',
            ])
            ->selectRaw("(SELECT COUNT(*) FROM inbox_mensajes h WHERE {$claveHilo('h')} = {$claveHilo('inbox_mensajes')}) AS hilo_total")
            ->selectRaw("(SELECT COUNT(*) FROM inbox_mensajes h WHERE h.leido = 0 AND {$claveHilo('h')} = {$claveHilo('inbox_mensajes')}) AS hilo_sin_leer")
            ->whereIn('id', $grupos)
            ->with(['solicitud:id,inbox_mensaje_id,estado,nombre_quien_responde,created_at'])
            ->latest('recibido_en');

        $pagina = $consulta->paginate(30);

        return response()->json([
            'data' => $pagina->items(),
            'meta' => [
                'total' => $pagina->total(),
                'pagina' => $pagina->currentPage(),
                'paginas' => $pagina->lastPage(),
                'no_leidos' => InboxMensaje::where('leido', false)->whereNull('carpeta')->count(),
                'carpetas' => $this->carpetas(),
                'etiquetas' => $this->etiquetas(),
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

        return collect(['Gestionados', 'Negados'])
            ->merge($conteos->keys()->diff(['Gestionados', 'Negados', 'Aceptaciones', 'Negaciones', 'Reenviados', 'Papelera']))
            ->unique()
            ->map(fn (string $nombre): array => ['nombre' => $nombre, 'total' => (int) ($conteos[$nombre] ?? 0)])
            ->values()
            ->all();
    }

    /**
     * Las etiquetas personalizadas que ya se usan en la bandeja, para
     * sugerirlas al marcar correos nuevos ("PACIENTES NO RED"…).
     *
     * @return array<int, string>
     */
    private function etiquetas(): array
    {
        return InboxMensaje::whereNotNull('etiquetas')
            ->orderByDesc('id')
            ->limit(2000)
            ->get(['etiquetas'])
            ->pluck('etiquetas')
            ->flatten()
            ->filter(fn ($e): bool => is_string($e) && ! InboxMensaje::esEtiquetaSistema($e))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Deja en el correo, dentro del propio buzón de Gmail, las etiquetas
     * personalizadas enviadas — el equivalente a marcarlo en Gmail.
     */
    public function etiquetasUpdate(Request $request, InboxMensaje $mensaje, GmailService $gmail): JsonResponse
    {
        $validated = $request->validate([
            'etiquetas' => ['present', 'array', 'max:10'],
            'etiquetas.*' => ['string', 'max:60'],
        ]);

        try {
            $finales = $gmail->ponerEtiquetas($mensaje, $validated['etiquetas']);
        } catch (Throwable $e) {
            Log::warning('Bandeja: no se pudieron aplicar las etiquetas.', ['mensaje' => $mensaje->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'No se pudieron aplicar las etiquetas en el buzón. Intente de nuevo.'], 503);
        }

        return response()->json([
            'data' => ['etiquetas' => $finales],
            'etiquetas' => $this->etiquetas(),
            'message' => 'Etiquetas actualizadas.',
        ]);
    }

    private function nombreCarpeta(Request $request, string $defecto): string
    {
        $nombre = trim((string) $request->input('carpeta'));

        return $nombre !== '' ? mb_substr($nombre, 0, 60) : $defecto;
    }

    /**
     * Elimina el correo definitivamente del aplicativo. El UID queda
     * registrado para que la sincronización no lo vuelva a traer desde
     * Gmail — el correo en sí sigue existiendo en el buzón.
     */
    public function papelera(InboxMensaje $mensaje, GmailService $gmail): JsonResponse
    {
        $gmail->registrarEliminado((string) $mensaje->gmail_id);
        InboxEstadistica::sumar('eliminados', $mensaje->recibido_en);
        $mensaje->delete();

        return response()->json([
            'data' => null,
            'carpetas' => $this->carpetas(),
            'message' => 'Correo eliminado definitivamente.',
        ]);
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

    public function show(InboxMensaje $mensaje, GmailService $gmail): JsonResponse
    {
        if (! $mensaje->leido) {
            $mensaje->update(['leido' => true]);
        }

        // Los correos viejos guardaron adjuntos con nombres genéricos y
        // tamaño 0: al abrirlos se repara la metadata desde el buzón una
        // sola vez (los nuevos ya traen el número de parte).
        if ($mensaje->tiene_adjuntos && ! array_key_exists('parte', $mensaje->adjuntos[0] ?? [])) {
            $gmail->refrescarAdjuntos($mensaje);
            $mensaje->refresh();
        }

        // Los correos guardados antes del soporte de etiquetas solo tienen
        // banderas IMAP: al abrirlos se traen sus etiquetas reales del buzón.
        if ($mensaje->necesitaEtiquetas()) {
            $gmail->refrescarEtiquetas($mensaje);
            $mensaje->refresh();
        }

        $mensaje->load(['solicitud:id,inbox_mensaje_id,estado,nombre_quien_responde,created_at']);

        return response()->json(['data' => $mensaje]);
    }

    /**
     * Los mensajes de la misma conversación (mismo thread_id), del más viejo
     * al más nuevo — la lista del lector para navegar el hilo completo.
     */
    public function hilo(InboxMensaje $mensaje): JsonResponse
    {
        if (! $mensaje->thread_id) {
            return response()->json(['data' => []]);
        }

        $mensajes = $mensaje->hilo()
            ->select([
                'id', 'remitente', 'remitente_email', 'asunto', 'snippet',
                'tiene_adjuntos', 'recibido_en', 'leido', 'gestion', 'carpeta',
            ])
            ->with(['solicitud:id,inbox_mensaje_id,estado,nombre_quien_responde,created_at'])
            ->orderBy('recibido_en')
            ->get();

        return response()->json(['data' => $mensajes]);
    }

    /**
     * Descarga un adjunto del correo directo del buzón — para revisar la
     * historia clínica sin tener que radicar primero la solicitud.
     */
    public function adjunto(InboxMensaje $mensaje, int $indice, GmailService $gmail): Response
    {
        $adjunto = $gmail->descargarAdjunto($mensaje, $indice);
        abort_unless($adjunto, 404, 'No se pudo descargar el adjunto: el correo ya no está en el buzón o el servicio de correo no responde.');

        return response($adjunto['contenido'], 200, [
            'Content-Type' => $adjunto['tipo'] ?: 'application/octet-stream',
            'Content-Disposition' => "inline; filename*=UTF-8''".rawurlencode((string) $adjunto['nombre']),
        ]);
    }

    public function noLeidos(): JsonResponse
    {
        return response()->json(['no_leidos' => InboxMensaje::where('leido', false)->whereNull('carpeta')->count()]);
    }

    /**
     * Aviso de posible duplicado al radicar un correo: busca solicitudes
     * activas con el mismo documento del paciente — la clínica a veces remite
     * el mismo caso por el portal y por correo. Es solo un aviso: el personal
     * decide si radica igual.
     */
    public function verificarDocumento(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'numero_documento' => ['required', 'string', 'max:30'],
            'tipo_documento' => ['nullable', 'string', 'max:5'],
        ]);

        $duplicadas = SolicitudReferencia::with('clinica:id,nombre')
            ->where('numero_documento', trim($validated['numero_documento']))
            ->when($validated['tipo_documento'] ?? null, fn ($q, string $tipo) => $q->where('tipo_documento', $tipo))
            ->whereIn('estado', ['pendiente', 'pendiente_cama', 'en_espera'])
            ->latest()
            ->limit(5)
            ->get(['id', 'clinica_id', 'primer_nombre', 'primer_apellido', 'estado', 'created_at'])
            ->map(fn (SolicitudReferencia $s): array => [
                'id' => $s->id,
                'paciente' => trim("{$s->primer_nombre} {$s->primer_apellido}"),
                'clinica' => $s->clinica?->nombre,
                'estado' => $s->estado,
                'creada' => $s->created_at?->toDateTimeString(),
            ]);

        return response()->json(['duplicadas' => $duplicadas]);
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
            'municipio' => ['nullable', 'string', 'max:120'],
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

        $carpeta = $this->nombreCarpeta($request, 'Gestionados');

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
                'municipio_capita' => isset($validated['municipio']) ? trim(preg_replace('/\s*,\s*/', ', ', $validated['municipio'])) : null,
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
            'eps' => ['required', 'string', 'max:120', $this->epsConContrato()],
            'grupo_negacion' => ['required', Rule::in(['no_red', 'poblacion_objeto'])],
            'motivo_negacion' => ['required', 'string', 'max:1500'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
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
        $detalle = "EPS: {$validated['eps']} · Grupo: {$grupo} · Motivo: {$validated['motivo_negacion']}";
        if (! empty($validated['observaciones'])) {
            $detalle .= " · Obs: {$validated['observaciones']}";
        }

        try {
            Mail::send(
                'emails.inbox-negada',
                [
                    'mensaje' => $mensaje,
                    'eps' => $validated['eps'],
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
                    $mensaje->responderEnHilo($message);
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
            'carpeta' => $this->nombreCarpeta($request, 'Negados'),
        ]);

        return response()->json([
            'data' => $mensaje->fresh(),
            'message' => 'Negación enviada a '.implode(', ', $destinatarios),
        ]);
    }

    /**
     * Reenvía a otro correo un mensaje que llegó a esta Bandeja por error:
     * el cuerpo y los adjuntos originales van al destinatario indicado y, si
     * se pide, el remitente recibe un aviso de que su correo fue redirigido.
     */
    public function reenviar(Request $request, InboxMensaje $mensaje, GmailService $gmail): JsonResponse
    {
        if ($mensaje->gestion || SolicitudReferencia::where('inbox_mensaje_id', $mensaje->id)->exists()) {
            return response()->json(['message' => 'Este correo ya fue gestionado.'], 422);
        }

        $validated = $request->validate([
            'para' => ['required', 'email', 'max:150'],
            'nota' => ['nullable', 'string', 'max:1500'],
            'avisar_remitente' => ['nullable', 'boolean'],
            'carpeta' => ['nullable', 'string', 'max:60'],
        ]);

        $para = trim($validated['para']);
        $avisar = (bool) ($validated['avisar_remitente'] ?? false) && filled($mensaje->remitente_email);
        $quienResponde = $this->nombreUsuario($request);

        try {
            Mail::send(
                'emails.inbox-reenvio',
                [
                    'mensaje' => $mensaje,
                    'nota' => $validated['nota'] ?? null,
                    'quienResponde' => $quienResponde,
                ],
                function ($message) use ($mensaje, $para, $gmail): void {
                    $message->to($para)->subject('Fwd: '.($mensaje->asunto ?: '(sin asunto)'));
                    if ($replyTo = config('mail.saliente_reply_to')) {
                        $message->replyTo($replyTo, config('mail.saliente_reply_to_nombre'));
                    }
                    // Los adjuntos no se guardan en la Bandeja: se descargan
                    // de Gmail/IMAP al momento de reenviar.
                    foreach ($gmail->descargarAdjuntos($mensaje) as $adjunto) {
                        $nombre = basename(str_replace('\\', '/', $adjunto['nombre'])) ?: 'adjunto';
                        $message->attachData($adjunto['contenido'], $nombre, ['mime' => $adjunto['tipo'] ?: 'application/octet-stream']);
                    }
                }
            );

            if ($avisar) {
                Mail::send(
                    'emails.inbox-redirigida',
                    ['mensaje' => $mensaje, 'quienResponde' => $quienResponde],
                    function ($message) use ($mensaje): void {
                        $message->to($mensaje->remitente_email)
                            ->subject('Re: '.($mensaje->asunto ?: 'Su correo'));
                        $mensaje->responderEnHilo($message);
                        if ($replyTo = config('mail.saliente_reply_to')) {
                            $message->replyTo($replyTo, config('mail.saliente_reply_to_nombre'));
                        }
                    }
                );
            }
        } catch (Throwable) {
            return response()->json(['message' => 'No se pudo reenviar el correo. Intente de nuevo en unos minutos.'], 503);
        }

        $detalle = "Reenviado a {$para}".($avisar ? ' · Aviso enviado al remitente' : '');
        $mensaje->update([
            'gestion' => 'reenviada',
            'gestion_por' => $quienResponde,
            'gestion_at' => now(),
            'gestion_detalle' => $detalle,
            'carpeta' => $this->nombreCarpeta($request, 'Gestionados'),
        ]);

        return response()->json([
            'data' => $mensaje->fresh(),
            'message' => "Correo reenviado a {$para}".($avisar ? ' y aviso enviado al remitente.' : '.'),
        ]);
    }

    /**
     * Responde al remitente en el mismo hilo del correo, sin tocar la
     * gestión: por directriz todo correo recibe contestación, haya o no
     * solicitud que radicar. Queda quién contestó y cuándo.
     */
    public function responder(Request $request, InboxMensaje $mensaje): JsonResponse
    {
        if (! filled($mensaje->remitente_email)) {
            return response()->json(['message' => 'Este correo no tiene una dirección de remitente a la que responder.'], 422);
        }

        $validated = $request->validate([
            'cuerpo' => ['required', 'string', 'max:4000'],
            'cc' => ['nullable', 'array', 'max:5'],
            'cc.*' => ['email', 'max:150'],
        ]);

        $quienResponde = $this->nombreUsuario($request);
        $copias = array_values(array_filter(array_map('trim', $validated['cc'] ?? [])));

        try {
            Mail::send(
                'emails.inbox-respuesta',
                [
                    'mensaje' => $mensaje,
                    'respuesta' => $validated['cuerpo'],
                    'quienResponde' => $quienResponde,
                ],
                function ($message) use ($mensaje, $copias): void {
                    $to = $message->to($mensaje->remitente_email);
                    if ($copias !== []) {
                        $to->cc($copias);
                    }
                    if ($replyTo = config('mail.saliente_reply_to')) {
                        $to->replyTo($replyTo, config('mail.saliente_reply_to_nombre'));
                    }
                    $to->subject('Re: '.($mensaje->asunto ?: 'Su correo'));
                    $mensaje->responderEnHilo($message);
                }
            );
        } catch (Throwable) {
            return response()->json(['message' => 'No se pudo enviar la respuesta. Intente de nuevo en unos minutos.'], 503);
        }

        if ($mensaje->contestado_at === null) {
            InboxEstadistica::sumar('contestados', $mensaje->recibido_en);
        }

        $mensaje->update([
            'contestado_at' => now(),
            'contestado_por' => $quienResponde,
            'contestado_detalle' => "Para: {$mensaje->remitente_email}"
                .($copias !== [] ? ' · CC: '.implode(', ', $copias) : '')
                ."\n".$validated['cuerpo'],
        ]);

        // Contestado = atendido: el hilo sale de la Entrada a «Contestados».
        // Solo se mueven los mensajes sin carpeta — un radicado o negado
        // conserva la suya. Al no tener gestión, la limpieza de medianoche
        // los borra igual que cualquier otro pendiente.
        $enEntrada = InboxMensaje::whereNull('carpeta');
        filled($mensaje->thread_id)
            ? $enEntrada->where('thread_id', $mensaje->thread_id)
            : $enEntrada->where('id', $mensaje->id);
        $enEntrada->update(['carpeta' => 'Contestados']);

        return response()->json([
            'data' => $mensaje->fresh(),
            'carpetas' => $this->carpetas(),
            'message' => 'Respuesta enviada a '.$mensaje->remitente_email.'.',
        ]);
    }

    /**
     * Las etiquetas que existen en el buzón de Gmail, unidas a las que ya
     * usan los correos guardados: el selector ofrece "Población objeto"…
     * aunque ningún correo de la base las tenga todavía.
     */
    public function etiquetasBuzon(GmailService $gmail): JsonResponse
    {
        return response()->json([
            'data' => collect($gmail->etiquetasDelBuzon())
                ->merge($this->etiquetas())
                ->filter(fn ($e): bool => is_string($e) && ! InboxMensaje::esEtiquetaSistema($e))
                ->unique()
                ->sort()
                ->values()
                ->all(),
        ]);
    }

    /**
     * Correos de las clínicas registradas, para sugerir el destino al
     * reenviar un correo que llegó a esta Bandeja por error.
     */
    public function correosClinicas(): JsonResponse
    {
        $clinicas = Clinica::whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('nombre')
            ->get(['nombre', 'email']);

        return response()->json(['data' => $clinicas]);
    }

    /**
     * La EPS debe traer su tipo de contrato: "NUEVA EPS" a secas no sirve,
     * tiene que ser "NUEVA EPS PGP" o "NUEVA EPS EVENTO" — igual que al
     * negar una solicitud desde el listado.
     */
    private function epsConContrato(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fail): void {
            if (in_array(mb_strtoupper(trim((string) $valor)), SolicitudReferencia::EPS_CON_CONTRATO, true)) {
                $fail('Seleccione la EPS con su tipo de contrato (por ejemplo NUEVA EPS PGP o NUEVA EPS EVENTO).');
            }
        };
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

    public function sincronizar(Request $request, GmailService $gmail): JsonResponse
    {
        if (! $gmail->configurado()) {
            return response()->json([
                'message' => 'Gmail no está configurado: falta el buzón o las credenciales en Configuración → Correo.',
            ], 422);
        }

        // El botón de sincronizar manda `auditar`; la Bandeja abierta llama sola cada minuto sin él.
        $manual = $request->boolean('auditar');
        $ultima = $gmail->ultimaSync();

        // Si el cron (u otra sesión) sincronizó hace poco no hace falta abrir otra conexión con Gmail.
        if (! $manual && $ultima && Carbon::parse($ultima)->gt(now()->subSeconds(self::SYNC_AUTOMATICA_FRESCA))) {
            return response()->json([
                'data' => ['agregados' => 0, 'examinados' => 0],
                'no_leidos' => InboxMensaje::where('leido', false)->whereNull('carpeta')->count(),
            ]);
        }

        try {
            // Candado: la Bandeja dispara este endpoint periódicamente desde
            // cada sesión abierta; si ya hay un sync en curso se retorna sin
            // duplicar la conexión con Gmail. Cada ejecución desde la web es
            // pequeña y acotada en tiempo para no dejar ocupado el servidor;
            // el grueso del trabajo lo hace el cron.
            $r = Cache::lock('inbox-sincronizar', 60)->get(
                fn (): array => $manual
                    ? $gmail->sincronizar(limite: 100, auditar: true, maxSegundos: 25)
                    : $gmail->sincronizar(limite: 20, maxSegundos: 10)
            ) ?? ['agregados' => 0, 'examinados' => 0];

            return response()->json([
                'data' => $r,
                'no_leidos' => InboxMensaje::where('leido', false)->whereNull('carpeta')->count(),
            ]);
        } catch (Throwable $e) {
            $gmail->registrarError($e->getMessage());

            return response()->json(['message' => 'No se pudo sincronizar: '.$e->getMessage()], 500);
        }
    }
}
