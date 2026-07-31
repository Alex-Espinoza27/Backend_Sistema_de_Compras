<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfertaItem extends Model
{
    use ConAuditoria;

    protected $table = 'OFERTA_ITEM';

    protected $primaryKey = 'OFIT_ID';

    public $timestamps = false;

    protected string $auditPrefix = 'OFIT';

    protected $fillable = [
        'OFIT_OFERTA_ID',
        'OFIT_REQUERIMIENTO_ITEM_ID',
        'OFIT_MARCA_MODELO',
        'OFIT_PRECIO_UNITARIO',
        'OFIT_PRECIO_TOTAL',
    ];

    protected function casts(): array
    {
        return [
            'OFIT_PRECIO_UNITARIO' => 'decimal:2',
            'OFIT_PRECIO_TOTAL' => 'decimal:2',
        ];
    }

    public function oferta(): BelongsTo
    {
        return $this->belongsTo(Oferta::class, 'OFIT_OFERTA_ID');
    }

    public function requerimientoItem(): BelongsTo
    {
        return $this->belongsTo(RequerimientoItem::class, 'OFIT_REQUERIMIENTO_ITEM_ID');
    }
}
