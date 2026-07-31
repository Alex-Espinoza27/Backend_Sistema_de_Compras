<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Multitenancy suave por entidad (02A §5). Un usuario con rol ENTIDAD solo
 * ve/gestiona filas de su propia entidad_id; ADMINISTRADOR y AUDITOR ven todas.
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

            if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('ENTIDAD')) {
                return;
            }

            $model = $builder->getModel();

            $builder->where($model->qualifyColumn($model->entidadColumn), $user->entidad_id);
        });
    }
}
