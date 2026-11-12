<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SolicitudReferencia extends Model
{
    use SoftDeletes;

    /**
     * EPS que manejan más de un contrato: al aceptar o negar, el personal de
     * referencia debe elegir la variante completa (p. ej. NUEVA EPS PGP).
     *
     * @var array<int, string>
     */
    public const EPS_CON_CONTRATO = ['ASMET SALUD', 'EMSSANAR', 'NUEVA EPS', 'SURA'];

    /**
     * Nombre de la EPS sin el tipo de contrato: "NUEVA EPS PGP" → "NUEVA EPS".
     */
    public static function epsBase(?string $eps): string
    {
        $nombre = trim((string) $eps);
        $mayusculas = mb_strtoupper($nombre);

        foreach (self::EPS_CON_CONTRATO as $base) {
            if ($mayusculas === $base || str_starts_with($mayusculas, $base.' ')) {
                return $base;
            }
        }

        return $nombre;
    }

    protected $table = 'solicitudes_referencia';

    protected $attributes = [
        'urgencia_vital' => false,
    ];

    protected $fillable = [
        'clinica_id',
        'inbox_mensaje_id',
        'origen',
        'fecha',
        'hora',
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'genero',
        'edad',
        'tipo_documento',
        'numero_documento',
        'eps',
        'diagnostico',
        'municipio_capita',
        'direccion_paciente',
        'telefono_paciente',
        'especialidad_requerida',
        'tipo_referencia',
        'urgencia_vital',
        'servicio_ubicacion_actual',
        'servicio_remision',
        'institucion_remitente',
        'municipio_remitente',
        'requiere_aislamiento',
        'tipo_aislamiento',
        'quien_remitente',
        'telefono_contacto',
        'correo_contacto',
        'resumen_historia_clinica',
        'via_contacto',
        'gestante',
        'condicion_especial',
        'observaciones',
        'estado',
        'codigo_aceptacion',
        'hora_respuesta',
        'hora_llegada',
        'lugar_llegada',
        'sede',
        'motivo_negacion',
        'grupo_negacion',
        'motivo_no_ingreso',
        'numero_ingreso',
        'nombre_quien_responde',
        'observaciones_respuesta',
    ];

    protected $casts = [
        'fecha' => 'date',
        'gestante' => 'boolean',
        'urgencia_vital' => 'boolean',
    ];

    public function clinica(): BelongsTo
    {
        return $this->belongsTo(Clinica::class);
    }

    /**
     * Correo al que van las notificaciones de estado: el de la clínica
     * registrada cuando existe, o el correo de contacto que el remitente
     * dejó en el formulario público cuando la solicitud no tiene clínica.
     */
    public function correoNotificacion(): ?string
    {
        return $this->clinica?->email ?? $this->correo_contacto;
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(SolicitudReferenciaAdjunto::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(SolicitudReferenciaEvento::class)->latest();
    }

    public function diagnosticos(): HasMany
    {
        return $this->hasMany(DiagnosticoSolicitud::class)->orderBy('id');
    }

    public function evoluciones(): HasMany
    {
        return $this->hasMany(SolicitudEvolucion::class)->latest();
    }

    protected static function booted(): void
    {
        static::creating(function (SolicitudReferencia $solicitud) {
            // Auto-generate codigo_aceptacion on create (will be overwritten when accepted)
            // Leave null until accepted by internal staff
        });
    }
}
