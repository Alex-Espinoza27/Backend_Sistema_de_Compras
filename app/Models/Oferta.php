<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Oferta extends Model
{
    use ConAuditoria, SoftDeletes;

    protected $table = 'OFERTA';

    protected $primaryKey = 'OFER_ID';

    public $timestamps = false;

    const DELETED_AT = 'OFER_ELIMINADO_EN';

    protected string $auditPrefix = 'OFER';

    protected $fillable = [
        'OFER_CONVOCATORIA_ID',
        'OFER_PROVEEDOR_ID',
        'OFER_ESTADO_ID',
        'OFER_TIPO_COMPROBANTE_ID',
        'OFER_TOTAL_GENERAL',
        'OFER_VALIDEZ_DIAS',
        'OFER_PLAZO_EJECUCION_SEGUN_TDR',
        'OFER_PLAZO_EJECUCION_DIAS',
        'OFER_FORMA_PAGO_SEGUN_TDR',
        'OFER_FORMA_PAGO_DIAS',
        'OFER_PLAZO_GARANTIA',
        'OFER_CONTACTO_NOMBRE',
        'OFER_CONTACTO_TELEFONO',
        'OFER_CONTACTO_CORREO',
        'OFER_CONSTANCIA_CODIGO',
        'OFER_FECHA_ENVIO',
        'OFER_EVAL_TECNICA',
        'OFER_EVAL_TECNICA_MOTIVO',
        'OFER_EVAL_LOGISTICA',
        'OFER_EVAL_LOGISTICA_MOTIVO',
        'OFER_EVALUADA_POR',
        'OFER_FECHA_EVALUACION',
    ];

    protected function casts(): array
    {
        return [
            'OFER_TOTAL_GENERAL' => 'decimal:2',
            'OFER_PLAZO_EJECUCION_SEGUN_TDR' => 'boolean',
            'OFER_FORMA_PAGO_SEGUN_TDR' => 'boolean',
            'OFER_FECHA_ENVIO' => 'datetime',
            'OFER_FECHA_EVALUACION' => 'datetime',
        ];
    }

    public function convocatoria(): BelongsTo
    {
        return $this->belongsTo(Convocatoria::class, 'OFER_CONVOCATORIA_ID');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'OFER_PROVEEDOR_ID');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'OFER_ESTADO_ID');
    }

    public function tipoComprobante(): BelongsTo
    {
        return $this->belongsTo(TipoComprobante::class, 'OFER_TIPO_COMPROBANTE_ID');
    }

    public function evaluadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'OFER_EVALUADA_POR');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OfertaItem::class, 'OFIT_OFERTA_ID');
    }
}
