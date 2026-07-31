<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

class ProveedorRubro extends Model
{
    use ConAuditoria;

    protected $table = 'PROVEEDOR_RUBRO';

    protected $primaryKey = 'PRRU_ID';

    public $timestamps = false;

    protected string $auditPrefix = 'PRRU';

    protected $fillable = [
        'PRRU_PROVEEDOR_ID',
        'PRRU_RUBRO_ID',
    ];
}
