<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

class Estado extends Model
{
    use ConAuditoria;

    protected $table = 'ESTADO';

    protected $primaryKey = 'ESTA_ID';

    public $timestamps = false;

    protected string $auditPrefix = 'ESTA';

    protected $fillable = [
        'ESTA_CONTEXTO',
        'ESTA_CODIGO',
        'ESTA_NOMBRE',
        'ESTA_COLOR',
        'ESTA_ORDEN',
    ];
}
