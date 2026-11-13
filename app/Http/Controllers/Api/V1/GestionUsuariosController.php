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
use Illuminate\Http\Request;

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
        $columnaOrden = ['nombre' => 'name', 'email' => 'email', 'dni' => 'dni'][$request->input('ordenar_por', 'nombre')] ?? 'name';

        $query = $this->queryBase()
            ->select(['id', 'name', 'email', 'activo', 'dni', 'tipo_documento_id', 'nombres', 'apellido_paterno', 'apellido_materno', 'sexo', 'celular', 'fecha_nacimiento', 'entidad_id'])
            ->with([
                'entidad' => fn ($q) => $q->select(['ENTI_ID', 'ENTI_RAZON_SOCIAL']),
                'tipoDocumento' => fn ($q) => $q->select(['TIDO_ID', 'TIDO_CODIGO', 'TIDO_NOMBRE']),
            ])
            ->when($request->filled('rol'), fn ($q) => $q->whereHas('roles', fn ($sub) => $sub->where('name', $request->input('rol'))))
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $buscar = $request->string('buscar');
                $q->where(fn ($sub) => $sub
                    ->where('name', 'ilike', "%{$buscar}%")
                    ->orWhere('email', 'ilike', "%{$buscar}%")
                    ->orWhere('dni', 'ilike', "%{$buscar}%")
                    ->orWhere('nombres', 'ilike', "%{$buscar}%")
                    ->orWhere('apellido_paterno', 'ilike', "%{$buscar}%")
                    ->orWhere('apellido_materno', 'ilike', "%{$buscar}%")
                );
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
            'activo' => $u->activo,
            'dni' => $u->dni,
            'tipo_documento' => $u->tipoDocumento ? ['tipo_documento_id' => $u->tipoDocumento->TIDO_ID, 'codigo' => $u->tipoDocumento->TIDO_CODIGO, 'nombre' => $u->tipoDocumento->TIDO_NOMBRE] : null,
            'nombres' => $u->nombres,
            'apellido_paterno' => $u->apellido_paterno,
            'apellido_materno' => $u->apellido_materno,
            'sexo' => $u->sexo,
            'celular' => $u->celular,
            'fecha_nacimiento' => $u->fecha_nacimiento?->toDateString(),
            'rol' => $u->getRoleNames()->first(),
            'entidad' => $u->entidad ? ['entidad_id' => $u->entidad->ENTI_ID, 'razon_social' => $u->entidad->ENTI_RAZON_SOCIAL] : null,
        ]);

        return ApiResponse::paginado($paginador);
    }

    public function show(int $id): JsonResponse
    {
        $usuario = $this->queryBase()
            ->select(['id', 'name', 'email', 'activo', 'dni', 'tipo_documento_id', 'nombres', 'apellido_paterno', 'apellido_materno', 'sexo', 'celular', 'fecha_nacimiento', 'entidad_id'])
            ->with([
                'entidad' => fn ($q) => $q->select(['ENTI_ID', 'ENTI_RAZON_SOCIAL']),
                'tipoDocumento' => fn ($q) => $q->select(['TIDO_ID', 'TIDO_CODIGO', 'TIDO_NOMBRE']),
            ])
            ->findOrFail($id);

        return ApiResponse::success([
            'id' => $usuario->id,
            'nombre' => $usuario->name,
            'correo' => $usuario->email,
            'activo' => $usuario->activo,
            'dni' => $usuario->dni,
            'tipo_documento' => $usuario->tipoDocumento ? ['tipo_documento_id' => $usuario->tipoDocumento->TIDO_ID, 'codigo' => $usuario->tipoDocumento->TIDO_CODIGO, 'nombre' => $usuario->tipoDocumento->TIDO_NOMBRE] : null,
            'nombres' => $usuario->nombres,
            'apellido_paterno' => $usuario->apellido_paterno,
            'apellido_materno' => $usuario->apellido_materno,
            'sexo' => $usuario->sexo,
            'celular' => $usuario->celular,
            'fecha_nacimiento' => $usuario->fecha_nacimiento?->toDateString(),
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

    public function desactivar(int $id): JsonResponse
    {
        $usuario = $this->queryBase()->findOrFail($id);

        $this->administracionService->desactivarUsuarioPlataforma($usuario);

        return ApiResponse::success(null, 'Usuario desactivado.');
    }

    public function activar(int $id): JsonResponse
    {
        $usuario = $this->queryBase()->findOrFail($id);

        $this->administracionService->activarUsuarioPlataforma($usuario);

        return ApiResponse::success(null, 'Usuario activado.');
    }

    public function restablecerPassword(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $usuario = $this->queryBase()->findOrFail($id);

        $mensaje = $this->administracionService->restablecerPasswordUsuario($usuario, $request->input('password'));

        return ApiResponse::success(null, $mensaje);
    }
}
