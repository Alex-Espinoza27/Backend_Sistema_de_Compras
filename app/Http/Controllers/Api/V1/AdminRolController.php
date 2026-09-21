<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActualizarPermisosRolRequest;
use App\Http\Requests\Admin\RolStoreRequest;
use App\Models\Modulo;
use App\Models\User;
use App\Services\AdministracionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminRolController extends Controller
{
    public function __construct(private AdministracionService $administracionService) {}

    public function roles(): JsonResponse
    {
        $roles = Role::where('guard_name', 'web')
            ->with('permissions:id,name')
            ->get(['id', 'name', 'activo'])
            ->map(fn (Role $rol) => $this->formatearRol($rol));

        return ApiResponse::success($roles);
    }

    /** Mismo shape en `roles()`, `crear()` y `actualizarPermisos()` (05C
     * hardening, pedido explícitamente por el usuario) — antes estos dos
     * últimos devolvían solo `rol`/`permisos`, así que la tabla de roles del
     * frontend se quedaba con `permisos_count`/`usuarios_count`/`es_reservado`
     * a medias hasta el siguiente refresh. */
    private function formatearRol(Role $rol): array
    {
        return [
            'rol' => $rol->name,
            'permisos' => $rol->permissions->pluck('name')->values(),
            'permisos_count' => $rol->permissions->count(),
            'usuarios_count' => User::role($rol->name)->count(),
            'es_reservado' => in_array($rol->name, AdministracionService::ROLES_RESERVADOS, true),
            'activo' => (bool) $rol->activo,
        ];
    }

    public function permisos(): JsonResponse
    {
        // Nombre amigable del módulo (05C hardening, pedido explícitamente
        // por el usuario): antes el frontend mostraba el código crudo
        // ("GESTION_PROVEEDORES") en vez del nombre real del módulo
        // ("Proveedores"), que ya vive en MODULO.MODU_NOMBRE.
        $nombresPorCodigo = Modulo::pluck('MODU_NOMBRE', 'MODU_CODIGO');

        $permisos = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Permission $p) use ($nombresPorCodigo) {
                $codigoModulo = strtoupper(explode('.', $p->name)[0]);

                return [
                    'permiso' => $p->name,
                    'modulo' => $codigoModulo,
                    'modulo_nombre' => $nombresPorCodigo[$codigoModulo] ?? $codigoModulo,
                ];
            });

        return ApiResponse::success($permisos);
    }

    public function actualizarPermisos(ActualizarPermisosRolRequest $request, string $rol): JsonResponse
    {
        $rolActualizado = $this->administracionService->actualizarPermisosRol($rol, $request->input('permisos'));

        return ApiResponse::success($this->formatearRol($rolActualizado), 'Permisos actualizados.');
    }

    public function crear(RolStoreRequest $request): JsonResponse
    {
        $rol = $this->administracionService->crearRol($request->validated());

        return ApiResponse::success($this->formatearRol($rol), 'Rol creado.', 201);
    }

    public function activar(string $rol): JsonResponse
    {
        $rolActualizado = $this->administracionService->cambiarActivoRol($rol, true);

        return ApiResponse::success($this->formatearRol($rolActualizado), 'Rol activado.');
    }

    public function desactivar(string $rol): JsonResponse
    {
        $rolActualizado = $this->administracionService->cambiarActivoRol($rol, false);

        return ApiResponse::success($this->formatearRol($rolActualizado), 'Rol desactivado.');
    }
}
