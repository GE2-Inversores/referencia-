<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Contador diario de la Bandeja (recibidos, eliminados, contestados), por
 * fecha de llegada del correo. Sobrevive a la limpieza de medianoche porque
 * guarda solo números.
 */
class InboxEstadistica extends Model
{
    public const CAMPOS = ['recibidos', 'eliminados', 'contestados'];

    protected $fillable = ['fecha', 'recibidos', 'eliminados', 'contestados'];

    /**
     * Suma al contador del día en que llegó el correo. Nunca interrumpe el
     * flujo que lo invoca: contar es secundario a sincronizar o responder.
     */
    public static function sumar(string $campo, ?CarbonInterface $llegada = null, int $cantidad = 1): void
    {
        if (! in_array($campo, self::CAMPOS, true) || $cantidad <= 0) {
            return;
        }

        try {
            $fecha = ($llegada ?? now())->toDateString();

            static::query()->insertOrIgnore(['fecha' => $fecha, 'created_at' => now(), 'updated_at' => now()]);
            static::query()->where('fecha', $fecha)->increment($campo, $cantidad, ['updated_at' => now()]);
        } catch (Throwable $e) {
            Log::warning('Bandeja: no se pudo actualizar el contador diario.', ['campo' => $campo, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Fija el valor de un día (relleno histórico desde Gmail).
     */
    public static function fijar(string $campo, CarbonInterface $dia, int $valor): void
    {
        if (! in_array($campo, self::CAMPOS, true)) {
            return;
        }

        $fecha = $dia->toDateString();

        static::query()->insertOrIgnore(['fecha' => $fecha, 'created_at' => now(), 'updated_at' => now()]);
        static::query()->where('fecha', $fecha)->update([$campo => max(0, $valor), 'updated_at' => now()]);
    }
}
