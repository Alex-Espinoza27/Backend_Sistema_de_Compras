<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

class Archivo extends Model
{
    use ConAuditoria;

    protected $table = 'ARCHIVO';

    protected $primaryKey = 'ARCH_ID';

    public $timestamps = false;

    protected string $auditPrefix = 'ARCH';

    protected $fillable = [
        'ARCH_CONTEXTO',
        'ARCH_REFERENCIA_ID',
        'ARCH_NOMBRE_ORIGINAL',
        'ARCH_RUTA',
        'ARCH_MIME',
        'ARCH_TAMANO_BYTES',
        'ARCH_HASH_SHA256',
    ];
}
