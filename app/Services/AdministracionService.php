<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Estado;
use App\Models\Proveedor;
use App\Models\TipoDocumento;
use App\Models\Uit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        $user = DB::transaction(function () use ($datos) {
            $user = User::create([
                'name' => $datos['nombre'],
                'email' => $datos['correo'],
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
        $valorAnterior = $usuario->only(['name', 'email']);

        $usuario->update(['name' => $datos['nombre'], 'email' => $datos['correo']]);

        if (! empty($datos['rol'])) {
            $usuario->syncRoles([$datos['rol']]);
            $usuario->update(['entidad_id' => $datos['rol'] === 'ENTIDAD' ? $datos['entidad_id'] : null]);
        }

        $this->auditoria->registrar('ADMINISTRACION', 'ACTUALIZAR', 'users', $usuario->id, $valorAnterior, $usuario->only(['name', 'email']));

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

    private const ROLES_RESERVADOS = ['ADMINISTRADOR', 'AUDITOR', 'ENTIDAD', 'PROVEEDOR'];

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

    public function eliminarRol(string $rol): void
    {
        if (in_array($rol, self::ROLES_RESERVADOS, true)) {
            throw new HttpException(409, 'No se puede eliminar un rol del sistema.');
        }

        $roleModel = Role::where('name', $rol)->where('guard_name', 'web')->firstOrFail();

        if (User::role($rol)->count() > 0) {
            throw new HttpException(409, 'No se puede eliminar un rol con usuarios asignados.');
        }

        $rolId = $roleModel->id;
        $roleModel->delete();

        $this->auditoria->registrar('ADMINISTRACION', 'ELIMINAR', 'roles', $rolId);
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

    public function actualizarConfiguracion(array $configuraciones): void
    {
        DB::transaction(function () use ($configuraciones) {
            foreach ($configuraciones as $item) {
                $config = Configuracion::where('CONF_CLAVE', $item['clave'])->firstOrFail();
                $valorAnterior = $config->CONF_VALOR;

                $config->update(['CONF_VALOR' => $item['valor']]);

                $this->auditoria->registrar(
                    'ADMINISTRACION',
                    'ACTUALIZAR',
                    'CONFIGURACION',
                    $config->CONF_ID,
                    ['valor' => $valorAnterior],
                    ['valor' => $item['valor']],
                );
            }
        });
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
