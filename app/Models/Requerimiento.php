<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use App\Traits\DeSuEntidad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Requerimiento extends Model
{
    use ConAuditoria, DeSuEntidad, SoftDeletes;

    protected $table = 'REQUERIMIENTO';

    protected $primaryKey = 'REQU_ID';

    public $timestamps = false;

    const DELETED_AT = 'REQU_ELIMINADO_EN';

    protected string $auditPrefix = 'REQU';

    protected string $entidadColumn = 'REQU_ENTIDAD_ID';

    protected $fillable = [
        'REQU_ENTIDAD_ID',
        'REQU_NUMERO',
        'REQU_OBJETO',
        'REQU_DESCRIPCION',
        'REQU_TIPO',
        'REQU_AREA_ID',
        'REQU_ACTIVO',
    ];

    protected function casts(): array
    {
        return [
            'REQU_ACTIVO' => 'boolean',
        ];
    }

    public function entidad(): BelongsTo
    {
        return $this->belongsTo(Entidad::class, 'REQU_ENTIDAD_ID');
    }

    /** `REQU_AREA_USUARIA` (columna legada, ya no se escribe) queda en la
     * tabla solo por seguridad de datos históricos (05C hardening). */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'REQU_AREA_ID');
    }

    /** Un requerimiento puede tener más de un rubro (05C hardening) —
     * `REQU_RUBRO_ID` (columna legada, ya no se escribe) queda en la tabla
     * solo por seguridad de datos históricos. */
    public function rubros(): BelongsToMany
    {
        return $this->belongsToMany(Rubro::class, 'REQUERIMIENTO_RUBRO', 'RERU_REQUERIMIENTO_ID', 'RERU_RUBRO_ID');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequerimientoItem::class, 'REIT_REQUERIMIENTO_ID');
    }

    public function convocatorias(): HasMany
    {
        return $this->hasMany(Convocatoria::class, 'CONV_REQUERIMIENTO_ID');
    }
}
