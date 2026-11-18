<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Notifications\RestablecerPasswordNotification;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'activo',
        'dni',
        'tipo_documento_id',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'sexo',
        'celular',
        'fecha_nacimiento',
        'password',
        'entidad_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'fecha_nacimiento' => 'date',
            'activo' => 'boolean',
        ];
    }

    /** Enlace de alta/restablecimiento iniciado por un administrador (broker
     * `users`, 60 min). El de "Olvidé mi contraseña" usa el broker
     * `recuperacion` y arma su propia notificación en AuthController. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RestablecerPasswordNotification($token, (int) config('auth.passwords.users.expire'), solicitudPropia: false));
    }

    public function entidad(): BelongsTo
    {
        return $this->belongsTo(Entidad::class, 'entidad_id');
    }

    /** Roles que ven todas las entidades (o ninguna, el proveedor): nunca llevan entidad. */
    public const ROLES_SIN_ENTIDAD = ['ADMINISTRADOR', 'AUDITOR', 'PROVEEDOR'];

    /**
     * ¿Este usuario trabaja SOLO con los datos de su entidad?
     *
     * Sí si tiene rol ENTIDAD o si tiene una entidad asignada (cualquier rol
     * personalizado). Antes se miraba solo el nombre del rol `ENTIDAD`, y un
     * usuario con rol personalizado veía los datos de TODAS las entidades.
     * Un usuario ENTIDAD sin entidad (dato roto) sigue limitado: no ve nada.
     */
    public function limitadoAEntidad(): bool
    {
        return self::limitaAEntidad($this->getRoleNames()->all(), $this->entidad_id);
    }

    /** Su entidad si está limitado a ella; null si ve todas. */
    public function entidadPropiaId(): ?int
    {
        return $this->limitadoAEntidad() ? $this->entidad_id : null;
    }

    /** @param  list<string>  $roles */
    public static function limitaAEntidad(array $roles, ?int $entidadId): bool
    {
        if (array_intersect($roles, self::ROLES_SIN_ENTIDAD)) {
            return false;
        }

        return in_array('ENTIDAD', $roles, true) || $entidadId !== null;
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
    }

    public function proveedor(): HasOne
    {
        return $this->hasOne(Proveedor::class, 'PROV_USER_ID');
    }
}
