<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequerimientoItem extends Model
{
    use ConAuditoria;

    protected $table = 'REQUERIMIENTO_ITEM';

    protected $primaryKey = 'REIT_ID';

    public $timestamps = false;

    protected string $auditPrefix = 'REIT';

    protected $fillable = [
        'REIT_REQUERIMIENTO_ID',
        'REIT_NUMERO',
        'REIT_DESCRIPCION',
        'REIT_UNIDAD_MEDIDA_ID',
        'REIT_CANTIDAD',
        'REIT_PRESENTACION',
    ];

    protected function casts(): array
    {
        return [
            'REIT_CANTIDAD' => 'decimal:2',
        ];
    }

    public function requerimiento(): BelongsTo
    {
        return $this->belongsTo(Requerimiento::class, 'REIT_REQUERIMIENTO_ID');
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'REIT_UNIDAD_MEDIDA_ID');
    }
}
