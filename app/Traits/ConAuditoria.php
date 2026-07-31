<?php

namespace App\Traits;

/**
 * Llena las columnas de auditoría de 01 §1.1 ({PREFIJO}_ACTIVO, _CREADO_EN/_POR,
 * _ACTUALIZADO_EN/_POR) vía hooks del modelo, ya que $timestamps de Laravel está
 * desactivado (02A §2.1). El soft delete (_ELIMINADO_EN) se maneja aparte con el
 * SoftDeletes nativo de Laravel en los modelos que 01 marca con baja lógica.
 *
 * Cada modelo que use este trait debe declarar su prefijo de tabla:
 *   protected string $auditPrefix = 'CONV';
 */
trait ConAuditoria
{
    protected static function bootConAuditoria(): void
    {
        static::creating(function ($model): void {
            $prefix = $model->auditPrefix;
            $userId = auth()->id();

            $model->{"{$prefix}_ACTIVO"} = $model->{"{$prefix}_ACTIVO"} ?? true;
            $model->{"{$prefix}_CREADO_EN"} = now();
            $model->{"{$prefix}_CREADO_POR"} = $userId;
            $model->{"{$prefix}_ACTUALIZADO_EN"} = now();
            $model->{"{$prefix}_ACTUALIZADO_POR"} = $userId;
        });

        static::updating(function ($model): void {
            $prefix = $model->auditPrefix;

            $model->{"{$prefix}_ACTUALIZADO_EN"} = now();
            $model->{"{$prefix}_ACTUALIZADO_POR"} = auth()->id();
        });
    }
}
