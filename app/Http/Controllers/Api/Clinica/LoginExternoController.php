<?php

namespace App\Http\Controllers\Api\Clinica;

use App\Http\Controllers\Controller;
use App\Mail\ClinicaRegistroConfirmacion;
use App\Mail\ClinicaRegistroNotificacionInterna;
use App\Models\Clinica;
use App\Models\ClinicaAuthToken;
use App\Models\ClinicaSession;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class LoginExternoController extends Controller
{
    /**
     * Autoregistro de una clínica externa. Queda pendiente de aprobación.
     */
    public function registro(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nit' => ['required', 'string', 'max:20', 'unique:clinicas,nit'],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'email_confirmacion' => ['required', 'email', 'same:email'],
            'telefono' => ['required', 'string', 'max:20'],
            'ciudad' => ['required', 'string', 'max:100'],
            'departamento' => ['required', 'string', 'max:100'],
            'direccion' => ['required', 'string', 'max:255'],
            'representante_legal' => ['required', 'string', 'max:255'],
            'cedula_representante' => ['required', 'string', 'max:20'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'especialidades' => ['nullable', 'array'],
            'especialidades.*' => ['string', 'max:100'],
            'logo_path' => ['nullable', 'string', 'max:255'],
        ], [
            'nit.unique' => 'Ya existe una clínica registrada con este NIT.',
            'email_confirmacion.same' => 'Los correos electrónicos no coinciden.',
        ]);

        $clinica = Clinica::create([
            'nit' => $validated['nit'],
            'razon_social' => $validated['razon_social'],
            'nombre' => $validated['nombre'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'],
            'ciudad' => $validated['ciudad'],
            'departamento' => $validated['departamento'],
            'direccion' => $validated['direccion'],
            'representante_legal' => $validated['representante_legal'],
            'cedula_representante' => $validated['cedula_representante'],
            'observaciones' => $validated['observaciones'] ?? null,
            'especialidades' => $validated['especialidades'] ?? null,
            'logo_path' => $validated['logo_path'] ?? null,
            'is_active' => false,
        ]);

        $radicado = 'RAD-'.str_pad((string) $clinica->id, 6, '0', STR_PAD_LEFT).'-'.date('Y');

        // Correo de confirmación a la clínica
        Mail::to($clinica->email)->send(new ClinicaRegistroConfirmacion($clinica));

        // Notificación interna al equipo de referencia (correo)
        $correoReferencia = config('mail.referencia_interna');
        Mail::to($correoReferencia)->send(new ClinicaRegistroNotificacionInterna($clinica));

        // Notificación en campana para usuarios con permiso clinicas.view
        $this->notificarUsuarios(
            titulo: 'Nueva solicitud de clínica',
            mensaje: "La institución \"{$clinica->nombre}\" (NIT: {$clinica->nit}) ha solicitado registro en el sistema.",
            tipo: 'info',
            link: '/clinicas',
        );

        return response()->json([
            'message' => 'Solicitud de registro recibida. Será notificado cuando sea aprobada.',
            'radicado' => $radicado,
        ], 201);
    }

    /**
     * Busca la clínica por NIT para mostrar su nombre antes de elegir método.
     */
    public function buscarClinica(Request $request): JsonResponse
    {
        $request->validate([
            'nit' => ['required', 'string', 'max:20'],
        ]);

        $nitNormalizado = preg_replace('/\D/', '', $request->nit);

        $clinica = Clinica::whereRaw("REGEXP_REPLACE(nit, '[^0-9]', '') = ?", [$nitNormalizado])
            ->first();

        if (! $clinica) {
            return response()->json([
                'message' => 'Clínica no encontrada o no autorizada para acceder al sistema.',
            ], 404);
        }

        if ($clinica->estado === 'pendiente') {
            return response()->json([
                'message' => 'Su clínica está pendiente de aceptación por el equipo de referencia. Le notificaremos cuando sea aprobada.',
            ], 403);
        }

        if ($clinica->estado === 'rechazada') {
            $mensaje = 'Clínica no autorizada para acceder al sistema.';

            if ($clinica->motivo_rechazo) {
                $mensaje .= " Motivo: {$clinica->motivo_rechazo}";
            }

            return response()->json(['message' => $mensaje], 403);
        }

        if (! $clinica->is_active) {
            return response()->json([
                'message' => 'Clínica no autorizada para acceder al sistema.',
            ], 403);
        }

        return response()->json([
            'data' => ['nombre' => $clinica->nombre],
        ]);
    }

    /**
     * Solicita un código de acceso: magic link por email o código OTP por SMS.
     */
    public function solicitarAcceso(Request $request): JsonResponse
    {
        $request->validate([
            'nit' => ['required', 'string', 'max:20'],
            'metodo' => ['required', 'in:email,sms'],
        ]);

        $nitNormalizado = preg_replace('/\D/', '', $request->nit);

        $clinica = Clinica::whereRaw("REGEXP_REPLACE(nit, '[^0-9]', '') = ?", [$nitNormalizado])
            ->first();

        if (! $clinica) {
            return response()->json([
                'message' => 'Clínica no encontrada o no autorizada para acceder al sistema.',
            ], 404);
        }

        if ($clinica->estado === 'pendiente') {
            return response()->json([
                'message' => 'Su clínica está pendiente de aceptación por el equipo de referencia.',
            ], 403);
        }

        if ($clinica->estado === 'rechazada' || ! $clinica->is_active) {
            return response()->json([
                'message' => 'Clínica no autorizada para acceder al sistema.',
            ], 403);
        }

        // Invalidar tokens anteriores no usados de esta clínica
        ClinicaAuthToken::where('clinica_id', $clinica->id)
            ->whereNull('used_at')
            ->delete();

        if ($request->metodo === 'email') {
            $this->enviarMagicLink($clinica);
        } else {
            $this->enviarOtpSms($clinica);
        }

        return response()->json(['message' => 'Código enviado exitosamente.']);
    }

    /**
     * Verifica el código OTP recibido por SMS.
     */
    public function verificarOtp(Request $request): JsonResponse
    {
        $request->validate([
            'nit' => ['required', 'string'],
            'codigo' => ['required', 'string', 'size:6'],
        ]);

        $nitNormalizado = preg_replace('/\D/', '', $request->nit);

        $clinica = Clinica::whereRaw("REGEXP_REPLACE(nit, '[^0-9]', '') = ?", [$nitNormalizado])
            ->first();

        if (! $clinica) {
            return response()->json(['message' => 'Clínica no encontrada o no autorizada para acceder al sistema.'], 404);
        }

        if ($clinica->estado === 'pendiente') {
            return response()->json([
                'message' => 'Su clínica está pendiente de aceptación por el equipo de referencia.',
            ], 403);
        }

        if ($clinica->estado === 'rechazada' || ! $clinica->is_active) {
            return response()->json([
                'message' => 'Clínica no autorizada para acceder al sistema.',
            ], 403);
        }

        $tokenRecord = ClinicaAuthToken::where('clinica_id', $clinica->id)
            ->where('tipo', 'otp')
            ->where('codigo_otp', $request->codigo)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $tokenRecord) {
            return response()->json([
                'message' => 'Código incorrecto o expirado. Solicite uno nuevo.',
            ], 422);
        }

        $tokenRecord->update(['used_at' => now()]);

        $sessionToken = $this->createSessionToken($clinica);

        return response()->json([
            'data' => $this->formatClinica($clinica),
            'token' => $sessionToken,
        ]);
    }

    /**
     * Verifica el token del magic link recibido por correo.
     */
    public function verificarMagicLink(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $tokenRecord = ClinicaAuthToken::where('tipo', 'magic_link')
            ->where('token', hash('sha256', $request->token))
            ->where('expires_at', '>', now())
            ->first();

        if (! $tokenRecord) {
            return response()->json([
                'message' => 'El enlace ha expirado. Solicite uno nuevo.',
            ], 422);
        }

        $clinica = $tokenRecord->clinica;

        if (! $clinica || ! $clinica->is_active) {
            return response()->json(['message' => 'Clínica no autorizada.'], 403);
        }

        $sessionToken = $this->createSessionToken($clinica);

        return response()->json([
            'data' => $this->formatClinica($clinica),
            'token' => $sessionToken,
        ]);
    }

    /**
     * Retorna los datos de la clínica autenticada mediante token.
     */
    public function clinicaActual(Request $request): JsonResponse
    {
        $clinica = $this->clinicaFromToken($request);

        if (! $clinica) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        return response()->json(['data' => $this->formatClinica($clinica)]);
    }

    /**
     * Cierra la sesión de la clínica (invalida el token).
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $this->extractToken($request);

        if ($token) {
            ClinicaSession::where('token', hash('sha256', $token))->delete();
        }

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    // ── Helpers privados ─────────────────────────────────────────────────────

    private function createSessionToken(Clinica $clinica): string
    {
        $plain = Str::random(64);

        ClinicaSession::create([
            'clinica_id' => $clinica->id,
            'token' => hash('sha256', $plain),
            'expires_at' => now()->addDays(30),
        ]);

        return $plain;
    }

    private function extractToken(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        if ($bearer) {
            return $bearer;
        }

        return $request->header('X-Clinica-Token');
    }

    public function clinicaFromToken(Request $request): ?Clinica
    {
        $plain = $this->extractToken($request);

        if (! $plain) {
            return null;
        }

        $session = ClinicaSession::where('token', hash('sha256', $plain))
            ->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $session) {
            return null;
        }

        $clinica = $session->clinica;

        if (! $clinica || ! $clinica->is_active) {
            $session->delete();

            return null;
        }

        return $clinica;
    }

    private function enviarMagicLink(Clinica $clinica): void
    {
        $tokenPlano = Str::random(64);

        ClinicaAuthToken::create([
            'clinica_id' => $clinica->id,
            'tipo' => 'magic_link',
            'token' => hash('sha256', $tokenPlano),
            'expires_at' => now()->endOfDay(),
        ]);

        $url = url("/login-externo/magic/{$tokenPlano}");

        Mail::send(
            'emails.clinica-magic-link',
            ['clinica' => $clinica, 'url' => $url],
            function ($message) use ($clinica) {
                $message->to($clinica->email)
                    ->subject('🔐 Acceso al Sistema de Referencia — Santa Bárbara');
            }
        );
    }

    private function enviarOtpSms(Clinica $clinica): void
    {
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        ClinicaAuthToken::create([
            'clinica_id' => $clinica->id,
            'tipo' => 'otp',
            'codigo_otp' => $codigo,
            'expires_at' => now()->addMinutes(10),
        ]);

        // TODO: integrar proveedor SMS (Twilio, AWS SNS, etc.)
        // SmsService::send($clinica->telefono, "Su código de acceso es: {$codigo}. Válido 10 minutos.");
        \Log::info("OTP para clínica {$clinica->nit}: {$codigo}");
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

    private function formatClinica(Clinica $clinica): array
    {
        return [
            'id' => $clinica->id,
            'nit' => $clinica->nit,
            'nombre' => $clinica->nombre,
            'email' => $clinica->email,
            'telefono' => $clinica->telefono,
            'ciudad' => $clinica->ciudad,
            'is_active' => $clinica->is_active,
        ];
    }
}
