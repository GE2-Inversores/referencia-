<?php

namespace App\Services;

use App\Models\InboxEstadistica;
use App\Models\InboxMensaje;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonInterface;
use DirectoryTree\ImapEngine\BodyStructurePart;
use DirectoryTree\ImapEngine\FolderInterface;
use DirectoryTree\ImapEngine\Mailbox;
use DirectoryTree\ImapEngine\MessageInterface as ImapMessage;
use DirectoryTree\ImapEngine\MessageQueryInterface;
use DirectoryTree\ImapEngine\Support\BodyPartDecoder;
use DirectoryTree\ImapEngine\Support\Str as ImapStr;
use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Label;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\MessagePart;
use Google\Service\Gmail\ModifyMessageRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

class GmailService
{
    private const SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';

    private const IMAP_HOST = 'imap.gmail.com';

    private const IMAP_PORT = 993;

    private const MARCA_UID = 'gmail_marca_uid';

    private const MARCA_VALIDEZ = 'gmail_marca_validez';

    public const CORTE_UID = 'gmail_corte_uid';

    private const ALERTA_ACTIVA = 'inbox_alerta_activa';

    private const ELIMINADOS = 'inbox_eliminados';

    private const ELIMINADOS_MAX = 2000;

    private const FALLOS_MAXIMOS = 5;

    /**
     * Ajustes gmail_* leídos una sola vez por instancia — cada Setting::get
     * es una consulta a la base de datos.
     *
     * @var array<string, mixed>
     */
    private array $ajustes = [];

    private function ajuste(string $clave, mixed $defecto = null): mixed
    {
        if (! array_key_exists($clave, $this->ajustes)) {
            $this->ajustes[$clave] = Setting::get($clave, $defecto);
        }

        return $this->ajustes[$clave];
    }

    public function configurado(): bool
    {
        if (! filled($this->ajuste('gmail_buzon'))) {
            return false;
        }

        return $this->esImap()
            ? filled($this->ajuste('gmail_password'))
            : filled($this->ajuste('gmail_credenciales'));
    }

    public function buzon(): ?string
    {
        return $this->ajuste('gmail_buzon');
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
     * `$maxSegundos` acota cuánto trabaja esta ejecución: al agotarse se detiene y
     * lo pendiente se trae en la siguiente (así una sync lanzada desde una petición
     * web no deja ocupado el servidor).
     *
     * @return array{agregados: int, examinados: int}
     */
    public function sincronizar(bool $completo = false, int $limite = 500, bool $auditar = false, ?int $maxSegundos = null): array
    {
        if (! $this->configurado()) {
            throw new RuntimeException('Gmail no está configurado: falta el buzón o las credenciales.');
        }

        // La página sincroniza al abrirse y el cron cada minuto: sin candado
        // dos procesos abrirían conexiones IMAP en paralelo sobre el mismo buzón.
        // El candado dura lo que puede tardar la ejecución más un margen: si el proceso muere,
        // no deja la sincronización bloqueada para todos durante minutos.
        $candado = Cache::lock('inbox-sync', ($maxSegundos ?? 240) + 60);

        if (! $candado->get()) {
            return ['agregados' => 0, 'examinados' => 0];
        }

        try {
            return $this->esImap()
                ? $this->sincronizarImap($completo, $limite, $auditar, $maxSegundos)
                : $this->sincronizarGoogle($completo, $limite, $maxSegundos);
        } finally {
            $candado->release();
        }
    }

    public function esImap(): bool
    {
        return $this->ajuste('gmail_modo', 'imap') !== 'servicio';
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
        } catch (Throwable $e) {
            Log::warning('Bandeja: no se pudieron descargar los adjuntos del correo.', ['mensaje' => $mensaje->id, 'error' => $e->getMessage()]);

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
        $remoto = $this->encontrarRemotoImap($mensaje);

        if (! $remoto) {
            return [];
        }

        $meta = $mensaje->adjuntos ?? [];
        $adjuntos = [];

        foreach ($this->partesAdjuntasImap($remoto) as $i => $parte) {
            $adjuntos[] = [
                'nombre' => $meta[$i]['nombre'] ?? $this->nombreAdjuntoImap($parte->filename(), $parte->contentType(), $i + 1),
                'tipo' => $parte->contentType(),
                'contenido' => (string) (BodyPartDecoder::binary($parte, $remoto->bodyPart($parte->partNumber())) ?? ''),
            ];
        }

        return $adjuntos;
    }

    /**
     * Un solo adjunto del correo (por índice de la lista guardada) — para
     * abrirlo o descargarlo desde la Bandeja sin radicar la solicitud.
     *
     * @return array{nombre: string, tipo: string, contenido: string}|null
     */
    public function descargarAdjunto(InboxMensaje $mensaje, int $indice): ?array
    {
        if (! $this->configurado()) {
            return null;
        }

        try {
            if (! $this->esImap()) {
                if (str_starts_with((string) $mensaje->gmail_id, 'imap-')) {
                    Log::warning('Bandeja: el correo se sincronizó por IMAP pero el modo actual es cuenta de servicio.', ['mensaje' => $mensaje->id]);

                    return null;
                }

                return $this->adjuntosGoogle($mensaje)[$indice] ?? null;
            }

            $remoto = $this->encontrarRemotoImap($mensaje);

            if (! $remoto) {
                Log::info('Bandeja: el correo no se encontró en el buzón al abrir el adjunto.', ['mensaje' => $mensaje->id, 'indice' => $indice]);

                return null;
            }

            $meta = ($mensaje->adjuntos ?? [])[$indice] ?? null;
            $partes = $this->partesAdjuntasImap($remoto);

            // Con número de parte guardado la referencia es exacta; sin él
            // (correos viejos sin reparar) se cae al índice de la lista.
            $parte = isset($meta['parte'])
                ? collect($partes)->first(fn (BodyStructurePart $p): bool => $p->partNumber() === $meta['parte'])
                : ($partes[$indice] ?? null);

            if (! $parte) {
                return null;
            }

            return [
                'nombre' => $meta['nombre']
                    ?? $this->nombreAdjuntoImap($parte->filename(), $parte->contentType(), $indice + 1),
                'tipo' => $parte->contentType() ?: 'application/octet-stream',
                'contenido' => (string) (BodyPartDecoder::binary($parte, $remoto->bodyPart($parte->partNumber())) ?? ''),
            ];
        } catch (Throwable $e) {
            Log::warning('Bandeja: no se pudo descargar el adjunto.', [
                'mensaje' => $mensaje->id,
                'indice' => $indice,
                'modo' => $this->esImap() ? 'imap' : 'servicio',
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Busca el correo en el buzón para bajar algo de él (adjuntos, metadata).
     * En Gmail cada carpeta numera sus UID por separado: el UID guardado solo
     * vale para INBOX. Si el correo ya no está ahí (archivado o movido), se
     * busca por su Message-ID en «Todos» y, si la fila es vieja y no lo
     * tiene, por remitente, asunto y fecha.
     */
    private function encontrarRemotoImap(InboxMensaje $mensaje): ?ImapMessage
    {
        $mailbox = $this->imapMailbox();

        if (str_starts_with((string) $mensaje->gmail_id, 'imap-')) {
            $remoto = $mailbox->inbox()->messages()->find((int) str_replace('imap-', '', (string) $mensaje->gmail_id));

            if ($remoto) {
                return $remoto;
            }
        }

        return $this->buscarPorMessageId($mailbox, $mensaje) ?? $this->buscarPorRemitenteYAsunto($mailbox, $mensaje);
    }

    /**
     * Carpetas donde puede estar un correo: «Todos» (tiene todo lo no
     * borrado, incluso lo archivado y lo etiquetado), la entrada, la papelera
     * y el spam. Se evita recorrer las decenas de etiquetas del buzón: Gmail
     * las lista como carpetas pero son lentas y algunas no se pueden abrir.
     *
     * @return array<int, FolderInterface>
     */
    private function carpetasDeBusqueda(Mailbox $mailbox): array
    {
        $carpetas = [];

        if ($todos = $this->carpetaTodos($mailbox)) {
            $carpetas[] = $todos;
        }

        $carpetas[] = $mailbox->inbox();

        foreach ($mailbox->folders()->get() as $carpeta) {
            if (array_intersect(['\Trash', '\Junk'], $carpeta->flags()) !== []) {
                $carpetas[] = $carpeta;
            }
        }

        return $carpetas;
    }

    /**
     * Busca el correo por su Message-ID (completo, o recortado a 40
     * caracteres en las filas viejas): SEARCH compara por coincidencia
     * parcial, así que el fragmento alcanza.
     */
    private function buscarPorMessageId(Mailbox $mailbox, InboxMensaje $mensaje): ?ImapMessage
    {
        $fragmento = $mensaje->fragmentoMessageId();

        if ($fragmento === null) {
            return null;
        }

        foreach ($this->carpetasDeBusqueda($mailbox) as $carpeta) {
            try {
                $carpeta->select(true);
                $uid = collect($mailbox->connection()->search(['HEADER', 'Message-ID', '"'.str_replace(['"', '\\'], '', $fragmento).'"'])->tokensAfter(2))
                    ->map->value
                    ->first();

                if ($uid !== null && ($remoto = $carpeta->messages()->find((int) $uid))) {
                    return $remoto;
                }
            } catch (Throwable $e) {
                Log::info('Bandeja: no se pudo buscar el correo en una carpeta.', ['carpeta' => $carpeta->path(), 'error' => $e->getMessage()]);
            }
        }

        return null;
    }

    /**
     * Último recurso para filas viejas sin Message-ID: busca en «Todos» por
     * remitente y asunto el día del correo, y se queda con el más cercano a
     * la hora en que llegó.
     */
    private function buscarPorRemitenteYAsunto(Mailbox $mailbox, InboxMensaje $mensaje): ?ImapMessage
    {
        $palabras = collect(preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', (string) $mensaje->asunto) ?? '', -1, PREG_SPLIT_NO_EMPTY))
            ->reject(fn (string $p): bool => in_array(mb_strtolower($p), ['re', 'rv', 'fwd', 'fw'], true))
            ->take(8)
            ->implode(' ');

        if ($palabras === '' || ! $mensaje->remitente_email || ! $mensaje->recibido_en || ! ($todos = $this->carpetaTodos($mailbox))) {
            return null;
        }

        try {
            $todos->select(true);
            $consulta = "from:{$mensaje->remitente_email} subject:({$palabras}) after:".($mensaje->recibido_en->timestamp - 86400).' before:'.($mensaje->recibido_en->timestamp + 86400);
            $uids = collect($mailbox->connection()->search(['X-GM-RAW', '"'.$consulta.'"'])->tokensAfter(2))->map->value->take(10);

            return $uids
                ->map(fn ($uid): ?ImapMessage => $todos->messages()->withHeaders()->find((int) $uid))
                ->filter()
                ->sortBy(fn (ImapMessage $m): int => abs(($m->date()?->timestamp ?? 0) - $mensaje->recibido_en->timestamp))
                ->first();
        } catch (Throwable $e) {
            Log::info('Bandeja: falló la búsqueda del correo por remitente y asunto.', ['mensaje' => $mensaje->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * La carpeta "Todos" del buzón: por la marca \All o por el nombre que usa
     * Gmail (inglés o español).
     */
    private function carpetaTodos(Mailbox $mailbox): ?FolderInterface
    {
        $carpetas = $mailbox->folders()->get();

        foreach ($carpetas as $carpeta) {
            if (in_array('\\All', $carpeta->flags(), true)) {
                return $carpeta;
            }
        }

        foreach ($carpetas as $carpeta) {
            if (in_array($carpeta->path(), ['[Gmail]/All Mail', '[Gmail]/Todos'], true)) {
                return $carpeta;
            }
        }

        return null;
    }

    /**
     * Repara la metadata de adjuntos guardada por la versión vieja (nombres
     * genéricos "noname"/"adjunto" y tamaño 0) leyendo el BODYSTRUCTURE del
     * buzón — liviano, no descarga el contenido.
     */
    public function refrescarAdjuntos(InboxMensaje $mensaje): void
    {
        if (! $mensaje->tiene_adjuntos || ! $this->configurado()) {
            return;
        }

        try {
            if ($this->esImap()) {
                $remoto = $this->encontrarRemotoImap($mensaje);

                if (! $remoto) {
                    return;
                }

                $adjuntos = $this->metaAdjuntosImap($remoto);
            } else {
                // En modo cuenta de servicio se repara con la API: la lista
                // sale del mismo filtro que usa la descarga, así quedan
                // alineados los chips con los índices.
                if (str_starts_with((string) $mensaje->gmail_id, 'imap-')) {
                    return;
                }

                $remoto = $this->gmail()->users_messages->get('me', $mensaje->gmail_id, ['format' => 'full']);
                [, , $adjuntos] = $this->extraerCuerpo($remoto->getPayload());
                $adjuntos = array_map(fn (array $a): array => $a + ['parte' => null], $adjuntos);
            }

            $mensaje->update(['adjuntos' => $adjuntos !== [] ? $adjuntos : null, 'tiene_adjuntos' => $adjuntos !== []]);
        } catch (Throwable) {
            // Si el buzón no responde se queda la metadata vieja — no es grave.
        }
    }

    /**
     * Partes del correo que son adjuntos de verdad según el BODYSTRUCTURE
     * del buzón (nombre, tipo y tamaño declarado) — sin descargar contenido.
     *
     * @return array<int, BodyStructurePart>
     */
    private function partesAdjuntasImap(ImapMessage $mensaje): array
    {
        return array_values($mensaje->bodyStructure(true)?->attachments() ?? []);
    }

    /**
     * Metadata de los adjuntos para guardar en la fila: el número de parte
     * ("2", "3.1") queda como referencia estable para descargarlo después.
     *
     * @return array<int, array{nombre: string, tipo: string, tamano: int, parte: string}>
     */
    private function metaAdjuntosImap(ImapMessage $mensaje): array
    {
        $adjuntos = [];

        foreach ($this->partesAdjuntasImap($mensaje) as $i => $parte) {
            $adjuntos[] = [
                'nombre' => $this->nombreAdjuntoImap($parte->filename(), $parte->contentType(), $i + 1),
                'tipo' => (string) $parte->contentType(),
                'tamano' => (int) ($parte->size() ?? 0),
                'parte' => $parte->partNumber(),
            ];
        }

        return $adjuntos;
    }

    /**
     * Algunos buzones adjuntan partes llamadas literalmente "noname" o sin
     * nombre: se les genera "adjunto-N.ext" según su tipo MIME.
     */
    private function nombreAdjuntoImap(?string $archivo, string $tipo, int $indice): string
    {
        $archivo = trim((string) $archivo);

        if ($archivo !== '' && strtolower($archivo) !== 'noname') {
            return $archivo;
        }

        $ext = MimeTypes::getDefault()->getExtensions($tipo)[0] ?? null;

        return 'adjunto-'.$indice.($ext ? '.'.$ext : '');
    }

    /**
     * @return array{agregados: int, examinados: int}
     */
    private function sincronizarGoogle(bool $completo, int $limite, ?int $maxSegundos = null): array
    {
        $inicio = microtime(true);
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
            $faltantes = array_filter($mensajes, fn (Message $m): bool => ! $existentes->has($m->getId()) && ! $this->fueEliminado($m->getId()));
            $examinados += count($mensajes);

            foreach ($faltantes as $stub) {
                $mensaje = $gmail->users_messages->get('me', $stub->getId(), ['format' => 'full']);
                $this->crearMensaje($this->mapear($mensaje));
                $agregados++;

                if ($agregados >= $limite || ($maxSegundos !== null && microtime(true) - $inicio >= $maxSegundos)) {
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
    private function sincronizarImap(bool $completo, int $limite, bool $auditar = false, ?int $maxSegundos = null): array
    {
        $inicio = microtime(true);
        $marca = (int) Setting::get(self::MARCA_UID, 0);
        // La auditoría revisa todo lo llegado desde el último corte de medianoche,
        // no solo lo posterior a la marca: recupera cualquier hueco del día.
        $piso = $auditar ? (int) Setting::get(self::CORTE_UID, 0) : $marca;

        // Paso 1 (liviano): solo los UID del buzón, sin descargar contenido.
        // Gmail asigna UID crecientes, así que se pide todo lo posterior a la
        // última marca: no depende de fechas ni de cuánto tiempo estuvo apagado.
        [$uids, $validez] = $this->conReintento(function () use ($completo, $piso): array {
            $inbox = $this->imapMailbox()->inbox();
            $validez = (int) ($inbox->status()['UIDVALIDITY'] ?? 0);
            $consulta = $inbox->messages()->newest();

            if (! $completo) {
                $piso > 0
                    ? $consulta->uid($piso + 1, INF)
                    : $consulta->since(now()->subDays(3));
            }

            return [$consulta->get()->map(fn (ImapMessage $m): int => $m->uid())->all(), $validez];
        });

        // Si Gmail reinicia la numeración del buzón (UIDVALIDITY), la marca
        // anterior ya no sirve: se vuelve a la ventana reciente.
        $validezAnterior = (int) Setting::get(self::MARCA_VALIDEZ, 0);
        if ($validez > 0 && $validezAnterior > 0 && $validez !== $validezAnterior) {
            $marca = $piso = 0;
        }
        if ($validez > 0) {
            $this->guardarEstado(self::MARCA_VALIDEZ, $validez);
        }

        // `UID n:*` siempre devuelve al menos el último mensaje aunque n lo supere.
        $uids = array_values(array_filter($uids, fn (int $u): bool => $piso === 0 || $u > $piso));
        $examinados = count($uids);

        $existentes = collect($uids)
            ->chunk(500)
            ->flatMap(fn ($grupo) => InboxMensaje::whereIn('gmail_id', $grupo->map(fn (int $u): string => 'imap-'.$u)->all())->pluck('gmail_id'))
            ->flip();

        // Se procesa del más viejo al más nuevo para que la marca avance sin dejar huecos.
        $faltantes = array_filter($uids, fn (int $u): bool => ! $existentes->has('imap-'.$u) && ! $this->fueEliminado('imap-'.$u));
        sort($faltantes);

        // Paso 2: se descarga el contenido solo de los correos que faltan,
        // por una misma conexión y reconectando si Gmail la cierra.
        $agregados = 0;
        $inbox = null;
        $avance = $marca;
        $bloqueado = false;
        $incompleto = false;

        foreach (array_slice($faltantes, 0, $limite) as $uid) {
            if ($maxSegundos !== null && microtime(true) - $inicio >= $maxSegundos) {
                $incompleto = true;

                break;
            }

            try {
                $mensaje = $this->conReintento(function (int $intento) use (&$inbox, $uid): ?ImapMessage {
                    if ($intento > 1 || $inbox === null) {
                        $inbox = $this->imapMailbox()->inbox();
                    }

                    return $this->descargarCorreoImap($inbox, $uid);
                });

                if ($mensaje) {
                    $this->guardarImap($mensaje);
                    $agregados++;
                }

                Cache::forget("inbox_fallos_uid_{$uid}");
            } catch (Throwable $e) {
                // Un correo que no se logra descargar no puede bloquear a los
                // demás ni perderse: se reintenta cada minuto y, tras varios
                // fallos, queda visible en la bandeja con aviso.
                $fallos = (int) Cache::get("inbox_fallos_uid_{$uid}", 0) + 1;
                Cache::put("inbox_fallos_uid_{$uid}", $fallos, now()->addDay());
                Log::warning('Bandeja: no se pudo descargar el correo.', ['uid' => $uid, 'fallos' => $fallos, 'error' => $e->getMessage()]);

                if ($fallos >= self::FALLOS_MAXIMOS) {
                    $this->crearMensaje($this->registroNoDescargado($uid));
                    $agregados++;
                    Cache::forget("inbox_fallos_uid_{$uid}");
                } else {
                    $bloqueado = true;
                }
            }

            if (! $bloqueado) {
                $avance = max($avance, $uid);
            }
        }

        // Si se procesó todo lo pendiente, la marca llega al último UID visto.
        if (! $bloqueado && ! $incompleto && count($faltantes) <= $limite && $uids !== []) {
            $avance = max($avance, ...$uids);
        }

        if ($avance > 0 && $avance !== (int) Setting::get(self::MARCA_UID, 0)) {
            $this->guardarEstado(self::MARCA_UID, $avance);
        }

        $this->marcarSync();

        return ['agregados' => $agregados, 'examinados' => $examinados];
    }

    /**
     * Descarga un correo sin traer sus adjuntos: solo encabezados, banderas y la
     * estructura; el texto y el HTML se piden como partes sueltas al leerlos.
     * Un correo con 20 MB de PDF pesa lo mismo que uno sin adjuntos, y el contenido
     * de los archivos solo se baja cuando alguien los abre. Si la estructura no
     * trae ninguna parte de texto, se baja completo como antes para no perderlo.
     */
    private function descargarCorreoImap(FolderInterface $inbox, int $uid): ?ImapMessage
    {
        $mensaje = $inbox->messages()->withHeaders()->withFlags()->find($uid);

        if ($mensaje === null) {
            return null;
        }

        $estructura = $mensaje->bodyStructure(true);

        if ($estructura !== null && ($estructura->text() !== null || $estructura->html() !== null)) {
            return $mensaje;
        }

        return $inbox->messages()->withHeaders()->withFlags()->withBody()->find($uid);
    }

    /**
     * Ejecuta una operación IMAP reintentándola con una conexión nueva si
     * Gmail la cierra (Server closed the connection / EOF).
     *
     * @template T
     *
     * @param  callable(int): T  $operacion  recibe el número de intento
     * @return T
     */
    private function conReintento(callable $operacion, int $intentos = 3): mixed
    {
        for ($intento = 1; ; $intento++) {
            try {
                return $operacion($intento);
            } catch (Throwable $e) {
                if ($intento >= $intentos) {
                    throw $e;
                }

                usleep(500_000 * $intento);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function registroNoDescargado(int $uid): array
    {
        $nota = 'No se pudo descargar este correo desde Gmail. Ábralo directamente en el buzón.';

        return [
            'gmail_id' => 'imap-'.$uid,
            'remitente' => '(correo no descargado)',
            'asunto' => "Correo sin descargar (UID {$uid})",
            'snippet' => $nota,
            'cuerpo_texto' => $nota,
            'tiene_adjuntos' => false,
            'recibido_en' => now(),
        ];
    }

    /**
     * Fija el corte de la auditoría: todo UID anterior ya pasó por la
     * limpieza de medianoche y no debe volver a traerse.
     */
    public function marcarCorteDelDia(): void
    {
        $marca = (int) Setting::get(self::MARCA_UID, 0);

        if ($marca > 0) {
            $this->guardarEstado(self::CORTE_UID, $marca);
        }
    }

    private function guardarEstado(string $clave, int $valor): void
    {
        // Directo a la tabla: el modelo Setting es auditable y generaría un
        // registro de auditoría por cada sincronización (cada minuto).
        $consulta = DB::table('settings')->where('key', $clave);

        $consulta->exists()
            ? $consulta->update(['value' => (string) $valor, 'deleted_at' => null, 'updated_at' => now()])
            : DB::table('settings')->insert(['key' => $clave, 'value' => (string) $valor, 'group' => 'inbox_estado', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Vigilante: si la Bandeja lleva más de $minutos sin sincronizar con éxito
     * (cron caído, credenciales vencidas, Gmail inaccesible) avisa una sola vez
     * a quienes ven la Bandeja; cuando se recupera, avisa que volvió.
     */
    public function vigilar(int $minutos = 15): void
    {
        if (! $this->configurado()) {
            return;
        }

        $ultima = $this->ultimaSync();
        $atrasada = ! $ultima || Carbon::parse($ultima)->lt(now()->subMinutes($minutos));
        $activa = Cache::has(self::ALERTA_ACTIVA);

        if ($atrasada && ! $activa) {
            Cache::forever(self::ALERTA_ACTIVA, true);
            $detalle = $this->ultimoError() ? ' Último error: '.$this->ultimoError() : '';
            $this->avisarBandeja(
                '⚠️ La Bandeja de correo dejó de sincronizar',
                'No se reciben correos nuevos desde '.($ultima ? Carbon::parse($ultima)->format('d/m/Y H:i') : 'nunca').'. Revise la conexión con Gmail.'.$detalle,
                'error',
            );
        } elseif (! $atrasada && $activa) {
            Cache::forget(self::ALERTA_ACTIVA);
            $this->avisarBandeja('✅ La Bandeja de correo volvió a sincronizar', 'Ya se están recibiendo los correos de nuevo.', 'success');
        }
    }

    /**
     * Respaldo del cron: cuando alguien tiene la aplicación abierta y la última
     * sincronización está atrasada, se sincroniza en segundo plano después de
     * responder. Máximo un intento cada 2 minutos aunque falle.
     */
    public function respaldarSiEstaAtrasada(int $minutos = 3): void
    {
        if (! $this->configurado()) {
            return;
        }

        $ultima = $this->ultimaSync();

        if ($ultima && Carbon::parse($ultima)->gt(now()->subMinutes($minutos))) {
            return;
        }

        if (! Cache::add('inbox_respaldo_intento', true, now()->addMinutes(2))) {
            return;
        }

        defer(function (): void {
            try {
                $this->sincronizar(limite: 20, maxSegundos: 10);
            } catch (Throwable $e) {
                $this->registrarError($e->getMessage());
                Log::warning('Bandeja: falló la sincronización de respaldo.', ['error' => $e->getMessage()]);
            }

            $this->vigilar();
        });
    }

    private function avisarBandeja(string $titulo, string $mensaje, string $tipo): void
    {
        $usuarios = User::permission('inbox.view')->where('is_active', true)->get();
        $ahora = now();

        $filas = $usuarios->map(fn (User $u): array => [
            'user_id' => $u->id,
            'type' => $tipo,
            'title' => $titulo,
            'message' => $mensaje,
            'link' => '/bandeja',
            'read_at' => null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ])->all();

        if ($filas !== []) {
            Notification::insert($filas);
        }

        $correos = $usuarios->pluck('email')->push(config('mail.referencia_interna'))
            ->filter(fn ($c): bool => filter_var($c, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()->values()->all();

        if ($correos === []) {
            return;
        }

        try {
            Mail::raw($titulo."\n\n".$mensaje."\n\nSistema de Referencia Santa Bárbara", function ($m) use ($correos, $titulo): void {
                $m->to($correos)->subject($titulo);
            });
        } catch (Throwable $e) {
            Log::warning('Bandeja: no se pudo enviar el correo de alerta.', ['error' => $e->getMessage()]);
        }
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
            $uid = 'imap-'.$mensaje->uid();
            if (! InboxMensaje::where('gmail_id', $uid)->exists() && ! $this->fueEliminado($uid)) {
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
     * Marca un correo como eliminado a propósito: el borrado es definitivo en
     * el aplicativo y el UID queda registrado para que la sincronización — y
     * la auditoría de huecos — no lo vuelva a traer desde Gmail.
     */
    public function registrarEliminado(string $gmailId): void
    {
        $lista = array_keys($this->eliminados());
        $lista[] = $gmailId;
        $lista = array_slice(array_values(array_unique($lista)), -self::ELIMINADOS_MAX);

        Setting::set(self::ELIMINADOS, (string) json_encode($lista));
        $this->ajustes['eliminados'] = array_flip($lista);
    }

    private function fueEliminado(string $gmailId): bool
    {
        return isset($this->eliminados()[$gmailId]);
    }

    /**
     * @return array<string, true>
     */
    private function eliminados(): array
    {
        if (! array_key_exists('eliminados', $this->ajustes)) {
            $this->ajustes['eliminados'] = array_flip((array) json_decode((string) Setting::get(self::ELIMINADOS, '[]'), true));
        }

        return $this->ajustes['eliminados'];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crearMensaje(array $datos): void
    {
        if (isset($datos['gmail_id']) && $this->fueEliminado((string) $datos['gmail_id'])) {
            return;
        }

        $datos['thread_id'] ??= $this->resolverHilo($datos);

        try {
            InboxEstadistica::sumar('recibidos', InboxMensaje::create($datos)->recibido_en);

            return;
        } catch (Throwable $e) {
            Log::warning('Bandeja: falló al guardar el correo, se reintenta saneado.', ['gmail_id' => $datos['gmail_id'] ?? null, 'error' => $e->getMessage()]);
        }

        try {
            InboxEstadistica::sumar('recibidos', InboxMensaje::create($this->sanear($datos))->recibido_en);

            return;
        } catch (Throwable $e) {
            Log::error('Bandeja: el reintento saneado también falló, se guarda sin cuerpo.', ['gmail_id' => $datos['gmail_id'] ?? null, 'error' => $e->getMessage()]);
        }

        $minimo = $this->sanear($datos);
        $minimo['cuerpo_html'] = null;
        $minimo['cuerpo_texto'] = 'El contenido de este correo no se pudo guardar. Ábralo directamente en Gmail.';
        $minimo['adjuntos'] = null;
        $minimo['etiquetas'] = null;

        InboxEstadistica::sumar('recibidos', InboxMensaje::create($minimo)->recibido_en);
    }

    /**
     * Correos que Gmail cuenta en el día (misma consulta del relleno) y que no
     * están en la base por Message-ID: sirve para entender por qué el total
     * de Gmail supera lo que la Bandeja importó. Solo lectura, solo IMAP.
     *
     * @return array<int, array{uid: int, entrada: bool, fecha: string, de: string, asunto: string}>
     */
    public function correosNoImportados(CarbonInterface $dia): array
    {
        if (! $this->esImap()) {
            throw new RuntimeException('El diagnóstico solo está disponible en modo IMAP.');
        }

        $buzon = $this->imapMailbox();
        $todos = $this->carpetaTodos($buzon) ?? throw new RuntimeException('No se encontró la carpeta «Todos» del buzón.');
        $todos->select(true);

        $inicio = $dia->copy()->startOfDay();
        $consulta = 'after:'.$inicio->timestamp.' before:'.$inicio->copy()->addDay()->timestamp.' -in:sent -in:drafts';
        $uids = collect($buzon->connection()->search(['X-GM-RAW', '"'.$consulta.'"'])->tokensAfter(2))->map->value->map(fn ($v): int => (int) $v);

        // Los correos guardados antes de existir la columna message_id no la
        // traen: también se reconocen por remitente + asunto + hora.
        $huella = fn (?string $de, ?string $asunto, mixed $hora): string => mb_strtolower((string) $de).'|'.mb_substr(trim((string) $asunto), 0, 500).'|'.Carbon::parse($hora)->setTimezone(config('app.timezone'))->format('H:i');

        $guardados = InboxMensaje::query()
            ->where('recibido_en', '>=', $inicio)
            ->where('recibido_en', '<', $inicio->copy()->addDay())
            ->get(['message_id', 'remitente_email', 'asunto', 'recibido_en']);

        $conocidos = $guardados->pluck('message_id')->filter()->flip();
        $huellas = $guardados->mapWithKeys(fn (InboxMensaje $m): array => [$huella($m->remitente_email, $m->asunto, $m->recibido_en) => true]);

        if ($uids->isEmpty()) {
            return [];
        }

        $enEntrada = collect($buzon->connection()->search(['X-GM-RAW', '"'.$consulta.' in:inbox"'])->tokensAfter(2))->map->value->map(fn ($v): int => (int) $v);

        // Una sola consulta con solo encabezados para todo el rango de UID del día.
        $enRango = $todos->messages()->withHeaders()->withoutBody()->withoutFlags()->uid($uids->min(), $uids->max())->get();

        $faltantes = [];
        foreach ($enRango as $mensaje) {
            if (! $uids->contains($mensaje->uid()) || $conocidos->has($mensaje->messageId())) {
                continue;
            }

            if ($mensaje->date() && $huellas->has($huella($mensaje->from()?->email(), $mensaje->subject(), $mensaje->date()))) {
                continue;
            }

            $faltantes[] = [
                'uid' => $mensaje->uid(),
                'entrada' => $enEntrada->contains($mensaje->uid()),
                'fecha' => (string) $mensaje->date()?->setTimezone(config('app.timezone'))->format('H:i'),
                'de' => (string) ($mensaje->from()?->email() ?? ''),
                'asunto' => mb_substr((string) $mensaje->subject(), 0, 70),
            ];
        }

        return $faltantes;
    }

    /**
     * Cuántos correos llegaron a Gmail cada día del rango (sin enviados ni
     * borradores), para rellenar el histórico de los contadores. Los días
     * sin correos quedan en 0; se usa la hora del servidor para el corte.
     *
     * @return array<string, int> fecha (Y-m-d) => correos recibidos
     */
    public function contarRecibidosPorDia(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $dias = [];
        for ($dia = $desde->copy()->startOfDay(); $dia->lte($hasta); $dia->addDay()) {
            $dias[$dia->toDateString()] = $dia->copy();
        }

        $consulta = fn (CarbonInterface $dia): string => 'after:'.$dia->timestamp.' before:'.$dia->copy()->addDay()->timestamp.' -in:sent -in:drafts';
        $conteos = [];

        if ($this->esImap()) {
            $buzon = $this->imapMailbox();
            $todos = $this->carpetaTodos($buzon) ?? throw new RuntimeException('No se encontró la carpeta «Todos» del buzón.');
            $todos->select(true);

            foreach ($dias as $fecha => $dia) {
                $respuesta = $buzon->connection()->search(['X-GM-RAW', '"'.$consulta($dia).'"']);
                $conteos[$fecha] = collect($respuesta->tokensAfter(2))->count();
            }

            return $conteos;
        }

        $gmail = $this->gmail();
        foreach ($dias as $fecha => $dia) {
            $total = 0;
            $pagina = null;
            do {
                $lista = $gmail->users_messages->listUsersMessages('me', array_filter(['q' => $consulta($dia), 'maxResults' => 500, 'pageToken' => $pagina]));
                $total += count($lista->getMessages() ?? []);
                $pagina = $lista->getNextPageToken();
            } while ($pagina);
            $conteos[$fecha] = $total;
        }

        return $conteos;
    }

    /**
     * Clave de la conversación para correos IMAP: si el mensaje responde a
     * uno que ya conocemos (por In-Reply-To/References) hereda el thread_id
     * del padre; si no, abre hilo propio con su Message-ID. En modo Gmail
     * API el thread_id ya viene resuelto por Google.
     *
     * @param  array<string, mixed>  $datos
     */
    private function resolverHilo(array $datos): ?string
    {
        $candidatos = $this->idsReferencias($datos);

        if ($candidatos !== []) {
            // Filas nuevas guardan el Message-ID completo en message_id; las
            // viejas lo guardaban recortado a 40 en thread_id — se prueban ambas.
            $padre = InboxMensaje::query()
                ->whereIn('message_id', $candidatos)
                ->orWhereIn('thread_id', array_map(fn (string $id): string => mb_substr($id, 0, 40), $candidatos))
                ->orderBy('id')
                ->first(['thread_id', 'message_id']);

            if ($padre) {
                return $padre->thread_id ?: $padre->message_id;
            }
        }

        return isset($datos['message_id']) ? mb_substr((string) $datos['message_id'], 0, 255) : null;
    }

    /**
     * Message-IDs citados por In-Reply-To y References, con corchetes y sin
     * ellos, para encontrar el padre sin importar cómo lo haya guardado la
     * fila original.
     *
     * @param  array<string, mixed>  $datos
     * @return array<int, string>
     */
    private function idsReferencias(array $datos): array
    {
        $ids = [];

        foreach (['in_reply_to', 'referencias'] as $campo) {
            if (preg_match_all('/<[^<>\s]+>/', (string) ($datos[$campo] ?? ''), $coincidencias)) {
                $ids = array_merge($ids, $coincidencias[0], array_map(fn (string $id): string => trim($id, '<>'), $coincidencias[0]));
            }
        }

        return array_values(array_unique($ids));
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

    /**
     * Trae del buzón las etiquetas de un correo ya guardado y las deja en
     * la fila — para los mensajes sincronizados antes de que existiera el
     * soporte de etiquetas (abrieron su registro con banderas o vacío).
     */
    public function refrescarEtiquetas(InboxMensaje $mensaje): void
    {
        if (! $this->configurado()) {
            return;
        }

        try {
            $etiquetas = $this->esImap()
                ? $this->etiquetasRemotasImap($mensaje)
                : $this->etiquetasRemotasGoogle($mensaje);
        } catch (Throwable $e) {
            Log::warning('Bandeja: no se pudieron traer las etiquetas del buzón.', [
                'mensaje' => $mensaje->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($etiquetas !== null) {
            $mensaje->update(['etiquetas' => $etiquetas ?: null]);
        }
    }

    /**
     * @return array<int, string>|null
     */
    private function etiquetasRemotasImap(InboxMensaje $mensaje): ?array
    {
        $remoto = $this->encontrarRemotoImap($mensaje);

        return $remoto ? $this->etiquetasImap($remoto) : null;
    }

    /**
     * @return array<int, string>|null
     */
    private function etiquetasRemotasGoogle(InboxMensaje $mensaje): ?array
    {
        $remoto = $this->gmail()->users_messages->get('me', (string) $mensaje->gmail_id, ['format' => 'minimal']);
        $ids = $remoto->getLabelIds() ?? [];

        return array_map(
            fn (string $id): string => $this->nombresEtiquetasGoogle()[$id] ?? $id,
            $ids
        );
    }

    /**
     * Etiquetas del correo según Gmail (X-GM-LABELS): las que el personal
     * marcó en el buzón ("PACIENTES NO RED", "Población objeto"…). Si el
     * servidor no las soporta se conservan las banderas IMAP como antes.
     *
     * @return array<int, string>|null
     */
    private function etiquetasImap(ImapMessage $mensaje): ?array
    {
        try {
            $etiquetas = $mensaje->folder()->mailbox()->connection()
                ->fetch('X-GM-LABELS', $mensaje->uid())
                ->map(fn ($r) => $r->tokenAt(3)?->lookup('X-GM-LABELS')?->values())
                ->filter()
                ->flatten()
                ->unique()
                ->values()
                ->all();
        } catch (Throwable) {
            $etiquetas = [];
        }

        return $etiquetas !== [] ? $etiquetas : ($mensaje->flags() ?: null);
    }

    /**
     * Las etiquetas que existen en el buzón: por IMAP Gmail las expone como
     * carpetas sin marca de sistema ("Población objeto"…); por la API son
     * las etiquetas type=user. Alimenta las sugerencias del selector de la
     * Bandeja — si el buzón no responde, queda solo la lista de la base.
     *
     * @return array<int, string>
     */
    public function etiquetasDelBuzon(): array
    {
        if (! $this->configurado()) {
            return [];
        }

        try {
            if ($this->esImap()) {
                $sistema = ['\Noselect', '\All', '\Trash', '\Sent', '\Drafts', '\Junk', '\Spam', '\Important', '\Archive', '\Flagged', '\Marked', '\Unmarked'];

                return $this->imapMailbox()->folders()->get()
                    ->filter(fn (FolderInterface $c): bool => $c->path() !== '' && strtoupper($c->path()) !== 'INBOX' && array_intersect($c->flags(), $sistema) === [])
                    ->map(fn (FolderInterface $c): string => ImapStr::fromImapUtf7($c->path()))
                    ->filter()
                    ->values()
                    ->all();
            }

            return collect($this->gmail()->users_labels->listUsersLabels('me')->getLabels() ?? [])
                ->filter(fn (Label $l): bool => $l->getType() === 'user')
                ->map(fn (Label $l): string => (string) $l->getName())
                ->filter()
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Mapa id → nombre de las etiquetas de Gmail (modo API): labelIds trae
     * "Label_123" y la lista muestra el nombre que el usuario ve en Gmail.
     *
     * @return array<string, string>
     */
    private function nombresEtiquetasGoogle(): array
    {
        if (! array_key_exists('labels_map', $this->ajustes)) {
            $mapa = [];
            try {
                $labels = $this->gmail()->users_labels->listUsersLabels('me')->getLabels() ?? [];
                foreach ($labels as $label) {
                    $mapa[$label->getId()] = $label->getName();
                }
            } catch (Throwable) {
                // Sin el mapa se guardan los ids crudos, igual que antes.
            }
            $this->ajustes['labels_map'] = $mapa;
        }

        return $this->ajustes['labels_map'];
    }

    /**
     * Deja en el correo exactamente las etiquetas personalizadas pedidas,
     * tanto en el buzón (X-GM-LABELS por IMAP o labels.modify por la API)
     * como en la fila local. Las etiquetas de sistema nunca se tocan.
     *
     * @param  array<int, string>  $deseadas  nombres de etiquetas visibles
     * @return array<int, string> etiquetas personalizadas que quedaron
     */
    public function ponerEtiquetas(InboxMensaje $mensaje, array $deseadas): array
    {
        $deseadas = array_values(array_unique(array_filter(
            array_map(fn ($e): string => trim((string) $e), $deseadas),
            fn (string $e): bool => $e !== '' && ! InboxMensaje::esEtiquetaSistema($e)
        )));

        $actuales = array_values(array_filter(
            $mensaje->etiquetas ?? [],
            fn ($e): bool => is_string($e) && ! InboxMensaje::esEtiquetaSistema($e)
        ));

        $agregar = array_values(array_diff($deseadas, $actuales));
        $quitar = array_values(array_diff($actuales, $deseadas));

        if ($agregar === [] && $quitar === []) {
            return $actuales;
        }

        if ($this->esImap()) {
            $this->ponerEtiquetasImap($mensaje, $agregar, $quitar);
        } else {
            $this->ponerEtiquetasGoogle($mensaje, $agregar, $quitar);
        }

        // Las etiquetas de sistema guardadas se conservan; solo cambia la
        // parte personalizada.
        $sistema = array_values(array_filter(
            $mensaje->etiquetas ?? [],
            fn ($e): bool => is_string($e) && InboxMensaje::esEtiquetaSistema($e)
        ));
        $mensaje->update(['etiquetas' => array_values(array_unique(array_merge($sistema, $deseadas))) ?: null]);

        return $deseadas;
    }

    /**
     * @param  array<int, string>  $agregar
     * @param  array<int, string>  $quitar
     */
    private function ponerEtiquetasImap(InboxMensaje $mensaje, array $agregar, array $quitar): void
    {
        $remoto = $this->encontrarRemotoImap($mensaje);

        if ($remoto === null) {
            throw new RuntimeException('El correo ya no está en el buzón.');
        }

        $conexion = $remoto->folder()->mailbox()->connection();
        $comillas = fn (string $etiqueta): string => '"'.str_replace(['"', '\\'], '', $etiqueta).'"';

        if ($agregar !== []) {
            $conexion->store(array_map($comillas, $agregar), $remoto->uid(), mode: '+', item: 'X-GM-LABELS');
        }

        if ($quitar !== []) {
            $conexion->store(array_map($comillas, $quitar), $remoto->uid(), mode: '-', item: 'X-GM-LABELS');
        }
    }

    /**
     * @param  array<int, string>  $agregar
     * @param  array<int, string>  $quitar
     */
    private function ponerEtiquetasGoogle(InboxMensaje $mensaje, array $agregar, array $quitar): void
    {
        $gmail = $this->gmail();
        $mapa = array_flip($this->nombresEtiquetasGoogle()); // nombre → id

        $idDe = function (string $nombre) use ($gmail, &$mapa): string {
            if (! isset($mapa[$nombre])) {
                // Etiqueta nueva: Gmail la crea igual que al usarla por primera vez en su propia interfaz.
                $label = $gmail->users_labels->create('me', new Label([
                    'name' => $nombre,
                    'labelListVisibility' => 'labelShow',
                    'messageListVisibility' => 'show',
                ]));
                $mapa[$nombre] = $label->getId();
            }

            return $mapa[$nombre];
        };

        $peticion = new ModifyMessageRequest;
        $peticion->setAddLabelIds(array_map($idDe, $agregar));
        $peticion->setRemoveLabelIds(array_map($idDe, $quitar));

        $gmail->users_messages->modify('me', (string) $mensaje->gmail_id, $peticion);
    }

    private function imapMailbox(): Mailbox
    {
        return new Mailbox([
            'host' => self::IMAP_HOST,
            'port' => self::IMAP_PORT,
            'encryption' => 'ssl',
            'timeout' => 15,
            'username' => (string) $this->buzon(),
            // Google la muestra con espacios ("xxxx xxxx xxxx xxxx"); se quitan por si la pegan tal cual.
            'password' => str_replace(' ', '', (string) $this->ajuste('gmail_password')),
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

        // Con el correo completo se lee de lo ya descargado; si llegó liviano, pide solo esas partes.
        $texto = $mensaje->text(true);
        $html = $mensaje->html(true);

        if ($texto === null && $html !== null) {
            $texto = $this->htmlATexto($html);
        }

        $adjuntos = $this->metaAdjuntosImap($mensaje);

        $baseSnippet = $html !== null ? $this->htmlATexto($html) : trim((string) preg_replace('/\s+/', ' ', (string) $texto));

        return [
            'gmail_id' => 'imap-'.$mensaje->uid(),
            // thread_id lo resuelve crearMensaje(): si el correo responde a uno
            // conocido hereda la clave del hilo; si no, queda con su Message-ID.
            'message_id' => $mensaje->messageId() ? mb_substr($mensaje->messageId(), 0, 255) : null,
            'in_reply_to' => ($respondeA = $mensaje->inReplyTo()) ? mb_substr(implode(' ', $respondeA), 0, 2000) : null,
            'referencias' => ($refs = $mensaje->header('References')?->getValue()) ? mb_substr($refs, 0, 4000) : null,
            'remitente' => mb_substr($remitente, 0, 500),
            'remitente_email' => $de ? mb_substr($de->email(), 0, 255) : null,
            'para' => mb_substr($para, 0, 500),
            'asunto' => mb_substr((string) ($mensaje->subject() ?? '(sin asunto)'), 0, 500),
            'snippet' => mb_substr($baseSnippet, 0, 500),
            'cuerpo_texto' => $texto,
            'cuerpo_html' => $html,
            'tiene_adjuntos' => $adjuntos !== [],
            'adjuntos' => $adjuntos ?: null,
            'etiquetas' => $this->etiquetasImap($mensaje),
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
        $credenciales = json_decode((string) $this->ajuste('gmail_credenciales'), true);
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
            // En modo API el threadId de Google ya es la clave de la conversación.
            'thread_id' => $mensaje->getThreadId(),
            'message_id' => isset($headers['message-id']) ? mb_substr($headers['message-id'], 0, 255) : null,
            'in_reply_to' => isset($headers['in-reply-to']) ? mb_substr($headers['in-reply-to'], 0, 2000) : null,
            'referencias' => isset($headers['references']) ? mb_substr($headers['references'], 0, 4000) : null,
            'remitente' => mb_substr($de, 0, 500),
            'remitente_email' => $this->emailDe($de),
            'para' => mb_substr((string) ($headers['to'] ?? ''), 0, 500),
            'asunto' => mb_substr((string) ($headers['subject'] ?? '(sin asunto)'), 0, 500),
            'snippet' => mb_substr((string) $mensaje->getSnippet(), 0, 500),
            'cuerpo_texto' => $texto,
            'cuerpo_html' => $html,
            'tiene_adjuntos' => $adjuntos !== [],
            'adjuntos' => $adjuntos ?: null,
            'etiquetas' => array_map(
                fn (string $id): string => $this->nombresEtiquetasGoogle()[$id] ?? $id,
                $mensaje->getLabelIds() ?? []
            ) ?: null,
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
