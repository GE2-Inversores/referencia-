<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Mail\Message;

class InboxMensaje extends Model
{
    protected $table = 'inbox_mensajes';

    protected $fillable = [
        'gmail_id',
        'thread_id',
        'message_id',
        'in_reply_to',
        'referencias',
        'remitente',
        'remitente_email',
        'para',
        'asunto',
        'snippet',
        'cuerpo_texto',
        'cuerpo_html',
        'tiene_adjuntos',
        'adjuntos',
        'etiquetas',
        'recibido_en',
        'leido',
        'gestion',
        'gestion_por',
        'gestion_at',
        'gestion_detalle',
        'contestado_at',
        'contestado_por',
        'contestado_detalle',
        'carpeta',
    ];

    /**
     * Solicitud de referencia radicada a partir de este correo, si existe.
     */
    public function solicitud(): HasOne
    {
        return $this->hasOne(SolicitudReferencia::class, 'inbox_mensaje_id');
    }

    /**
     * Las etiquetas de sistema del buzón (INBOX, UNREAD, \Seen…) no se
     * muestran ni se editan: solo importan las personalizadas, como
     * "PACIENTES NO RED".
     */
    public static function esEtiquetaSistema(string $nombre): bool
    {
        return (bool) preg_match(
            '/^\\\\?(INBOX|UNREAD|SENT|DRAFTS?|TRASH|SPAM|JUNK|IMPORTANT|STARRED|CHAT|MUTED|ALL|ARCHIVE|BIN|FLAGGED|SEEN|DELETED|ANSWERED|RECENT|NOINF_RECENT|CATEGORY_|VOICEMAIL)/i',
            $nombre
        );
    }

    /**
     * Las filas viejas guardaban las banderas IMAP (\Seen, \Flagged…) en
     * lugar de las etiquetas de Gmail: mientras el registro solo tenga
     * eso, todavía falta traer sus etiquetas reales del buzón.
     */
    public function necesitaEtiquetas(): bool
    {
        $etiquetas = $this->etiquetas;

        if ($etiquetas === null || $etiquetas === []) {
            return true;
        }

        return collect($etiquetas)->every(fn ($e): bool => is_string($e) && (bool) preg_match(
            '/^\\\\?(Seen|Flagged|Draft|Deleted|Answered|Recent|Junk|NonJunk|JunkRecorded|NotJunk|NoInf_recent)$/i',
            $e
        ));
    }

    /**
     * Todos los correos de la misma conversación (comparten thread_id),
     * este mensaje incluido.
     */
    public function hilo(): HasMany
    {
        return $this->hasMany(self::class, 'thread_id', 'thread_id');
    }

    /**
     * Message-ID propio del correo, normalizado con sus corchetes. Sirve
     * tanto para buscarlo en el buzón como para enlazar respuestas; en las
     * filas viejas, antes de que existiera message_id, thread_id guardaba
     * el Message-ID recortado.
     */
    public function identificadorCorreo(): ?string
    {
        $id = trim((string) ($this->message_id ?: ''));

        if ($id === '' && str_contains((string) $this->thread_id, '@')) {
            $id = trim((string) $this->thread_id);
        }

        if ($id === '') {
            return null;
        }

        return str_starts_with($id, '<') ? $id : "<{$id}>";
    }

    /**
     * Encabezados In-Reply-To y References para responder dentro del mismo
     * hilo: sin ellos el cliente de correo del destinatario abre una
     * conversación nueva y la respuesta pierde el contexto.
     */
    public function responderEnHilo(Message $message): void
    {
        $id = $this->identificadorCorreo();

        if ($id === null) {
            return;
        }

        $headers = $message->getSymfonyMessage()->getHeaders();
        $headers->addTextHeader('In-Reply-To', $id);
        $headers->addTextHeader('References', trim("{$this->referencias} {$id}"));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tiene_adjuntos' => 'boolean',
            'adjuntos' => 'array',
            'etiquetas' => 'array',
            'recibido_en' => 'datetime',
            'leido' => 'boolean',
            'gestion_at' => 'datetime',
            'contestado_at' => 'datetime',
        ];
    }
}
