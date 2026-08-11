<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UsuarioPlataformaIndexRequest;
use App\Http\Requests\Admin\UsuarioPlataformaStoreRequest;
use App\Http\Requests\Admin\UsuarioPlataformaUpdateRequest;
use App\Models\User;
use App\Services\AdministracionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Mantenimiento de usuarios unificado (05C hardening) — reemplaza a
 * `AdminUsuarioController` (acotado antes a ADMINISTRADOR/AUDITOR). Incluye
 * cualquier rol excepto PROVEEDOR (ese login se gestiona en Mantenimiento de
 * Proveedores): ADMINISTRADOR, AUDITOR, ENTIDAD y cualquier rol custom nuevo
 * creado desde Roles y Permisos. No reemplaza `EntidadController::usuarios`
 * (vista embebida en el detalle de una entidad concreta, sigue igual).
 */
class GestionUsuariosController extends Controller
{
    public function __construct(private AdministracionService $administracionService) {}

    private function queryBase()
    {
        return User::query()->whereDoesntHave('roles', fn ($q) => $q->where('name', 'PROVEEDOR'));
    }

    public function index(UsuarioPlataformaIndexRequest $request): JsonResponse
    {
        $columnaOrden = ['nombre' => 'name', 'email' => 'email'][$request->input('ordenar_por', 'nombre')] ?? 'name';

        $query = $this->queryBase()
            ->select(['id', 'name', 'email', 'entidad_id'])
            ->with(['entidad' => fn ($q) => $q->select(['ENTI_ID', 'ENTI_RAZON_SOCIAL'])])
            ->when($request->filled('rol'), fn ($q) => $q->whereHas('roles', fn ($sub) => $sub->where('name', $request->input('rol'))))
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $buscar = $request->string('buscar');
                $q->where(fn ($sub) => $sub->where('name', 'ilike', "%{$buscar}%")->orWhere('email', 'ilike', "%{$buscar}%"));
            })
            ->orderBy($columnaOrden, $request->input('direccion', 'asc'));

        $paginador = $query->paginate(
            $request->integer('por_pagina', 15),
            page: $request->integer('pagina', 1),
        );

        $paginador->getCollection()->transform(fn (User $u) => [
            'id' => $u->id,
            'nombre' => $u->name,
            'correo' => $u->email,
            'rol' => $u->getRoleNames()->first(),
            'entidad' => $u->entidad ? ['entidad_id' => $u->entidad->ENTI_ID, 'razon_social' => $u->entidad->ENTI_RAZON_SOCIAL] : null,
        ]);

        return ApiResponse::paginado($paginador);
    }

    public function show(int $id): JsonResponse
    {
        $usuario = $this->queryBase()
            ->select(['id', 'name', 'email', 'entidad_id'])
            ->with(['entidad' => fn ($q) => $q->select(['ENTI_ID', 'ENTI_RAZON_SOCIAL'])])
            ->findOrFail($id);

        return ApiResponse::success([
            'id' => $usuario->id,
            'nombre' => $usuario->name,
            'correo' => $usuario->email,
            'rol' => $usuario->getRoleNames()->first(),
            'entidad' => $usuario->entidad ? ['entidad_id' => $usuario->entidad->ENTI_ID, 'razon_social' => $usuario->entidad->ENTI_RAZON_SOCIAL] : null,
        ]);
    }

    public function store(UsuarioPlataformaStoreRequest $request): JsonResponse
    {
        $usuario = $this->administracionService->crearUsuarioPlataforma($request->validated());

        return ApiResponse::success(['id' => $usuario->id], 'Usuario creado.', 201);
    }

    public function update(UsuarioPlataformaUpdateRequest $request, int $id): JsonResponse
    {
        $usuario = $this->queryBase()->findOrFail($id);

        $this->administracionService->actualizarUsuarioPlataforma($usuario, $request->validated());

        return ApiResponse::success(null, 'Usuario actualizado.');
    }

    public function destroy(int $id): JsonResponse
    {
        $usuario = $this->queryBase()->findOrFail($id);

        $this->administracionService->eliminarUsuarioPlataforma($usuario);

        return ApiResponse::success(null, 'Usuario eliminado.');
    }
}
