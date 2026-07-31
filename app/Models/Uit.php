<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

class Uit extends Model
{
    use ConAuditoria;

    protected $table = 'UIT';

    protected $primaryKey = 'UITA_ID';

    public $timestamps = false;

    protected string $auditPrefix = 'UITA';

    protected $fillable = [
        'UITA_ANIO',
        'UITA_VALOR',
        'UITA_VIGENTE',
    ];

    protected function casts(): array
    {
        return [
            'UITA_VALOR' => 'decimal:2',
            'UITA_VIGENTE' => 'boolean',
        ];
    }
}
