<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use App\Traits\DeSuEntidad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catálogo de áreas usuarias por entidad (05C hardening) — reemplaza el
 * campo de texto libre `REQU_AREA_USUARIA` de Requerimientos. */
class Area extends Model
{
    use ConAuditoria, DeSuEntidad;

    protected $table = 'AREA';

    protected $primaryKey = 'AREA_ID';

    public $timestamps = false;

    protected string $auditPrefix = 'AREA';

    protected string $entidadColumn = 'AREA_ENTIDAD_ID';

    protected $fillable = [
        'AREA_ENTIDAD_ID',
        'AREA_NOMBRE',
        'AREA_SIGLA',
        'AREA_ACTIVO',
    ];

    protected function casts(): array
    {
        return [
            'AREA_ACTIVO' => 'boolean',
        ];
    }

    public function entidad(): BelongsTo
    {
        return $this->belongsTo(Entidad::class, 'AREA_ENTIDAD_ID');
    }
}
