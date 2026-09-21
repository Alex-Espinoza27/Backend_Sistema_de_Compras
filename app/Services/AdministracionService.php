<?php

namespace App\Services;

use App\Mail\NotificacionInstitucional;
use App\Models\Configuracion;
use App\Models\Estado;
use App\Models\Proveedor;
use App\Models\TipoDocumento;
use App\Models\Uit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Reglas de M01 (Administración): usuarios internos, reactivación de
 * proveedor, roles/permisos. El correo de credenciales se envía después de
 * confirmar la transacción, nunca dentro de ella (ver nota en EntidadService).
 */
class AdministracionService
{
    public function __construct(
        private EstadoService $estadoService,
        private AuditoriaService $auditoria,
    ) {}

    /** Alta de usuario de plataforma (05C hardening — módulo "Usuarios"
     * unificado: internos + Entidad, antes solo ADMINISTRADOR/AUDITOR).
     * `entidad_id` solo aplica si `rol === 'ENTIDAD'` (ya validado en el
     * Request). Mismo patrón que `EntidadService::crearFilaUsuario`. */
    public function crearUsuarioPlataforma(array $datos): User
    {
        $nombreCompleto = trim("{$datos['nombres']} {$datos['apellido_paterno']} {$datos['apellido_materno']}");

        $user = DB::transaction(function () use ($datos, $nombreCompleto) {
            $user = User::create([
                'name' => $nombreCompleto,
                'email' => $datos['correo'],
                'dni' => $datos['dni'] ?? null,
                'nombres' => $datos['nombres'] ?? null,
                'apellido_paterno' => $datos['apellido_paterno'] ?? null,
                'apellido_materno' => $datos['apellido_materno'] ?? null,
                'sexo' => $datos['sexo'] ?? null,
                'password' => Str::random(32),
                'entidad_id' => $datos['rol'] === 'ENTIDAD' ? $datos['entidad_id'] : null,
            ]);

            $user->assignRole($datos['rol']);

            $this->auditoria->registrar('ADMINISTRACION', 'CREAR', 'users', $user->id);

            return $user;
        });

        Password::sendResetLink(['email' => $user->email]);

        return $user;
    }

    public function actualizarUsuarioPlataforma(User $usuario, array $datos): User
    {
        $valorAnterior = $usuario->only(['name', 'email', 'dni', 'nombres', 'apellido_paterno', 'apellido_materno', 'sexo']);

        $nombreCompleto = trim("{$datos['nombres']} {$datos['apellido_paterno']} {$datos['apellido_materno']}");

        $usuario->update([
            'name' => $nombreCompleto,
            'email' => $datos['correo'],
            'dni' => $datos['dni'] ?? null,
            'nombres' => $datos['nombres'] ?? null,
            'apellido_paterno' => $datos['apellido_paterno'] ?? null,
            'apellido_materno' => $datos['apellido_materno'] ?? null,
            'sexo' => $datos['sexo'] ?? null,
        ]);

        if (! empty($datos['rol'])) {
            $usuario->syncRoles([$datos['rol']]);
            $usuario->update(['entidad_id' => $datos['rol'] === 'ENTIDAD' ? $datos['entidad_id'] : null]);
        }

        $this->auditoria->registrar('ADMINISTRACION', 'ACTUALIZAR', 'users', $usuario->id, $valorAnterior, $usuario->only(['name', 'email', 'dni', 'nombres', 'apellido_paterno', 'apellido_materno', 'sexo']));

        return $usuario;
    }

    public function eliminarUsuarioPlataforma(User $usuario): void
    {
        if ($usuario->hasRole('ADMINISTRADOR') && User::role('ADMINISTRADOR')->count() <= 1) {
            throw new HttpException(409, 'No se puede eliminar al último administrador.');
        }

        $userId = $usuario->id;
        $usuario->delete();

        $this->auditoria->registrar('ADMINISTRACION', 'ELIMINAR', 'users', $userId);
    }

    public function reactivarProveedor(Proveedor $proveedor): Proveedor
    {
        $this->estadoService->transicionar(
            modelo: $proveedor,
            columnaEstado: 'PROV_ESTADO_ID',
            contexto: 'PROVEEDOR',
            codigoDestino: 'ACTIVO',
            transicionesPermitidas: ['SUSPENDIDO' => ['ACTIVO']],
            modulo: 'ADMINISTRACION',
            tabla: 'PROVEEDOR',
        );

        return $proveedor->fresh();
    }

    /** Espejo de `reactivarProveedor` — suspensión iniciada por un
     * administrador (05C hardening), a diferencia de la auto-suspensión de
     * `ProveedorController::suspender` no cierra ninguna sesión (no es la
     * del propio proveedor la que está actuando). */
    public function suspenderProveedor(Proveedor $proveedor): Proveedor
    {
        $this->estadoService->transicionar(
            modelo: $proveedor,
            columnaEstado: 'PROV_ESTADO_ID',
            contexto: 'PROVEEDOR',
            codigoDestino: 'SUSPENDIDO',
            transicionesPermitidas: ['ACTIVO' => ['SUSPENDIDO']],
            modulo: 'ADMINISTRACION',
            tabla: 'PROVEEDOR',
        );

        return $proveedor->fresh();
    }

    /** Alta directa de proveedor por un administrador (05C hardening) —
     * mismo patrón que `AuthController::registro` (crea `User`+`Proveedor`
     * en una transacción) pero sin captcha/password/consulta SUNAT: la
     * cuenta se activa con contraseña aleatoria + enlace de restablecimiento. */
    public function crearProveedor(array $datos): Proveedor
    {
        $proveedor = DB::transaction(function () use ($datos) {
            $user = User::create([
                'name' => $datos['razon_social'],
                'email' => $datos['correo'],
                'password' => Str::random(32),
            ]);
            $user->assignRole('PROVEEDOR');

            $tipoDocumento = TipoDocumento::where('TIDO_CODIGO', $datos['tipo_documento'])->firstOrFail();
            $estadoActivo = Estado::where('ESTA_CONTEXTO', 'PROVEEDOR')->where('ESTA_CODIGO', 'ACTIVO')->firstOrFail();

            $proveedor = Proveedor::create([
                'PROV_USER_ID' => $user->id,
                'PROV_TIPO_DOCUMENTO_ID' => $tipoDocumento->TIDO_ID,
                'PROV_NUMERO_DOCUMENTO' => $datos['numero_documento'],
                'PROV_TIPO_PERSONA' => $datos['tipo_persona'],
                'PROV_RAZON_SOCIAL' => $datos['razon_social'],
                'PROV_CORREO' => $datos['correo'],
                'PROV_TELEFONO' => $datos['telefono'],
                'PROV_PERSONA_CONTACTO' => $datos['persona_contacto'],
                'PROV_ESTADO_ID' => $estadoActivo->ESTA_ID,
                'PROV_ORIGEN_DATO' => 'MANUAL',
            ]);

            $rubroIds = array_merge($datos['rubros_servicios'] ?? [], $datos['rubros_bienes'] ?? []);
            $proveedor->rubros()->attach($rubroIds);

            $this->auditoria->registrar('ADMINISTRACION', 'CREAR', 'PROVEEDOR', $proveedor->PROV_ID, userId: $user->id);

            return $proveedor;
        });

        Password::sendResetLink(['email' => $proveedor->PROV_CORREO]);

        return $proveedor;
    }

    public function actualizarProveedor(Proveedor $proveedor, array $datos): Proveedor
    {
        $valorAnterior = $proveedor->only(['PROV_NUMERO_DOCUMENTO', 'PROV_RAZON_SOCIAL', 'PROV_TIPO_PERSONA', 'PROV_CORREO', 'PROV_TELEFONO', 'PROV_PERSONA_CONTACTO']);

        DB::transaction(function () use ($proveedor, $datos) {
            $tipoDocumento = TipoDocumento::where('TIDO_CODIGO', $datos['tipo_documento'])->firstOrFail();

            $proveedor->update([
                'PROV_TIPO_DOCUMENTO_ID' => $tipoDocumento->TIDO_ID,
                'PROV_NUMERO_DOCUMENTO' => $datos['numero_documento'],
                'PROV_TIPO_PERSONA' => $datos['tipo_persona'],
                'PROV_RAZON_SOCIAL' => $datos['razon_social'],
                'PROV_CORREO' => $datos['correo'],
                'PROV_TELEFONO' => $datos['telefono'],
                'PROV_PERSONA_CONTACTO' => $datos['persona_contacto'],
            ]);

            if ($proveedor->PROV_USER_ID) {
                User::where('id', $proveedor->PROV_USER_ID)->update([
                    'name' => $datos['razon_social'],
                    'email' => $datos['correo'],
                ]);
            }

            $rubroIds = array_merge($datos['rubros_servicios'] ?? [], $datos['rubros_bienes'] ?? []);
            $proveedor->rubros()->sync($rubroIds);
        });

        $this->auditoria->registrar(
            'ADMINISTRACION',
            'ACTUALIZAR',
            'PROVEEDOR',
            $proveedor->PROV_ID,
            $valorAnterior,
            $proveedor->fresh()->only(['PROV_NUMERO_DOCUMENTO', 'PROV_RAZON_SOCIAL', 'PROV_TIPO_PERSONA', 'PROV_CORREO', 'PROV_TELEFONO', 'PROV_PERSONA_CONTACTO']),
        );

        return $proveedor->fresh();
    }

    /** Pública (05C hardening): antes también vivía duplicada en
     * `RolStoreRequest`, que ahora la referencia desde acá — una sola fuente
     * de verdad, y el módulo Roles y Permisos también la usa para exponer
     * `es_reservado` en `GET /admin/roles`. */
    public const ROLES_RESERVADOS = ['ADMINISTRADOR', 'AUDITOR', 'ENTIDAD', 'PROVEEDOR'];

    /** Crea un rol nuevo tipo "staff interno" genérico (05C hardening) — solo
     * con los permisos que se le asignen, sin scoping por entidad ni acceso
     * a rutas de proveedor (esos comportamientos solo miran el nombre exacto
     * `ENTIDAD`/`PROVEEDOR` en varios sitios del código, no se generalizan). */
    public function crearRol(array $datos): Role
    {
        $rol = Role::create(['name' => $datos['nombre'], 'guard_name' => 'web']);
        $rol->syncPermissions($datos['permisos'] ?? []);

        $this->auditoria->registrar('ADMINISTRACION', 'CREAR', 'roles', $rol->id);

        return $rol->fresh('permissions');
    }

    /** Desactivar en vez de eliminar (05C hardening, pedido explícitamente
     * por el usuario): un rol desactivado deja de ofrecerse para
     * asignaciones nuevas, pero no afecta a quienes ya lo tienen (decisión
     * confirmada con el usuario) — por eso no hace falta bloquear cuando
     * tiene usuarios asignados, a diferencia de lo que exigía eliminar. */
    public function cambiarActivoRol(string $rol, bool $activo): Role
    {
        if (! $activo && in_array($rol, self::ROLES_RESERVADOS, true)) {
            throw new HttpException(409, 'No se puede desactivar un rol del sistema.');
        }

        $roleModel = Role::where('name', $rol)->where('guard_name', 'web')->firstOrFail();
        $valorAnterior = $roleModel->activo;

        $roleModel->update(['activo' => $activo]);

        $this->auditoria->registrar(
            'ADMINISTRACION',
            'ACTUALIZAR',
            'roles',
            $roleModel->id,
            ['activo' => $valorAnterior],
            ['activo' => $activo],
        );

        return $roleModel->fresh('permissions');
    }

    public function actualizarPermisosRol(string $rol, array $permisos): Role
    {
        $roleModel = Role::where('name', $rol)->where('guard_name', 'web')->firstOrFail();
        $valorAnterior = $roleModel->permissions()->pluck('name')->all();

        $roleModel->syncPermissions($permisos);

        $this->auditoria->registrar(
            'ADMINISTRACION',
            'ACTUALIZAR',
            'roles',
            $roleModel->id,
            ['permisos' => $valorAnterior],
            ['permisos' => $permisos],
        );

        return $roleModel->fresh('permissions');
    }

    private const CLAVES_CORREO = [
        'SMTP_HOST', 'SMTP_PUERTO', 'SMTP_USUARIO', 'SMTP_PASSWORD', 'SMTP_CIFRADO',
        'CORREO_REMITENTE', 'CORREO_REMITENTE_NOMBRE',
    ];

    public function actualizarConfiguracion(array $configuraciones): void
    {
        $tocaConfiguracionCorreo = false;

        DB::transaction(function () use ($configuraciones, &$tocaConfiguracionCorreo) {
            foreach ($configuraciones as $item) {
                $config = Configuracion::where('CONF_CLAVE', $item['clave'])->firstOrFail();
                $valorAnterior = $config->CONF_VALOR;

                // El middleware global ConvertEmptyStringsToNull convierte
                // "" a null antes de llegar aquí — se normaliza de vuelta a
                // "" (CONF_VALOR nunca debe quedar null a propósito).
                $valor = $item['valor'] ?? '';

                // CONF_TIPO=SECRETO (credenciales SMTP, 05C hardening): se
                // guarda cifrado con la APP_KEY, nunca en texto plano (salvo
                // que se limpie a vacío a propósito, que se guarda tal cual
                // para que quede consistentemente "sin definir"). El valor
                // anterior tampoco se expone en la auditoría.
                $esSecreto = $config->CONF_TIPO === 'SECRETO';
                $nuevoValor = ($esSecreto && $valor !== '') ? Crypt::encryptString($valor) : $valor;
                $config->update(['CONF_VALOR' => $nuevoValor]);

                if (in_array($config->CONF_CLAVE, self::CLAVES_CORREO, true)) {
                    $tocaConfiguracionCorreo = true;
                }

                $this->auditoria->registrar(
                    'ADMINISTRACION',
                    'ACTUALIZAR',
                    'CONFIGURACION',
                    $config->CONF_ID,
                    ['valor' => $esSecreto ? '••••••••' : $valorAnterior],
                    ['valor' => $esSecreto ? '••••••••' : $valor],
                );
            }
        });

        // El worker de colas (`queue:work`) carga la config de correo una
        // sola vez al arrancar y la reutiliza mientras vive el proceso — sin
        // esto, un cambio de credenciales SMTP queda "guardado" pero el
        // worker sigue usando las viejas hasta que alguien lo reinicia a
        // mano (bug real detectado en producción: la key se actualizaba en
        // BD pero los correos seguían fallando con credenciales viejas).
        // `queue:restart` señaliza al worker para que se reinicie solo tras
        // terminar el job en curso; requiere que el proceso lo supervise y
        // lo vuelva a levantar (`restart: unless-stopped` en docker-compose).
        if ($tocaConfiguracionCorreo) {
            Artisan::call('queue:restart');
        }
    }

    /** Envía de forma síncrona (no encolada, a diferencia de
     * NotificacionService::enviarCorreo) para que un fallo de credenciales
     * SMTP se reporte de inmediato al admin, no quede silencioso en
     * `failed_jobs` (05C hardening, pedido explícitamente por el usuario). */
    public function enviarCorreoPrueba(string $destino): void
    {
        try {
            Mail::to($destino)->send(new NotificacionInstitucional(
                'Correo de prueba — Compras La Perla',
                'Este es un correo de prueba enviado desde la Configuración de la plataforma. '
                .'Si lo recibiste, las credenciales SMTP configuradas funcionan correctamente.',
            ));
        } catch (\Throwable $e) {
            throw new HttpException(422, 'No se pudo enviar el correo de prueba: '.$e->getMessage());
        }
    }

    public function crearUit(array $datos): Uit
    {
        return DB::transaction(function () use ($datos) {
            if (! empty($datos['vigente'])) {
                Uit::where('UITA_VIGENTE', true)->update(['UITA_VIGENTE' => false]);
            }

            $uit = Uit::create([
                'UITA_ANIO' => $datos['anio'],
                'UITA_VALOR' => $datos['valor'],
                'UITA_VIGENTE' => $datos['vigente'] ?? false,
            ]);

            $this->auditoria->registrar('ADMINISTRACION', 'CREAR', 'UIT', $uit->UITA_ID);

            return $uit;
        });
    }

    public function actualizarUit(Uit $uit, array $datos): Uit
    {
        return DB::transaction(function () use ($uit, $datos) {
            $valorAnterior = $uit->only(['UITA_ANIO', 'UITA_VALOR', 'UITA_VIGENTE']);

            if (! empty($datos['vigente'])) {
                Uit::where('UITA_ID', '!=', $uit->UITA_ID)->update(['UITA_VIGENTE' => false]);
            }

            $uit->update([
                'UITA_ANIO' => $datos['anio'] ?? $uit->UITA_ANIO,
                'UITA_VALOR' => $datos['valor'] ?? $uit->UITA_VALOR,
                'UITA_VIGENTE' => $datos['vigente'] ?? $uit->UITA_VIGENTE,
            ]);

            $this->auditoria->registrar(
                'ADMINISTRACION',
                'ACTUALIZAR',
                'UIT',
                $uit->UITA_ID,
                $valorAnterior,
                $uit->only(['UITA_ANIO', 'UITA_VALOR', 'UITA_VIGENTE']),
            );

            return $uit;
        });
    }
}
