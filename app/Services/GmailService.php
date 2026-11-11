<?php

namespace App\Services;

use App\Models\InboxMensaje;
use App\Models\Setting;
use DirectoryTree\ImapEngine\Mailbox;
use DirectoryTree\ImapEngine\MessageInterface as ImapMessage;
use DirectoryTree\ImapEngine\MessageQueryInterface;
use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\MessagePart;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GmailService
{
    private const SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';

    private const IMAP_HOST = 'imap.gmail.com';

    private const IMAP_PORT = 993;

    public function configurado(): bool
    {
        if (! filled(Setting::get('gmail_buzon'))) {
            return false;
        }

        return $this->esImap()
            ? filled(Setting::get('gmail_password'))
            : filled(Setting::get('gmail_credenciales'));
    }

    public function buzon(): ?string
    {
        return Setting::get('gmail_buzon');
    }

    public function ultimaSync(): ?string
    {
        return Cache::get('inbox_ultima_sync');
    }

    public function ultimoError(): ?string
    {
        return Cache::get('inbox_ultimo_error');
    }

    /**
     * Sincroniza el buzón: trae los mensajes que aún no están en la tabla.
     * `$completo` recorre todo el historial; si no, se detiene al encontrar
     * un mensaje ya sincronizado (los listados vienen de más nuevo a más viejo).
     *
     * @return array{agregados: int, examinados: int}
     */
    public function sincronizar(bool $completo = false, int $limite = 500): array
    {
        if (! $this->configurado()) {
            throw new RuntimeException('Gmail no está configurado: falta el buzón o las credenciales.');
        }

        return $this->esImap()
            ? $this->sincronizarImap($completo, $limite)
            : $this->sincronizarGoogle($completo, $limite);
    }

    public function esImap(): bool
    {
        return Setting::get('gmail_modo', 'imap') !== 'servicio';
    }

    /**
     * Descarga el contenido de los adjuntos de un correo del buzón — en la
     * tabla solo vive la metadata (nombre/tipo/tamaño), el binario se pide
     * a Gmail o IMAP cuando hace falta (ej. al radicar la solicitud).
     *
     * @return array<int, array{nombre: string, tipo: string, contenido: string}>
     */
    public function descargarAdjuntos(InboxMensaje $mensaje): array
    {
        if (! $this->configurado()) {
            return [];
        }

        try {
            return $this->esImap()
                ? $this->adjuntosImap($mensaje)
                : $this->adjuntosGoogle($mensaje);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array{nombre: string, tipo: string, contenido: string}>
     */
    private function adjuntosGoogle(InboxMensaje $mensaje): array
    {
        $gmail = $this->gmail();
        $payload = $gmail->users_messages->get('me', $mensaje->gmail_id, ['format' => 'full'])->getPayload();
        $adjuntos = [];

        $camina = function (MessagePart $p) use (&$camina, &$adjuntos, $gmail, $mensaje): void {
            $attachmentId = $p->getBody()?->getAttachmentId();
            if ($p->getFilename() && $attachmentId) {
                $data = $gmail->users_messages_attachments
                    ->get('me', $mensaje->gmail_id, $attachmentId)
                    ->getData();
                $adjuntos[] = [
                    'nombre' => $p->getFilename(),
                    'tipo' => (string) $p->getMimeType(),
                    'contenido' => (string) base64_decode(strtr((string) $data, '-_', '+/'), true),
                ];
            }

            foreach ($p->getParts() ?? [] as $hija) {
                $camina($hija);
            }
        };

        if ($payload) {
            $camina($payload);
        }

        return $adjuntos;
    }

    /**
     * @return array<int, array{nombre: string, tipo: string, contenido: string}>
     */
    private function adjuntosImap(InboxMensaje $mensaje): array
    {
        $uid = (int) str_replace('imap-', '', (string) $mensaje->gmail_id);
        $remoto = $this->imapMailbox()->inbox()->messages()->find($uid);

        if (! $remoto) {
            return [];
        }

        // attachments(true) pereza-descarga cada parte del mensaje — con
        // attachments() solo se lee la estructura ya traída y sale vacío.
        return array_map(fn ($adjunto): array => [
            'nombre' => $adjunto->filename() ?: 'adjunto',
            'tipo' => (string) $adjunto->contentType(),
            'contenido' => $adjunto->contents(),
        ], $remoto->attachments(true));
    }

    /**
     * @return array{agregados: int, examinados: int}
     */
    private function sincronizarGoogle(bool $completo, int $limite): array
    {
        $gmail = $this->gmail();
        $agregados = 0;
        $examinados = 0;
        $pagina = null;

        do {
            $params = ['maxResults' => 100];
            if ($pagina) {
                $params['pageToken'] = $pagina;
            }

            $lista = $gmail->users_messages->listUsersMessages('me', $params);
            $mensajes = $lista->getMessages() ?? [];
            $pagina = $lista->getNextPageToken();

            $ids = array_map(fn (Message $m): string => $m->getId(), $mensajes);
            $existentes = InboxMensaje::whereIn('gmail_id', $ids)->pluck('gmail_id')->flip();
            $faltantes = array_filter($mensajes, fn (Message $m): bool => ! $existentes->has($m->getId()));
            $examinados += count($mensajes);

            foreach ($faltantes as $stub) {
                $mensaje = $gmail->users_messages->get('me', $stub->getId(), ['format' => 'full']);
                $this->crearMensaje($this->mapear($mensaje));
                $agregados++;

                if ($agregados >= $limite) {
                    $this->marcarSync();

                    return ['agregados' => $agregados, 'examinados' => $examinados];
                }
            }

            // En modo incremental, al toparnos con mensajes ya vistos no hace
            // falta seguir bajando: todo lo de abajo es más viejo y ya está.
            $todoLaPaginaExistia = count($faltantes) === 0 && count($mensajes) > 0;
            if (! $completo && $todoLaPaginaExistia) {
                break;
            }
        } while ($pagina);

        $this->marcarSync();

        return ['agregados' => $agregados, 'examinados' => $examinados];
    }

    /**
     * Sincroniza por IMAP con el correo del buzón y una contraseña de
     * aplicación (para cuentas @gmail.com personales; en Workspace se
     * usa la cuenta de servicio con delegación de dominio).
     *
     * @return array{agregados: int, examinados: int}
     */
    private function sincronizarImap(bool $completo, int $limite): array
    {
        $consulta = $this->imapMailbox()->inbox()->messages()
            ->withHeaders()
            ->withFlags()
            ->withBody()
            ->newest();

        // En modo incremental solo se revisa una ventana reciente; los
        // correos ya guardados se descartan por su gmail_id (imap-{uid}).
        if (! $completo) {
            $consulta->since(now()->subDays(3));
        }

        $agregados = 0;
        $examinados = 0;

        $consulta->each(function (ImapMessage $mensaje) use (&$agregados, &$examinados, $limite): bool {
            $examinados++;

            if (! InboxMensaje::where('gmail_id', 'imap-'.$mensaje->uid())->exists()) {
                $this->guardarImap($mensaje);
                $agregados++;
            }

            return $agregados < $limite;
        }, 20);

        $this->marcarSync();

        return ['agregados' => $agregados, 'examinados' => $examinados];
    }

    /**
     * Escucha el buzón por IMAP IDLE: registra cada correo apenas Gmail lo
     * anuncia. Retorna al vencer el tiempo de espera de la conexión; quien
     * lo invoca (inbox:listen) debe llamarlo de nuevo en un bucle.
     */
    public function escuchar(int $segundos = 300): void
    {
        $completo = fn (MessageQueryInterface $q): MessageQueryInterface => $q->withHeaders()->withFlags()->withBody();

        $this->imapMailbox()->inbox()->idle(function (ImapMessage $mensaje): void {
            if (! InboxMensaje::where('gmail_id', 'imap-'.$mensaje->uid())->exists()) {
                $this->guardarImap($mensaje);
            }
            $this->marcarSync();
        }, $completo, $segundos);
    }

    /**
     * Guarda un correo IMAP sin que un mensaje defectuoso aborte la
     * sincronización (y con ella a todos los más viejos): si falla al
     * mapear o insertar, se reintenta con el texto saneado y, en último
     * caso, con un registro mínimo — el correo nunca se pierde.
     */
    private function guardarImap(ImapMessage $mensaje): void
    {
        $uid = 'imap-'.$mensaje->uid();

        try {
            $datos = $this->mapearImap($mensaje);
        } catch (Throwable $e) {
            Log::warning('Bandeja: no se pudo leer el correo, se guarda un registro mínimo.', ['gmail_id' => $uid, 'error' => $e->getMessage()]);
            $this->crearMensaje($this->registroMinimo($mensaje, $uid, 'No se pudo leer el contenido de este correo. Ábralo directamente en Gmail.'));

            return;
        }

        $this->crearMensaje($datos);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crearMensaje(array $datos): void
    {
        try {
            InboxMensaje::create($datos);

            return;
        } catch (Throwable $e) {
            Log::warning('Bandeja: falló al guardar el correo, se reintenta saneado.', ['gmail_id' => $datos['gmail_id'] ?? null, 'error' => $e->getMessage()]);
        }

        try {
            InboxMensaje::create($this->sanear($datos));

            return;
        } catch (Throwable $e) {
            Log::error('Bandeja: el reintento saneado también falló, se guarda sin cuerpo.', ['gmail_id' => $datos['gmail_id'] ?? null, 'error' => $e->getMessage()]);
        }

        $minimo = $this->sanear($datos);
        $minimo['cuerpo_html'] = null;
        $minimo['cuerpo_texto'] = 'El contenido de este correo no se pudo guardar. Ábralo directamente en Gmail.';
        $minimo['adjuntos'] = null;
        $minimo['etiquetas'] = null;

        InboxMensaje::create($minimo);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function sanear(array $datos): array
    {
        $limpiar = function (mixed $valor) use (&$limpiar): mixed {
            if (is_string($valor)) {
                return mb_convert_encoding($valor, 'UTF-8', 'UTF-8');
            }

            return is_array($valor) ? array_map($limpiar, $valor) : $valor;
        };

        return array_map($limpiar, $datos);
    }

    /**
     * @return array<string, mixed>
     */
    private function registroMinimo(ImapMessage $mensaje, string $uid, string $nota): array
    {
        $seguro = function (callable $leer, mixed $defecto = null): mixed {
            try {
                return $leer();
            } catch (Throwable) {
                return $defecto;
            }
        };

        $de = $seguro(fn () => $mensaje->from());

        return [
            'gmail_id' => $uid,
            'remitente' => mb_substr((string) $seguro(fn () => $de ? ($de->name().' <'.$de->email().'>') : '', ''), 0, 500),
            'remitente_email' => $de ? mb_substr((string) $seguro(fn () => $de->email(), ''), 0, 255) : null,
            'asunto' => mb_substr((string) $seguro(fn () => $mensaje->subject(), '(sin asunto)'), 0, 500),
            'snippet' => mb_substr($nota, 0, 500),
            'cuerpo_texto' => $nota,
            'tiene_adjuntos' => false,
            'recibido_en' => $seguro(fn () => Carbon::parse($mensaje->date() ?? 'now')->setTimezone(config('app.timezone')), now()),
        ];
    }

    private function imapMailbox(): Mailbox
    {
        return new Mailbox([
            'host' => self::IMAP_HOST,
            'port' => self::IMAP_PORT,
            'encryption' => 'ssl',
            'username' => (string) $this->buzon(),
            // Google la muestra con espacios ("xxxx xxxx xxxx xxxx"); se quitan por si la pegan tal cual.
            'password' => str_replace(' ', '', (string) Setting::get('gmail_password')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearImap(ImapMessage $mensaje): array
    {
        $de = $mensaje->from();
        $remitente = $de
            ? trim(($de->name() !== '' ? $de->name().' ' : '').'<'.$de->email().'>')
            : '';

        $para = collect($mensaje->to())
            ->map(fn ($para): string => $para->email())
            ->filter()
            ->implode(', ');

        $texto = $mensaje->text();
        $html = $mensaje->html();

        if ($texto === null && $html !== null) {
            $texto = $this->htmlATexto($html);
        }

        $adjuntos = array_map(fn ($adjunto): array => [
            'nombre' => $adjunto->filename() ?: 'adjunto',
            'tipo' => $adjunto->contentType(),
            'tamano' => (int) ($adjunto->contentStream()->getSize() ?? 0),
        ], $mensaje->attachments());

        $baseSnippet = $html !== null ? $this->htmlATexto($html) : trim((string) preg_replace('/\s+/', ' ', (string) $texto));

        return [
            'gmail_id' => 'imap-'.$mensaje->uid(),
            'thread_id' => $mensaje->messageId() ? mb_substr($mensaje->messageId(), 0, 40) : null,
            'remitente' => mb_substr($remitente, 0, 500),
            'remitente_email' => $de ? mb_substr($de->email(), 0, 255) : null,
            'para' => mb_substr($para, 0, 500),
            'asunto' => mb_substr((string) ($mensaje->subject() ?? '(sin asunto)'), 0, 500),
            'snippet' => mb_substr($baseSnippet, 0, 500),
            'cuerpo_texto' => $texto,
            'cuerpo_html' => $html,
            'tiene_adjuntos' => $adjuntos !== [],
            'adjuntos' => $adjuntos ?: null,
            'etiquetas' => $mensaje->flags() ?: null,
            // La fecha del encabezado trae la zona del remitente; se guarda
            // normalizada a la zona de la app para ordenar bien por llegada.
            'recibido_en' => Carbon::parse($mensaje->date() ?? 'now')->setTimezone(config('app.timezone')),
        ];
    }

    /** Extrae texto legible de un HTML quitando bloques de estilos y scripts. */
    private function htmlATexto(string $html): string
    {
        $limpio = preg_replace('/<(style|script)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;

        return trim((string) preg_replace('/\s+/', ' ', strip_tags($limpio)));
    }

    private function gmail(): Gmail
    {
        $credenciales = json_decode((string) Setting::get('gmail_credenciales'), true);
        if (! is_array($credenciales) || empty($credenciales['client_email'])) {
            throw new RuntimeException('Las credenciales de Gmail no son un JSON válido de cuenta de servicio.');
        }

        $cliente = new Client;
        $cliente->setAuthConfig($credenciales);
        $cliente->setScopes([self::SCOPE]);
        $cliente->setSubject($this->buzon());

        return new Gmail($cliente);
    }

    private function marcarSync(): void
    {
        Cache::put('inbox_ultima_sync', now()->toIso8601String());
        Cache::forget('inbox_ultimo_error');
    }

    public function registrarError(string $mensaje): void
    {
        Cache::put('inbox_ultimo_error', $mensaje);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapear(Message $mensaje): array
    {
        $headers = collect($mensaje->getPayload()?->getHeaders() ?? [])
            ->mapWithKeys(fn ($h): array => [strtolower($h->getName()) => $h->getValue()]);

        $de = (string) ($headers['from'] ?? '');
        [$texto, $html, $adjuntos] = $this->extraerCuerpo($mensaje->getPayload());

        if ($texto === null && $html !== null) {
            $texto = trim((string) preg_replace('/\s+/', ' ', strip_tags($html)));
        }

        return [
            'gmail_id' => $mensaje->getId(),
            'thread_id' => $mensaje->getThreadId(),
            'remitente' => mb_substr($de, 0, 500),
            'remitente_email' => $this->emailDe($de),
            'para' => mb_substr((string) ($headers['to'] ?? ''), 0, 500),
            'asunto' => mb_substr((string) ($headers['subject'] ?? '(sin asunto)'), 0, 500),
            'snippet' => mb_substr((string) $mensaje->getSnippet(), 0, 500),
            'cuerpo_texto' => $texto,
            'cuerpo_html' => $html,
            'tiene_adjuntos' => $adjuntos !== [],
            'adjuntos' => $adjuntos ?: null,
            'etiquetas' => $mensaje->getLabelIds() ?: null,
            'recibido_en' => Carbon::createFromTimestampMs((int) $mensaje->getInternalDate())->setTimezone(config('app.timezone')),
        ];
    }

    /**
     * Recorre las partes MIME y separa texto plano, HTML y adjuntos.
     *
     * @return array{0: ?string, 1: ?string, 2: array<int, array{nombre: string, tipo: string, tamano: int}>}
     */
    private function extraerCuerpo(?MessagePart $parte): array
    {
        $texto = null;
        $html = null;
        $adjuntos = [];

        $camina = function (MessagePart $p) use (&$camina, &$texto, &$html, &$adjuntos): void {
            $mime = (string) $p->getMimeType();
            $archivo = (string) ($p->getFilename() ?? '');

            if ($archivo !== '' && $p->getBody()?->getAttachmentId()) {
                $adjuntos[] = [
                    'nombre' => $archivo,
                    'tipo' => $mime,
                    'tamano' => (int) ($p->getBody()?->getSize() ?? 0),
                ];

                return;
            }

            $data = $p->getBody()?->getData();
            if ($data) {
                $contenido = $this->decodificar($data);
                if ($mime === 'text/plain' && $texto === null) {
                    $texto = $contenido;
                } elseif ($mime === 'text/html' && $html === null) {
                    $html = $contenido;
                }
            }

            foreach ($p->getParts() ?? [] as $hija) {
                $camina($hija);
            }
        };

        if ($parte) {
            $camina($parte);
        }

        return [$texto, $html, $adjuntos];
    }

    private function decodificar(string $data): string
    {
        $raw = base64_decode(strtr($data, '-_', '+/'), true);

        if ($raw === false) {
            return '';
        }

        $utf8 = @mb_convert_encoding($raw, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');

        return is_string($utf8) ? $utf8 : $raw;
    }

    private function emailDe(string $de): ?string
    {
        if (preg_match('/<([^>]+)>/', $de, $m)) {
            return mb_substr($m[1], 0, 255);
        }

        return filter_var($de, FILTER_VALIDATE_EMAIL) ? mb_substr($de, 0, 255) : null;
    }
}
