<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

class TipoComprobante extends Model
{
    use ConAuditoria;

    protected $table = 'TIPO_COMPROBANTE';

    protected $primaryKey = 'TICO_ID';

    public $timestamps = false;

    protected string $auditPrefix = 'TICO';

    protected $fillable = [
        'TICO_CODIGO',
        'TICO_NOMBRE',
        'TICO_INCLUYE_IGV',
        'TICO_ACTIVO',
    ];

    protected function casts(): array
    {
        return [
            'TICO_INCLUYE_IGV' => 'boolean',
            'TICO_ACTIVO' => 'boolean',
        ];
    }
}
