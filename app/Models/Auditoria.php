<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solo inserción (01 §4.5): no usa ConAuditoria (no tiene columnas _ACTUALIZADO_EN/_POR
 * ni _ACTIVO); ya trae su propio AUDI_FECHA/AUDI_USER_ID. La escribe AuditoriaService.
 */
class Auditoria extends Model
{
    protected $table = 'AUDITORIA';

    protected $primaryKey = 'AUDI_ID';

    public $timestamps = false;

    protected $fillable = [
        'AUDI_USER_ID',
        'AUDI_MODULO',
        'AUDI_ACCION',
        'AUDI_TABLA',
        'AUDI_REGISTRO_ID',
        'AUDI_VALOR_ANTERIOR',
        'AUDI_VALOR_NUEVO',
        'AUDI_IP',
        'AUDI_USER_AGENT',
        'AUDI_ENDPOINT',
        'AUDI_FECHA',
    ];

    protected function casts(): array
    {
        return [
            'AUDI_VALOR_ANTERIOR' => 'array',
            'AUDI_VALOR_NUEVO' => 'array',
            'AUDI_FECHA' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'AUDI_USER_ID');
    }
}
