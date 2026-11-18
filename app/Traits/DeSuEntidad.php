<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Multitenancy suave por entidad (02A §5). Un usuario limitado a su entidad
 * (rol ENTIDAD, o rol personalizado con entidad asignada — ver
 * User::limitadoAEntidad) solo ve/gestiona filas de su entidad_id;
 * ADMINISTRADOR, AUDITOR y los roles personalizados sin entidad ven todas.
 *
 * Cada modelo que use este trait debe declarar la columna FK hacia ENTIDAD:
 *   protected string $entidadColumn = 'CONV_ENTIDAD_ID';
 */
trait DeSuEntidad
{
    protected static function bootDeSuEntidad(): void
    {
        static::addGlobalScope('de_su_entidad', function (Builder $builder): void {
            $user = auth()->user();

            if (! $user || ! method_exists($user, 'limitadoAEntidad') || ! $user->limitadoAEntidad()) {
                return;
            }

            $model = $builder->getModel();

            $builder->where($model->qualifyColumn($model->entidadColumn), $user->entidad_id);
        });
    }
}
