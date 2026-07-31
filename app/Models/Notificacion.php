<?php

namespace App\Models;

use App\Traits\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    use ConAuditoria;

    protected $table = 'NOTIFICACION';

    protected $primaryKey = 'NOTI_ID';

    public $timestamps = false;

    protected string $auditPrefix = 'NOTI';

    protected $fillable = [
        'NOTI_USER_ID',
        'NOTI_TIPO',
        'NOTI_TITULO',
        'NOTI_MENSAJE',
        'NOTI_LEIDA',
        'NOTI_REFERENCIA_TIPO',
        'NOTI_REFERENCIA_ID',
    ];

    protected function casts(): array
    {
        return [
            'NOTI_LEIDA' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'NOTI_USER_ID');
    }
}
