<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proveedor extends Model
{
    use ConAuditoria, SoftDeletes;

    protected $table = 'PROVEEDOR';

    protected $primaryKey = 'PROV_ID';

    public $timestamps = false;

    const DELETED_AT = 'PROV_ELIMINADO_EN';

    protected string $auditPrefix = 'PROV';

    protected $fillable = [
        'PROV_USER_ID',
        'PROV_TIPO_DOCUMENTO_ID',
        'PROV_NUMERO_DOCUMENTO',
        'PROV_TIPO_PERSONA',
        'PROV_RAZON_SOCIAL',
        'PROV_CORREO',
        'PROV_TELEFONO',
        'PROV_PERSONA_CONTACTO',
        'PROV_ESTADO_ID',
        'PROV_RESPUESTA_SUNAT',
        'PROV_ORIGEN_DATO',
    ];

    protected function casts(): array
    {
        return [
            'PROV_RESPUESTA_SUNAT' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'PROV_USER_ID');
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'PROV_TIPO_DOCUMENTO_ID');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'PROV_ESTADO_ID');
    }

    public function rubros(): BelongsToMany
    {
        return $this->belongsToMany(
            Rubro::class,
            'PROVEEDOR_RUBRO',
            'PRRU_PROVEEDOR_ID',
            'PRRU_RUBRO_ID',
        );
    }

    public function ofertas(): HasMany
    {
        return $this->hasMany(Oferta::class, 'OFER_PROVEEDOR_ID');
    }
}
