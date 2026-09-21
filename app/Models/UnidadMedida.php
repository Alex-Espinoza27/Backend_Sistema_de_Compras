<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    use ConAuditoria;

    protected $table = 'UNIDAD_MEDIDA';

    protected $primaryKey = 'UNME_ID';

    public $timestamps = false;

    protected string $auditPrefix = 'UNME';

    protected $fillable = [
        'UNME_CODIGO',
        'UNME_NOMBRE',
        'UNME_ACTIVO',
    ];

    protected function casts(): array
    {
        return [
            'UNME_ACTIVO' => 'boolean',
        ];
    }
}
