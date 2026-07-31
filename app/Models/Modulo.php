<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

class Modulo extends Model
{
    use ConAuditoria;

    protected $table = 'MODULO';

    protected $primaryKey = 'MODU_ID';

    public $timestamps = false;

    protected string $auditPrefix = 'MODU';

    protected $fillable = [
        'MODU_CODIGO',
        'MODU_NOMBRE',
        'MODU_ICONO',
        'MODU_ORDEN',
    ];
}
