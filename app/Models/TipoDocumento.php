<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

class TipoDocumento extends Model
{
    use ConAuditoria;

    protected $table = 'TIPO_DOCUMENTO';

    protected $primaryKey = 'TIDO_ID';

    public $timestamps = false;

    protected string $auditPrefix = 'TIDO';

    protected $fillable = [
        'TIDO_CODIGO',
        'TIDO_NOMBRE',
        'TIDO_LONGITUD',
    ];
}
