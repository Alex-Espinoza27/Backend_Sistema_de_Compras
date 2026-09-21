<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Rubro extends Model
{
    use ConAuditoria;

    protected $table = 'RUBRO';

    protected $primaryKey = 'RUBR_ID';

    public $timestamps = false;

    protected string $auditPrefix = 'RUBR';

    protected $fillable = [
        'RUBR_TIPO',
        'RUBR_CODIGO',
        'RUBR_NOMBRE',
        'RUBR_ACTIVO',
    ];

    protected function casts(): array
    {
        return [
            'RUBR_ACTIVO' => 'boolean',
        ];
    }

    public function proveedores(): BelongsToMany
    {
        return $this->belongsToMany(
            Proveedor::class,
            'PROVEEDOR_RUBRO',
            'PRRU_RUBRO_ID',
            'PRRU_PROVEEDOR_ID',
        );
    }
}
