<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Entidad extends Model
{
    use ConAuditoria, SoftDeletes;

    protected $table = 'ENTIDAD';

    protected $primaryKey = 'ENTI_ID';

    public $timestamps = false;

    const DELETED_AT = 'ENTI_ELIMINADO_EN';

    protected string $auditPrefix = 'ENTI';

    protected $fillable = [
        'ENTI_TIPO_DOCUMENTO_ID',
        'ENTI_NUMERO_DOCUMENTO',
        'ENTI_RAZON_SOCIAL',
        'ENTI_SIGLA',
        'ENTI_CORREO',
        'ENTI_TELEFONO',
        'ENTI_DIRECCION',
        'ENTI_PERSONA_CONTACTO',
        'ENTI_ESTADO_ID',
        'ENTI_MOTIVO_OBSERVACION',
        'ENTI_FECHA_SOLICITUD',
        'ENTI_FECHA_RESOLUCION',
        'ENTI_RESUELTA_POR',
        'ENTI_CORRELATIVO_CONVOCATORIA',
        'ENTI_CORRELATIVO_ANIO',
    ];

    protected function casts(): array
    {
        return [
            'ENTI_FECHA_SOLICITUD' => 'datetime',
            'ENTI_FECHA_RESOLUCION' => 'datetime',
        ];
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'ENTI_TIPO_DOCUMENTO_ID');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'ENTI_ESTADO_ID');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'entidad_id');
    }

    public function requerimientos(): HasMany
    {
        return $this->hasMany(Requerimiento::class, 'REQU_ENTIDAD_ID');
    }

    public function convocatorias(): HasMany
    {
        return $this->hasMany(Convocatoria::class, 'CONV_ENTIDAD_ID');
    }
}
