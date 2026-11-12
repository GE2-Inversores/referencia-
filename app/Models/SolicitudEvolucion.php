<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolicitudEvolucion extends Model
{
    protected $table = 'solicitud_evoluciones';

    protected $fillable = [
        'solicitud_referencia_id',
        'nota',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudReferencia::class, 'solicitud_referencia_id');
    }

    /** Respuestas del personal de referencia a esta evolución, de la más antigua a la más reciente. */
    public function respuestas(): HasMany
    {
        return $this->hasMany(SolicitudEvolucionRespuesta::class, 'solicitud_evolucion_id')->oldest();
    }

    /** Soportes clínicos que la clínica adjuntó con esta evolución. */
    public function adjuntos(): HasMany
    {
        return $this->hasMany(SolicitudReferenciaAdjunto::class, 'solicitud_evolucion_id');
    }
}
