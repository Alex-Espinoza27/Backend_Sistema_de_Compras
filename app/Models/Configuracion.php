<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    use ConAuditoria;

    protected $table = 'CONFIGURACION';

    protected $primaryKey = 'CONF_ID';

    public $timestamps = false;

    protected string $auditPrefix = 'CONF';

    protected $fillable = [
        'CONF_CLAVE',
        'CONF_VALOR',
        'CONF_TIPO',
        'CONF_DESCRIPCION',
    ];
}
