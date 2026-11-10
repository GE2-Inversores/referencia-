<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InboxMensaje extends Model
{
    protected $table = 'inbox_mensajes';

    protected $fillable = [
        'gmail_id',
        'thread_id',
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
    ];

    /**
     * Solicitud de referencia radicada a partir de este correo, si existe.
     */
    public function solicitud(): HasOne
    {
        return $this->hasOne(SolicitudReferencia::class, 'inbox_mensaje_id');
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
        ];
    }
}
