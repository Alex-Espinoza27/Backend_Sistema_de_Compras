<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'TIDO_ACTIVO',
    ];

    protected function casts(): array
    {
        return [
            'TIDO_ACTIVO' => 'boolean',
        ];
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'tipo_documento_id');
    }
}
