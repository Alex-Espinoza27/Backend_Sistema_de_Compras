<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;

/**
 * Filtro de una columna fecha-hora por rango de DÍAS completos, inclusivo y
 * en hora de Lima: "del 15/11 al 16/11" devuelve todo lo registrado entre las
 * 00:00 del 15 y las 23:59:59 del 16, sin importar la hora del registro.
 *
 * Los límites se envían con su desplazamiento (`-05:00`), así el resultado no
 * depende de la zona horaria que tenga configurada la sesión de la base de
 * datos ni de la del servidor, y el índice de la columna sigue sirviendo
 * (no hay `::date` sobre la columna).
 */
class RangoFechas
{
    public const ZONA = 'America/Lima';

    /** Inicio del día `YYYY-MM-DD` en hora de Lima. */
    public static function inicio(string $fecha): CarbonImmutable
    {
        return CarbonImmutable::parse(substr($fecha, 0, 10), self::ZONA)->startOfDay();
    }

    /** Primer instante del día SIGUIENTE (límite superior exclusivo). */
    public static function finExclusivo(string $fecha): CarbonImmutable
    {
        return self::inicio($fecha)->addDay();
    }

    public static function aplicar(EloquentBuilder|Builder $consulta, string $columna, ?string $desde, ?string $hasta): void
    {
        if (filled($desde)) {
            $consulta->where($columna, '>=', self::inicio($desde)->toIso8601String());
        }

        if (filled($hasta)) {
            $consulta->where($columna, '<', self::finExclusivo($hasta)->toIso8601String());
        }
    }
}
