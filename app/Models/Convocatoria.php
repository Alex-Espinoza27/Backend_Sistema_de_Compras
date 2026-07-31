<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use App\Traits\DeSuEntidad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Convocatoria extends Model
{
    use ConAuditoria, DeSuEntidad, SoftDeletes;

    protected $table = 'CONVOCATORIA';

    protected $primaryKey = 'CONV_ID';

    public $timestamps = false;

    const DELETED_AT = 'CONV_ELIMINADO_EN';

    protected string $auditPrefix = 'CONV';

    protected string $entidadColumn = 'CONV_ENTIDAD_ID';

    protected $fillable = [
        'CONV_REQUERIMIENTO_ID',
        'CONV_ENTIDAD_ID',
        'CONV_NUMERO',
        'CONV_NOMBRE_SERVICIO',
        'CONV_FECHA_INICIO',
        'CONV_FECHA_FIN',
        'CONV_ESTADO_ID',
        'CONV_MOTIVO_CIERRE',
        'CONV_OFERTA_ADJUDICADA_ID',
        'CONV_FECHA_PUBLICACION',
        'CONV_FECHA_APERTURA',
        'CONV_FECHA_RESULTADO',
    ];

    protected function casts(): array
    {
        return [
            'CONV_FECHA_INICIO' => 'datetime',
            'CONV_FECHA_FIN' => 'datetime',
            'CONV_FECHA_PUBLICACION' => 'datetime',
            'CONV_FECHA_APERTURA' => 'datetime',
            'CONV_FECHA_RESULTADO' => 'datetime',
        ];
    }

    public function requerimiento(): BelongsTo
    {
        return $this->belongsTo(Requerimiento::class, 'CONV_REQUERIMIENTO_ID');
    }

    public function entidad(): BelongsTo
    {
        return $this->belongsTo(Entidad::class, 'CONV_ENTIDAD_ID');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'CONV_ESTADO_ID');
    }

    public function ofertaAdjudicada(): BelongsTo
    {
        return $this->belongsTo(Oferta::class, 'CONV_OFERTA_ADJUDICADA_ID');
    }

    public function ofertas(): HasMany
    {
        return $this->hasMany(Oferta::class, 'OFER_CONVOCATORIA_ID');
    }
}
