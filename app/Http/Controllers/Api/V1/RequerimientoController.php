<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Requerimiento\RequerimientoIndexRequest;
use App\Http\Requests\Requerimiento\RequerimientoStoreRequest;
use App\Http\Requests\Requerimiento\RequerimientoUpdateRequest;
use App\Http\Requests\Requerimiento\SubirTdrRequest;
use App\Models\Archivo;
use App\Models\Requerimiento;
use App\Services\ArchivoService;
use App\Services\RequerimientoService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RequerimientoController extends Controller
{
    public function __construct(
        private RequerimientoService $requerimientoService,
        private ArchivoService $archivoService,
    ) {}

    public function index(RequerimientoIndexRequest $request): JsonResponse
    {
        $columnaOrden = [
            'numero' => 'REQU_NUMERO',
            'objeto' => 'REQU_OBJETO',
            'tipo' => 'REQU_TIPO',
        ][$request->input('ordenar_por', 'numero')] ?? 'REQU_NUMERO';

        $query = Requerimiento::query()
            ->select(['REQU_ID', 'REQU_RUBRO_ID', 'REQU_NUMERO', 'REQU_OBJETO', 'REQU_TIPO', 'REQU_AREA_USUARIA'])
            ->withCount(['items as cantidad_items', 'convocatorias as convocatorias_count'])
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $buscar = $request->string('buscar');
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('REQU_OBJETO', 'ilike', "%{$buscar}%")->orWhere('REQU_NUMERO', 'ilike', "%{$buscar}%");
                });
            })
            ->when($request->filled('tipo'), fn ($q) => $q->where('REQU_TIPO', $request->input('tipo')))
            ->when($request->filled('rubro_id'), fn ($q) => $q->where('REQU_RUBRO_ID', $request->integer('rubro_id')))
            ->when($request->filled('convocado'), function ($q) use ($request) {
                filter_var($request->input('convocado'), FILTER_VALIDATE_BOOLEAN)
                    ? $q->whereHas('convocatorias')
                    : $q->whereDoesntHave('convocatorias');
            })
            ->with(['rubro' => fn ($q) => $q->select(['RUBR_ID', 'RUBR_NOMBRE'])])
            ->orderBy($columnaOrden);

        $paginador = $query->paginate(
            $request->integer('por_pagina', 15),
            page: $request->integer('pagina', 1),
        );

        $paginador->getCollection()->transform(fn (Requerimiento $r) => [
            'requerimiento_id' => $r->REQU_ID,
            'numero' => $r->REQU_NUMERO,
            'objeto' => $r->REQU_OBJETO,
            'tipo' => $r->REQU_TIPO,
            'area_usuaria' => $r->REQU_AREA_USUARIA,
            'rubro' => $r->rubro ? ['rubro_id' => $r->rubro->RUBR_ID, 'nombre' => $r->rubro->RUBR_NOMBRE] : null,
            'cantidad_items' => $r->cantidad_items,
            'tiene_convocatoria' => $r->convocatorias_count > 0,
        ]);

        return ApiResponse::paginado($paginador);
    }

    public function show(int $id): JsonResponse
    {
        $requerimiento = Requerimiento::query()
            ->select(['REQU_ID', 'REQU_RUBRO_ID', 'REQU_NUMERO', 'REQU_OBJETO', 'REQU_DESCRIPCION', 'REQU_TIPO', 'REQU_AREA_USUARIA'])
            ->with([
                'rubro' => fn ($q) => $q->select(['RUBR_ID', 'RUBR_NOMBRE']),
                'items' => fn ($q) => $q->select(['REIT_ID', 'REIT_REQUERIMIENTO_ID', 'REIT_NUMERO', 'REIT_DESCRIPCION', 'REIT_UNIDAD_MEDIDA_ID', 'REIT_CANTIDAD'])->orderBy('REIT_NUMERO'),
                'items.unidadMedida' => fn ($q) => $q->select(['UNME_ID', 'UNME_CODIGO', 'UNME_NOMBRE']),
            ])
            ->findOrFail($id);

        $archivoTdr = Archivo::select(['ARCH_ID', 'ARCH_NOMBRE_ORIGINAL'])
            ->where('ARCH_CONTEXTO', 'REQUERIMIENTO_TDR')
            ->where('ARCH_REFERENCIA_ID', $requerimiento->REQU_ID)
            ->first();

        return ApiResponse::success([
            'requerimiento_id' => $requerimiento->REQU_ID,
            'numero' => $requerimiento->REQU_NUMERO,
            'objeto' => $requerimiento->REQU_OBJETO,
            'descripcion' => $requerimiento->REQU_DESCRIPCION,
            'tipo' => $requerimiento->REQU_TIPO,
            'area_usuaria' => $requerimiento->REQU_AREA_USUARIA,
            'rubro' => $requerimiento->rubro ? ['rubro_id' => $requerimiento->rubro->RUBR_ID, 'nombre' => $requerimiento->rubro->RUBR_NOMBRE] : null,
            'items' => $requerimiento->items->map(fn ($item) => [
                'requerimiento_item_id' => $item->REIT_ID,
                'numero' => $item->REIT_NUMERO,
                'descripcion' => $item->REIT_DESCRIPCION,
                'unidad_medida' => ['codigo' => $item->unidadMedida->UNME_CODIGO, 'nombre' => $item->unidadMedida->UNME_NOMBRE],
                'cantidad' => $item->REIT_CANTIDAD,
            ]),
            'archivo_tdr' => $archivoTdr ? ['archivo_id' => $archivoTdr->ARCH_ID, 'nombre' => $archivoTdr->ARCH_NOMBRE_ORIGINAL] : null,
        ]);
    }

    public function store(RequerimientoStoreRequest $request): JsonResponse
    {
        $requerimiento = $this->requerimientoService->crear($request->validated(), $request->user());

        return ApiResponse::success(['requerimiento_id' => $requerimiento->REQU_ID], 'Requerimiento creado.', 201);
    }

    public function update(RequerimientoUpdateRequest $request, int $id): JsonResponse
    {
        $requerimiento = Requerimiento::select(['REQU_ID'])->findOrFail($id);

        $this->requerimientoService->actualizar($requerimiento, $request->validated());

        return ApiResponse::success(null, 'Requerimiento actualizado.');
    }

    public function destroy(int $id): JsonResponse
    {
        $requerimiento = Requerimiento::select(['REQU_ID'])->findOrFail($id);

        $this->requerimientoService->eliminar($requerimiento);

        return ApiResponse::success(null, 'Requerimiento eliminado.');
    }

    public function subirTdr(SubirTdrRequest $request, int $id): JsonResponse
    {
        $requerimiento = Requerimiento::select(['REQU_ID'])->findOrFail($id);

        $archivo = $this->archivoService->subir($request->file('archivo'), 'REQUERIMIENTO_TDR', $requerimiento->REQU_ID);

        return ApiResponse::success(['archivo_id' => $archivo->ARCH_ID, 'nombre' => $archivo->ARCH_NOMBRE_ORIGINAL], 'Archivo TDR subido.', 201);
    }

    public function eliminarTdr(int $id, int $archivoId): JsonResponse
    {
        Requerimiento::select(['REQU_ID'])->findOrFail($id);

        $archivo = Archivo::where('ARCH_ID', $archivoId)
            ->where('ARCH_CONTEXTO', 'REQUERIMIENTO_TDR')
            ->where('ARCH_REFERENCIA_ID', $id)
            ->firstOr(function () {
                throw new HttpException(404, 'El archivo no existe para este requerimiento.');
            });

        $this->archivoService->eliminar($archivo);

        return ApiResponse::success(null, 'Archivo TDR eliminado.');
    }

    public function combo(Request $request): JsonResponse
    {
        $items = Requerimiento::query()
            ->select(['REQU_ID', 'REQU_NUMERO', 'REQU_OBJETO'])
            ->whereDoesntHave('convocatorias')
            ->orderBy('REQU_NUMERO')
            ->get()
            ->map(fn (Requerimiento $r) => [
                'requerimiento_id' => $r->REQU_ID,
                'numero' => $r->REQU_NUMERO,
                'objeto' => $r->REQU_OBJETO,
            ]);

        return ApiResponse::success($items);
    }
}
