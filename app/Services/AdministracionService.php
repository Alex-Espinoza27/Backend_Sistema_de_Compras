<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Proveedor;
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

    public function crearUsuarioInterno(array $datos): User
    {
        $user = DB::transaction(function () use ($datos) {
            $user = User::create([
                'name' => $datos['nombre'],
                'email' => $datos['correo'],
                'password' => Str::random(32),
            ]);

            $user->assignRole($datos['rol']);

            $this->auditoria->registrar('ADMINISTRACION', 'CREAR', 'users', $user->id);

            return $user;
        });

        Password::sendResetLink(['email' => $user->email]);

        return $user;
    }

    public function actualizarUsuarioInterno(User $usuario, array $datos): User
    {
        $valorAnterior = $usuario->only(['name', 'email']);

        $usuario->update(['name' => $datos['nombre'], 'email' => $datos['correo']]);

        if (! empty($datos['rol'])) {
            $usuario->syncRoles([$datos['rol']]);
        }

        $this->auditoria->registrar('ADMINISTRACION', 'ACTUALIZAR', 'users', $usuario->id, $valorAnterior, $usuario->only(['name', 'email']));

        return $usuario;
    }

    public function eliminarUsuarioInterno(User $usuario): void
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
