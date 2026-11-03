<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SolicitudEvolucionClinica;
use App\Models\Notification;
use App\Models\SolicitudReferencia;
use App\Models\SolicitudReferenciaAdjunto;
use App\Models\SolicitudReferenciaEvento;
use App\Models\User;
use App\Services\SqlQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SolicitudReferenciaController extends Controller
{
    /** Solo lo que sigue en curso — lo ya resuelto (completado/negado) vive en historico(). */
    public function index(): JsonResponse
    {
        abort_unless(request()->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        $solicitudes = SolicitudReferencia::with(['clinica', 'adjuntos', 'diagnosticos', 'eventos', 'evoluciones.adjuntos'])
            ->whereIn('estado', ['pendiente', 'pendiente_cama', 'en_espera'])
            ->orderByRaw("FIELD(estado, 'pendiente', 'pendiente_cama', 'en_espera')")
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $solicitudes]);
    }

    /** Histórico de solicitudes ya resueltas (completadas o negadas). */
    public function historico(): JsonResponse
    {
        abort_unless(request()->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        $solicitudes = SolicitudReferencia::with(['clinica', 'adjuntos', 'diagnosticos', 'eventos', 'evoluciones.adjuntos'])
            ->whereIn('estado', ['completado', 'negado'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json(['data' => $solicitudes]);
    }

    public function show(SolicitudReferencia $solicitud): JsonResponse
    {
        abort_unless(request()->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        return response()->json(['data' => $solicitud->load(['clinica', 'diagnosticos', 'eventos'])]);
    }

    public function aceptar(Request $request, SolicitudReferencia $solicitud): JsonResponse
    {
        abort_unless($request->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        if (! in_array($solicitud->estado, ['pendiente', 'pendiente_cama'], true)) {
            return response()->json(['message' => 'Solo se pueden aceptar solicitudes nuevas o pendientes de cama.'], 422);
        }

        $sinDisponibilidad = $request->boolean('sin_disponibilidad');

        $validated = $request->validate([
            'hora_respuesta' => ['required', 'date_format:H:i'],
            'nombre_quien_responde' => ['required', 'string', 'max:200'],
            'sin_disponibilidad' => ['nullable', 'boolean'],
            // Código real que Gomedisys generó al registrar al paciente allá
            // (ya no se inventa acá — ver flujo: primero se registra en
            // Gomedisys, luego se acepta en la app con ese código). Solo se
            // exige cuando sí hay cupo disponible.
            'codigo_aceptacion' => ['nullable', Rule::requiredIf(fn () => ! $sinDisponibilidad), 'string', 'max:20', Rule::unique('solicitudes_referencia', 'codigo_aceptacion')->ignore($solicitud->id)],
            'hora_llegada' => ['nullable', Rule::requiredIf(fn () => ! $sinDisponibilidad), 'date_format:H:i'],
            'lugar_llegada' => ['nullable', Rule::requiredIf(fn () => ! $sinDisponibilidad), 'string', 'max:150'],
            'sede' => ['nullable', Rule::requiredIf(fn () => ! $sinDisponibilidad), Rule::in(['URGENCIAS', 'CENTRAL DE ESPECIALISTAS', 'CAC SANTA BARBARA'])],
            // EPS confirmada al momento de la respuesta — el operador la
            // valida/corrige y queda guardada en la solicitud.
            'eps' => ['required', 'string', 'max:120'],
            'observaciones_respuesta' => ['nullable', 'string'],
            'soportes' => ['nullable', 'array', 'max:5'],
            'soportes.*' => ['file', 'mimes:pdf,jpg,jpeg,png,gif,webp,bmp,tiff,svg,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar', 'max:10240'],
        ]);

        $this->guardarSoportes($solicitud, $validated['soportes'] ?? []);

        if ($sinDisponibilidad) {
            // Aceptada en espera de cupo: queda "pendiente" hasta que haya
            // disponibilidad — entonces se envía el código y pasa a en_espera.
            $solicitud->update([
                'estado' => 'pendiente_cama',
                'eps' => $validated['eps'],
                'hora_respuesta' => $validated['hora_respuesta'],
                'nombre_quien_responde' => $validated['nombre_quien_responde'],
                'observaciones_respuesta' => $validated['observaciones_respuesta'] ?? null,
            ]);

            $solicitud->load('clinica');

            SolicitudReferenciaEvento::create([
                'solicitud_referencia_id' => $solicitud->id,
                'tipo' => 'pendiente_cama',
                'titulo' => 'Aceptada — en espera de cupo',
                'descripcion' => 'La solicitud fue aceptada y quedó en espera de cupo; el código se enviará cuando haya disponibilidad.',
            ]);

            if ($solicitud->correoNotificacion()) {
                Mail::send(
                    'emails.solicitud-aceptada-sin-cama',
                    ['solicitud' => $solicitud],
                    function ($message) use ($solicitud) {
                        $message->to($solicitud->correoNotificacion())
                            ->replyTo(config('mail.referencia_interna'), config('mail.referencia_nombre'))
                            ->subject("Solicitud de referencia ACEPTADA - {$solicitud->primer_nombre} {$solicitud->primer_apellido}");
                    }
                );
            }

            $this->notificarClinica(
                $solicitud,
                'Solicitud aceptada',
                "La solicitud de {$solicitud->primer_nombre} {$solicitud->primer_apellido} fue aceptada y quedó en espera de cupo. Le estaremos enviando el código cuando haya disponibilidad.",
                'success',
            );

            return response()->json(['data' => $solicitud, 'message' => 'Solicitud aceptada: en espera de cupo']);
        }

        // Aceptar y marcar en espera de llegada son un solo paso — no existe
        // un estado "aceptado" intermedio.
        $solicitud->update([
            'estado' => 'en_espera',
            'eps' => $validated['eps'],
            'codigo_aceptacion' => $validated['codigo_aceptacion'],
            'hora_respuesta' => $validated['hora_respuesta'],
            'nombre_quien_responde' => $validated['nombre_quien_responde'],
            'hora_llegada' => $validated['hora_llegada'],
            'lugar_llegada' => $validated['lugar_llegada'],
            'sede' => $validated['sede'] ?? null,
            'observaciones_respuesta' => $validated['observaciones_respuesta'] ?? null,
        ]);

        $solicitud->load('clinica');

        SolicitudReferenciaEvento::create([
            'solicitud_referencia_id' => $solicitud->id,
            'tipo' => 'en_espera',
            'titulo' => 'Paciente en espera',
            'descripcion' => 'La solicitud fue aceptada y el paciente está en espera de llegada.',
        ]);

        if ($solicitud->correoNotificacion()) {
            Mail::send(
                'emails.solicitud-en-espera',
                ['solicitud' => $solicitud],
                function ($message) use ($solicitud) {
                    $message->to($solicitud->correoNotificacion())
                        ->replyTo(config('mail.referencia_interna'), config('mail.referencia_nombre'))
                        ->subject("Solicitud de referencia ACEPTADA - {$solicitud->primer_nombre} {$solicitud->primer_apellido}");
                }
            );
        }

        $this->notificarClinica(
            $solicitud,
            'Solicitud aceptada',
            "La solicitud de {$solicitud->primer_nombre} {$solicitud->primer_apellido} fue aceptada y quedó en espera de llegada. Código: {$solicitud->codigo_aceptacion}.",
            'success',
        );

        return response()->json(['data' => $solicitud, 'message' => 'Solicitud aceptada: el paciente quedó en espera de llegada']);
    }

    /**
     * El personal de referencia le pide a la clínica, por correo, la
     * evolución actual del paciente. El correo lleva un enlace directo al
     * módulo público de Evolución con la referencia ya cargada. Solo aplica
     * a solicitudes Pendientes (en espera de cupo).
     */
    public function solicitarEvolucion(Request $request, SolicitudReferencia $solicitud): JsonResponse
    {
        abort_unless($request->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        if ($solicitud->estado !== 'pendiente_cama') {
            return response()->json(['message' => 'Solo se puede pedir evolución de solicitudes en estado Pendiente (en espera de cupo).'], 422);
        }

        $solicitud->load('clinica');
        $correo = $solicitud->correoNotificacion();

        if (! $correo) {
            return response()->json(['message' => 'La clínica de esta solicitud no tiene un correo registrado.'], 422);
        }

        try {
            Mail::to($correo)->send(new SolicitudEvolucionClinica($solicitud));
        } catch (Throwable $e) {
            Log::error('No se pudo enviar la solicitud de evolución', [
                'solicitud_id' => $solicitud->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'No se pudo enviar el correo a la clínica. Intente de nuevo en unos minutos.'], 503);
        }

        SolicitudReferenciaEvento::create([
            'solicitud_referencia_id' => $solicitud->id,
            'tipo' => 'evolucion_solicitada',
            'titulo' => 'Evolución solicitada',
            'descripcion' => "El equipo de referencia pidió por correo la evolución del paciente a {$correo}.",
        ]);

        $this->notificarClinica(
            $solicitud,
            'Evolución solicitada',
            "Referencia le pide registrar la evolución de {$solicitud->primer_nombre} {$solicitud->primer_apellido}.",
            'warning',
        );

        return response()->json(['data' => $solicitud->load('eventos'), 'message' => "Solicitud de evolución enviada a {$correo}"]);
    }

    public function negar(Request $request, SolicitudReferencia $solicitud): JsonResponse
    {
        abort_unless($request->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        if (in_array($solicitud->estado, ['negado', 'completado'], true)) {
            return response()->json(['message' => 'Esta solicitud ya no se puede negar.'], 422);
        }

        $validated = $request->validate([
            'hora_respuesta' => ['required', 'date_format:H:i'],
            'grupo_negacion' => ['required', 'string', 'in:no_red,poblacion_objeto'],
            'motivo_negacion' => ['required', 'string', 'max:250'],
            'eps' => ['required', 'string', 'max:120'],
            'nombre_quien_responde' => ['required', 'string', 'max:200'],
            'observaciones_respuesta' => ['nullable', 'string'],
            'soportes' => ['nullable', 'array', 'max:5'],
            'soportes.*' => ['file', 'mimes:pdf,jpg,jpeg,png,gif,webp,bmp,tiff,svg,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar', 'max:10240'],
        ]);

        $solicitud->update([
            'estado' => 'negado',
            'eps' => $validated['eps'],
            'hora_respuesta' => $validated['hora_respuesta'],
            'grupo_negacion' => $validated['grupo_negacion'],
            'motivo_negacion' => $validated['motivo_negacion'],
            'nombre_quien_responde' => $validated['nombre_quien_responde'],
            'observaciones_respuesta' => $validated['observaciones_respuesta'] ?? null,
        ]);

        $this->guardarSoportes($solicitud, $validated['soportes'] ?? []);

        $solicitud->load('clinica');

        if ($solicitud->correoNotificacion()) {
            Mail::send(
                'emails.solicitud-negada',
                ['solicitud' => $solicitud],
                function ($message) use ($solicitud) {
                    $message->to($solicitud->correoNotificacion())
                        ->replyTo(config('mail.referencia_interna'), config('mail.referencia_nombre'))
                        ->subject("Solicitud de referencia NEGADA - {$solicitud->primer_nombre} {$solicitud->primer_apellido}");
                }
            );
        }

        $this->notificarClinica(
            $solicitud,
            'Solicitud negada',
            "La solicitud de {$solicitud->primer_nombre} {$solicitud->primer_apellido} fue negada. Motivo: {$validated['motivo_negacion']}.",
            'error',
        );

        return response()->json(['data' => $solicitud, 'message' => 'Solicitud negada']);
    }

    /**
     * Cambio manual de estado con motivo — para corregir decisiones o mover
     * la solicitud cuando el flujo normal no aplica. La clínica queda
     * notificada por correo y por la campanita del portal.
     */
    public function cambiarEstado(Request $request, SolicitudReferencia $solicitud): JsonResponse
    {
        abort_unless($request->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        // "Nueva solicitud" queda fuera del cambio manual: las nuevas solo
        // avanzan por el flujo normal (aceptar/negar).
        $labels = [
            'pendiente_cama' => 'Pendiente (en espera de cupo)',
            'en_espera' => 'En espera',
            'completado' => 'Completada',
            'negado' => 'Negada',
        ];

        if ($solicitud->estado === 'pendiente') {
            return response()->json(['message' => 'Las solicitudes nuevas solo avanzan por el flujo de aceptar/negar.'], 422);
        }

        $validated = $request->validate([
            'estado' => ['required', 'string', Rule::in(array_keys($labels)), Rule::notIn([$solicitud->estado])],
            'motivo' => ['required', 'string', 'max:500'],
            'nombre_quien_responde' => ['required', 'string', 'max:200'],
            'hora_respuesta' => ['required', 'date_format:H:i'],
            // Solo se exigen cuando el destino es "en espera" (con código)
            'codigo_aceptacion' => ['nullable', Rule::requiredIf(fn () => $request->input('estado') === 'en_espera'), 'string', 'max:20', Rule::unique('solicitudes_referencia', 'codigo_aceptacion')->ignore($solicitud->id)],
            'hora_llegada' => ['nullable', Rule::requiredIf(fn () => $request->input('estado') === 'en_espera'), 'date_format:H:i'],
            'lugar_llegada' => ['nullable', Rule::requiredIf(fn () => $request->input('estado') === 'en_espera'), 'string', 'max:150'],
            'sede' => ['nullable', Rule::requiredIf(fn () => $request->input('estado') === 'en_espera' && ! $solicitud->sede), Rule::in(['URGENCIAS', 'CENTRAL DE ESPECIALISTAS', 'CAC SANTA BARBARA'])],
            // EPS confirmada al decidir — se exige al dejarla pendiente
            // (espera/cupo) o al negarla; al completar se conserva la que hay.
            'eps' => ['nullable', Rule::requiredIf(fn () => in_array($request->input('estado'), ['en_espera', 'pendiente_cama', 'negado'], true)), 'string', 'max:120'],
            'numero_ingreso' => ['nullable', Rule::requiredIf(fn () => $request->input('estado') === 'completado'), 'integer'],
        ]);

        $estadoAnterior = $solicitud->estado;

        $solicitud->update([
            'estado' => $validated['estado'],
            'hora_respuesta' => $validated['hora_respuesta'],
            'nombre_quien_responde' => $validated['nombre_quien_responde'],
            'observaciones_respuesta' => $validated['motivo'],
            'codigo_aceptacion' => $validated['estado'] === 'en_espera' ? $validated['codigo_aceptacion'] : $solicitud->codigo_aceptacion,
            'hora_llegada' => $validated['estado'] === 'en_espera' ? $validated['hora_llegada'] : $solicitud->hora_llegada,
            'lugar_llegada' => $validated['estado'] === 'en_espera' ? $validated['lugar_llegada'] : $solicitud->lugar_llegada,
            'sede' => $validated['estado'] === 'en_espera' ? ($validated['sede'] ?? $solicitud->sede) : $solicitud->sede,
            'eps' => $validated['eps'] ?? $solicitud->eps,
            'numero_ingreso' => $validated['estado'] === 'completado' ? $validated['numero_ingreso'] : $solicitud->numero_ingreso,
            'motivo_negacion' => $validated['estado'] === 'negado' ? $validated['motivo'] : $solicitud->motivo_negacion,
            'grupo_negacion' => $validated['estado'] === 'negado' && ! $solicitud->grupo_negacion ? 'no_red' : $solicitud->grupo_negacion,
        ]);

        $solicitud->load('clinica');

        SolicitudReferenciaEvento::create([
            'solicitud_referencia_id' => $solicitud->id,
            'tipo' => 'cambio_estado',
            'titulo' => "Estado cambiado: {$labels[$estadoAnterior]} → {$labels[$validated['estado']]}",
            'descripcion' => "Cambio manual. Motivo: {$validated['motivo']}",
        ]);

        if ($solicitud->correoNotificacion()) {
            Mail::send(
                'emails.solicitud-cambio-estado',
                [
                    'solicitud' => $solicitud,
                    'estadoNuevo' => $labels[$validated['estado']],
                    'motivo' => $validated['motivo'],
                ],
                function ($message) use ($solicitud) {
                    $message->to($solicitud->correoNotificacion())
                        ->replyTo(config('mail.referencia_interna'), config('mail.referencia_nombre'))
                        ->subject("Cambio de estado en su solicitud - {$solicitud->primer_nombre} {$solicitud->primer_apellido}");
                }
            );
        }

        $this->notificarClinica(
            $solicitud,
            'Cambio de estado',
            "La solicitud de {$solicitud->primer_nombre} {$solicitud->primer_apellido} pasó a «{$labels[$validated['estado']]}». Motivo: {$validated['motivo']}",
            'info',
        );

        $this->notificarPersonal(
            'Estado modificado manualmente',
            "{$solicitud->primer_nombre} {$solicitud->primer_apellido}: {$labels[$estadoAnterior]} → {$labels[$validated['estado']]}.",
            'warning',
        );

        return response()->json(['data' => $solicitud, 'message' => "Estado cambiado a «{$labels[$validated['estado']]}» y clínica notificada"]);
    }

    /**
     * Agrega una nota de seguimiento interna a la solicitud.
     *
     * Las notas quedan en la línea de tiempo del detalle y sirven para
     * dejar constancia del seguimiento (llamadas, confirmaciones de cupo,
     * contactos con la clínica, etc.). Son internas: no se notifican a la
     * clínica ni cambian el estado.
     */
    public function agregarNota(Request $request, SolicitudReferencia $solicitud): JsonResponse
    {
        $validated = $request->validate([
            'nota' => ['required', 'string', 'max:1000'],
        ]);

        $autor = $request->user()?->full_name ?? 'Sistema';

        SolicitudReferenciaEvento::create([
            'solicitud_referencia_id' => $solicitud->id,
            'tipo' => 'nota',
            'titulo' => "Nota de seguimiento — {$autor}",
            'descripcion' => $validated['nota'],
        ]);

        $solicitud->load(['clinica', 'diagnosticos', 'eventos']);

        return response()->json(['data' => $solicitud, 'message' => 'Nota de seguimiento agregada']);
    }

    /**
     * Deshace la aceptación y devuelve la solicitud a pendiente.
     *
     * Una vez negada, la solicitud queda definitiva a propósito (el personal
     * debe revisar bien antes de negar) — por eso 'negado' no está entre los
     * estados que se pueden devolver a pendiente.
     */
    public function pendiente(Request $request, SolicitudReferencia $solicitud): JsonResponse
    {
        abort_unless($request->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        if (! in_array($solicitud->estado, ['en_espera', 'pendiente_cama'], true)) {
            return response()->json(['message' => 'Esta solicitud no se puede devolver a pendiente.'], 422);
        }

        $validated = $request->validate([
            'observaciones_respuesta' => ['required', 'string'],
            'nombre_quien_responde' => ['required', 'string', 'max:200'],
        ]);

        $solicitud->update([
            'estado' => 'pendiente',
            'observaciones_respuesta' => $validated['observaciones_respuesta'],
            'nombre_quien_responde' => $validated['nombre_quien_responde'],
        ]);

        SolicitudReferenciaEvento::create([
            'solicitud_referencia_id' => $solicitud->id,
            'tipo' => 'revertido',
            'titulo' => 'Devuelta a pendiente',
            'descripcion' => "Se deshizo la decisión anterior. Motivo: {$validated['observaciones_respuesta']}",
        ]);

        $this->notificarClinica(
            $solicitud,
            'Solicitud en revisión nuevamente',
            "La solicitud de {$solicitud->primer_nombre} {$solicitud->primer_apellido} volvió a quedar pendiente de revisión.",
            'warning',
        );

        return response()->json(['data' => $solicitud, 'message' => 'Solicitud devuelta a pendiente']);
    }

    public function completado(Request $request, SolicitudReferencia $solicitud): JsonResponse
    {
        abort_unless($request->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        if ($solicitud->estado !== 'en_espera') {
            return response()->json(['message' => 'Solo se pueden completar solicitudes que están en espera de llegada.'], 422);
        }

        // numero_ingreso ya no es opcional: la única puerta a "completado" es
        // consultarIngreso() + confirmación del paciente real en Gomedisys —
        // ya no existe un botón "Completar" manual sin ese número verificado.
        $validated = $request->validate([
            'observaciones_respuesta' => ['nullable', 'string'],
            'numero_ingreso' => ['required', 'integer'],
        ]);

        $solicitud->update([
            'estado' => 'completado',
            'numero_ingreso' => $validated['numero_ingreso'],
            'observaciones_respuesta' => $validated['observaciones_respuesta'] ?? null,
        ]);

        $solicitud->load('clinica');

        SolicitudReferenciaEvento::create([
            'solicitud_referencia_id' => $solicitud->id,
            'tipo' => 'completado',
            'titulo' => 'Paciente atendido',
            'descripcion' => "El paciente llegó a la institución (ingreso Gomedisys #{$validated['numero_ingreso']}) y fue atendido.",
        ]);

        if ($solicitud->correoNotificacion()) {
            Mail::send(
                'emails.solicitud-completada',
                ['solicitud' => $solicitud],
                function ($message) use ($solicitud) {
                    $message->to($solicitud->correoNotificacion())
                        ->replyTo(config('mail.referencia_interna'), config('mail.referencia_nombre'))
                        ->subject("Solicitud de referencia COMPLETADA - {$solicitud->primer_nombre} {$solicitud->primer_apellido}");
                }
            );
        }

        $this->notificarClinica(
            $solicitud,
            'Paciente atendido',
            "La solicitud de {$solicitud->primer_nombre} {$solicitud->primer_apellido} fue completada: el paciente fue atendido.",
            'success',
        );

        $this->notificarPersonal(
            'Paciente atendido',
            "{$solicitud->primer_nombre} {$solicitud->primer_apellido} llegó a la institución y fue atendido.",
            'success',
        );

        return response()->json(['data' => $solicitud, 'message' => 'Solicitud completada']);
    }

    /**
     * El paciente en espera nunca llegó a la institución: la referencia se
     * cierra como negada y queda el motivo de no ingreso que elige el
     * operador (alta médica, fallecimiento, otra IPS, etc.). La clínica
     * queda notificada igual que con una negación normal.
     */
    public function noIngreso(Request $request, SolicitudReferencia $solicitud): JsonResponse
    {
        abort_unless($request->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        if ($solicitud->estado !== 'en_espera') {
            return response()->json(['message' => 'Solo se puede registrar no ingreso de solicitudes en espera de llegada.'], 422);
        }

        $validated = $request->validate([
            'motivo_no_ingreso' => ['required', 'string', 'max:250'],
        ]);

        $solicitud->update([
            'estado' => 'negado',
            'motivo_no_ingreso' => $validated['motivo_no_ingreso'],
            // También en motivo_negacion — así el detalle y el correo de
            // negada (que leen ese campo) muestran la razón sin cambios.
            'motivo_negacion' => 'No ingreso: '.$validated['motivo_no_ingreso'],
        ]);

        $solicitud->load('clinica');

        SolicitudReferenciaEvento::create([
            'solicitud_referencia_id' => $solicitud->id,
            'tipo' => 'negado',
            'titulo' => 'Paciente no llegó',
            'descripcion' => 'El paciente no se presentó a la institución. Motivo de no ingreso: '.$validated['motivo_no_ingreso'],
        ]);

        if ($solicitud->correoNotificacion()) {
            Mail::send(
                'emails.solicitud-negada',
                ['solicitud' => $solicitud],
                function ($message) use ($solicitud) {
                    $message->to($solicitud->correoNotificacion())
                        ->replyTo(config('mail.referencia_interna'), config('mail.referencia_nombre'))
                        ->subject("Solicitud de referencia NEGADA - {$solicitud->primer_nombre} {$solicitud->primer_apellido}");
                }
            );
        }

        $this->notificarClinica(
            $solicitud,
            'Paciente no llegó',
            "El paciente {$solicitud->primer_nombre} {$solicitud->primer_apellido} no se presentó a la institución. Motivo: {$validated['motivo_no_ingreso']}.",
            'error',
        );

        return response()->json(['data' => $solicitud, 'message' => 'Se registró que el paciente no llegó']);
    }

    /**
     * Consulta directo en la base de datos de Gomedisys si el paciente de
     * esta solicitud ya tiene un número de ingreso (o sea, si ya llegó y
     * fue admitido en la institución). Solo CONSULTA — no completa la
     * solicitud automáticamente. Devuelve los datos del paciente tal como
     * están en Gomedisys para que el personal los revise y confirme que es
     * la persona correcta antes de marcar la solicitud como completada
     * (ver completado()).
     */
    public function consultarIngreso(SolicitudReferencia $solicitud, SqlQueryService $sqlQueryService): JsonResponse
    {
        abort_unless(request()->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        if ($solicitud->estado !== 'en_espera') {
            return response()->json(['message' => 'Solo se puede consultar el ingreso de solicitudes en espera.'], 422);
        }

        try {
            $fila = $sqlQueryService->executeQueryFromFileFirst('consultaIngresoPaciente', [$solicitud->numero_documento]);
        } catch (Throwable $e) {
            Log::error('Error consultando ingreso en Gomedisys', ['solicitud_id' => $solicitud->id, 'error' => $e->getMessage()]);

            // El detalle ya quedó en el log — al usuario solo le llega un
            // mensaje limpio, sin exponer consultas SQL ni datos de la
            // conexión (host, base, usuario).
            return response()->json(['message' => 'No se pudo conectar con la base de datos de Gomedisys. Intenta de nuevo en unos minutos o avisa a soporte.'], 502);
        }

        if (! $fila || ! $fila->admission_number) {
            return response()->json(['ingreso' => false, 'message' => 'El paciente todavía no tiene número de ingreso en Gomedisys.']);
        }

        return response()->json([
            'ingreso' => true,
            'paciente' => [
                'fullname' => $fila->fullname,
                'identification_number' => $fila->identification_number,
                'document_type' => $fila->document_type,
                'sex' => $fila->sex,
                'birthdate' => $fila->birthdate,
                'admission_number' => $fila->admission_number,
                'admission_date' => $fila->admission_date,
            ],
            'message' => "Se encontró ingreso #{$fila->admission_number} en Gomedisys. Confirme que es el paciente correcto.",
        ]);
    }

    /**
     * Guarda como soportes de la solicitud los archivos que adjunta quien
     * responde (aceptar, negar) — quedan visibles en el detalle.
     *
     * @param  array<int, UploadedFile>  $soportes
     */
    private function guardarSoportes(SolicitudReferencia $solicitud, array $soportes): void
    {
        foreach ($soportes as $soporte) {
            $solicitud->adjuntos()->create([
                'nombre_original' => $soporte->getClientOriginalName(),
                'ruta' => $soporte->store("solicitudes-referencia/{$solicitud->id}", 'local'),
                'mime_type' => $soporte->getMimeType(),
                'tamano' => $soporte->getSize(),
            ]);
        }
    }

    private function notificarClinica(SolicitudReferencia $solicitud, string $titulo, string $mensaje, string $tipo = 'info'): void
    {
        if (! $solicitud->clinica_id) {
            return;
        }

        Notification::create([
            'clinica_id' => $solicitud->clinica_id,
            'type' => $tipo,
            'title' => $titulo,
            'message' => $mensaje,
            'link' => '/clinica/historial',
        ]);
    }

    /** Avisa a la campanita interna (todo el personal que puede ver solicitudes) que un paciente llegó. */
    private function notificarPersonal(string $titulo, string $mensaje, string $tipo = 'info'): void
    {
        $usuarios = User::permission('clinicas.view')->where('is_active', true)->get();

        $now = now();
        $rows = $usuarios->map(fn (User $u) => [
            'user_id' => $u->id,
            'type' => $tipo,
            'title' => $titulo,
            'message' => $mensaje,
            // Un paciente atendido pasa a 'completado', que ya no vive en
            // /solicitudes-referencia — el enlace debe llevar al histórico.
            'link' => '/historico',
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows) {
            Notification::insert($rows);
        }
    }

    public function descargarAdjunto(SolicitudReferencia $solicitud, SolicitudReferenciaAdjunto $adjunto): BinaryFileResponse
    {
        abort_unless(request()->user()->hasPermission('clinicas.view'), Response::HTTP_FORBIDDEN);

        if ($adjunto->solicitud_referencia_id !== $solicitud->id) {
            abort(404);
        }

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
