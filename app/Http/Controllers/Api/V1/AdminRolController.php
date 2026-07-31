<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActualizarPermisosRolRequest;
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
            ->get(['id', 'name'])
            ->map(fn (Role $rol) => [
                'rol' => $rol->name,
                'permisos' => $rol->permissions->pluck('name')->values(),
            ]);

        return ApiResponse::success($roles);
    }

    public function permisos(): JsonResponse
    {
        $permisos = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Permission $p) => [
                'permiso' => $p->name,
                'modulo' => strtoupper(explode('.', $p->name)[0]),
            ]);

        return ApiResponse::success($permisos);
    }

    public function actualizarPermisos(ActualizarPermisosRolRequest $request, string $rol): JsonResponse
    {
        $rolActualizado = $this->administracionService->actualizarPermisosRol($rol, $request->input('permisos'));

        return ApiResponse::success([
            'rol' => $rolActualizado->name,
            'permisos' => $rolActualizado->permissions->pluck('name')->values(),
        ], 'Permisos actualizados.');
    }
}
