<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Respuesta libre del personal de referencia a una evolución que la clínica
 * registró: se envía por correo a la clínica y se ve en el módulo de Evolución.
 */
class SolicitudEvolucionRespuesta extends Model
{
    protected $table = 'solicitud_evolucion_respuestas';

    protected $fillable = [
        'solicitud_evolucion_id',
        'solicitud_referencia_id',
        'mensaje',
        'respondido_por',
        'correo_enviado',
    ];

    protected function casts(): array
    {
        return ['correo_enviado' => 'boolean'];
    }

    public function evolucion(): BelongsTo
    {
        return $this->belongsTo(SolicitudEvolucion::class, 'solicitud_evolucion_id');
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudReferencia::class, 'solicitud_referencia_id');
    }
}
