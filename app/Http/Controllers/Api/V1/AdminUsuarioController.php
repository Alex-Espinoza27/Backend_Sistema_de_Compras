<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UsuarioInternoIndexRequest;
use App\Http\Requests\Admin\UsuarioInternoStoreRequest;
use App\Http\Requests\Admin\UsuarioInternoUpdateRequest;
use App\Models\User;
use App\Services\AdministracionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminUsuarioController extends Controller
{
    public function __construct(private AdministracionService $administracionService) {}

    public function index(UsuarioInternoIndexRequest $request): JsonResponse
    {
        $columnaOrden = ['nombre' => 'name', 'email' => 'email'][$request->input('ordenar_por', 'nombre')] ?? 'name';

        $query = User::query()
            ->select(['id', 'name', 'email'])
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['ADMINISTRADOR', 'AUDITOR']))
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
        ]);

        return ApiResponse::paginado($paginador);
    }

    public function show(int $id): JsonResponse
    {
        $usuario = User::select(['id', 'name', 'email'])
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['ADMINISTRADOR', 'AUDITOR']))
            ->findOrFail($id);

        return ApiResponse::success([
            'id' => $usuario->id,
            'nombre' => $usuario->name,
            'correo' => $usuario->email,
            'rol' => $usuario->getRoleNames()->first(),
        ]);
    }

    public function store(UsuarioInternoStoreRequest $request): JsonResponse
    {
        $usuario = $this->administracionService->crearUsuarioInterno($request->validated());

        return ApiResponse::success(['id' => $usuario->id], 'Usuario interno creado.', 201);
    }

    public function update(UsuarioInternoUpdateRequest $request, int $id): JsonResponse
    {
        $usuario = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['ADMINISTRADOR', 'AUDITOR']))->findOrFail($id);

        $this->administracionService->actualizarUsuarioInterno($usuario, $request->validated());

        return ApiResponse::success(null, 'Usuario actualizado.');
    }

    public function destroy(int $id): JsonResponse
    {
        $usuario = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['ADMINISTRADOR', 'AUDITOR']))->findOrFail($id);

        $this->administracionService->eliminarUsuarioInterno($usuario);

        return ApiResponse::success(null, 'Usuario eliminado.');
    }
}
