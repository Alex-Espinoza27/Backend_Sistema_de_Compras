<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Area\AreaIndexRequest;
use App\Http\Requests\Area\AreaStoreRequest;
use App\Http\Requests\Area\AreaUpdateRequest;
use App\Models\Area;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Catálogo de áreas usuarias por entidad (05C hardening, M04 del TDR:
 * "registro de requerimientos... programación de convocatorias"). El scope
 * por entidad lo aplica el global scope `DeSuEntidad` del modelo `Area`
 * (mismo patrón que Requerimiento/Convocatoria), no este controller.
 */
class AreaController extends Controller
{
    public function index(AreaIndexRequest $request): JsonResponse
    {
        $esAdminOAuditor = $request->user()->hasAnyRole(['ADMINISTRADOR', 'AUDITOR']);

        if (! $esAdminOAuditor && ! $request->user()->entidad_id) {
            throw new HttpException(403, 'Debe pertenecer a una entidad para ver sus áreas.');
        }

        // ADMINISTRADOR/AUDITOR no tienen entidad propia (DeSuEntidad no
        // aplica scope para ellos) — necesitan indicar `entidad_id`
        // explícitamente (05C hardening, combo del filtro de Reportes).
        if ($esAdminOAuditor && ! $request->filled('entidad_id')) {
            throw new HttpException(422, 'Debe indicar una entidad.');
        }

        $query = Area::query()
            ->select(['AREA_ID', 'AREA_NOMBRE', 'AREA_SIGLA', 'AREA_ACTIVO'])
            ->when($esAdminOAuditor && $request->filled('entidad_id'), fn ($q) => $q->where('AREA_ENTIDAD_ID', $request->integer('entidad_id')))
            ->when($request->filled('buscar'), fn ($q) => $q->where('AREA_NOMBRE', 'ilike', '%'.$request->string('buscar').'%'))
            ->when($request->filled('activo'), fn ($q) => $q->where('AREA_ACTIVO', filter_var($request->input('activo'), FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('AREA_NOMBRE');

        $paginador = $query->paginate(
            $request->integer('por_pagina', 15),
            page: $request->integer('pagina', 1),
        );

        $paginador->getCollection()->transform(fn (Area $a) => [
            'area_id' => $a->AREA_ID,
            'nombre' => $a->AREA_NOMBRE,
            'sigla' => $a->AREA_SIGLA,
            'activo' => $a->AREA_ACTIVO,
        ]);

        return ApiResponse::paginado($paginador);
    }

    public function store(AreaStoreRequest $request): JsonResponse
    {
        // 05C hardening: quien no tiene rol ENTIDAD (ADMINISTRADOR o un rol
        // custom "staff interno") crea el área en nombre de la entidad que
        // elija explícitamente (`entidad_id`, validado en `AreaStoreRequest`).
        // Se verifica el ROL, no solo la presencia de `entidad_id` en la
        // fila del usuario.
        $entidadId = $request->user()->hasRole('ENTIDAD') ? $request->user()->entidad_id : $request->integer('entidad_id');

        if (! $entidadId) {
            throw new HttpException(403, 'Debe pertenecer a una entidad para crear áreas.');
        }

        $area = Area::create([
            'AREA_ENTIDAD_ID' => $entidadId,
            'AREA_NOMBRE' => $request->input('nombre'),
            'AREA_SIGLA' => $request->input('sigla'),
        ]);

        return ApiResponse::success(['area_id' => $area->AREA_ID], 'Área creada.', 201);
    }

    public function update(AreaUpdateRequest $request, int $id): JsonResponse
    {
        $area = Area::findOrFail($id);
        $area->update([
            'AREA_NOMBRE' => $request->input('nombre'),
            'AREA_SIGLA' => $request->input('sigla'),
            'AREA_ACTIVO' => $request->boolean('activo'),
        ]);

        return ApiResponse::success(null, 'Área actualizada.');
    }
}
