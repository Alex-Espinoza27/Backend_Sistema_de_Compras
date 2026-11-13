<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Requerimiento\RequerimientoIndexRequest;
use App\Http\Requests\Requerimiento\RequerimientoStoreRequest;
use App\Http\Requests\Requerimiento\RequerimientoUpdateRequest;
use App\Models\Archivo;
use App\Models\Requerimiento;
use App\Models\Rubro;
use App\Models\UnidadMedida;
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
            'fecha_creacion' => 'REQU_CREADO_EN',
        ][$request->input('ordenar_por', 'numero')] ?? 'REQU_NUMERO';

        $esAdminOAuditor = $request->user()->hasAnyRole(['ADMINISTRADOR', 'AUDITOR']);

        $query = Requerimiento::query()
            ->select(['REQU_ID', 'REQU_ENTIDAD_ID', 'REQU_NUMERO', 'REQU_OBJETO', 'REQU_TIPO', 'REQU_AREA_ID', 'REQU_ACTIVO', 'REQU_CREADO_EN'])
            ->withCount(['items as cantidad_items', 'convocatorias as convocatorias_count'])
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $buscar = $request->string('buscar');
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('REQU_OBJETO', 'ilike', "%{$buscar}%")->orWhere('REQU_NUMERO', 'ilike', "%{$buscar}%");
                });
            })
            ->when($request->filled('tipo'), fn ($q) => $q->where('REQU_TIPO', $request->input('tipo')))
            ->when($request->filled('rubro_ids'), function ($q) use ($request) {
                $q->whereHas('rubros', fn ($sub) => $sub->whereIn('RUBR_ID', $request->input('rubro_ids')));
            })
            ->when($request->filled('convocado'), function ($q) use ($request) {
                filter_var($request->input('convocado'), FILTER_VALIDATE_BOOLEAN)
                    ? $q->whereHas('convocatorias')
                    : $q->whereDoesntHave('convocatorias');
            })
            // Solo ADMINISTRADOR/AUDITOR pueden filtrar por entidad (05C
            // hardening): para ENTIDAD el scope real ya lo aplica `DeSuEntidad`.
            ->when($esAdminOAuditor && $request->filled('entidad_id'), fn ($q) => $q->where('REQU_ENTIDAD_ID', $request->integer('entidad_id')))
            // `whereDate` compara solo la parte de fecha (ignora la hora):
            // "hasta" así incluye el día completo, no solo la medianoche
            // (05C hardening, pedido explícitamente por el usuario — nuevo
            // filtro, con el valor por defecto "hoy" que arma el frontend).
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('REQU_CREADO_EN', '>=', $request->input('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('REQU_CREADO_EN', '<=', $request->input('hasta')))
            ->with([
                'rubros' => fn ($q) => $q->select(['RUBR_ID', 'RUBR_NOMBRE']),
                'area' => fn ($q) => $q->select(['AREA_ID', 'AREA_NOMBRE']),
                'entidad' => fn ($q) => $q->select(['ENTI_ID', 'ENTI_SIGLA']),
            ])
            ->orderBy($columnaOrden, $request->input('direccion', 'asc'));

        $paginador = $query->paginate(
            $request->integer('por_pagina', 15),
            page: $request->integer('pagina', 1),
        );

        $paginador->getCollection()->transform(fn (Requerimiento $r) => [
            'requerimiento_id' => $r->REQU_ID,
            'numero' => $r->REQU_NUMERO,
            'objeto' => $r->REQU_OBJETO,
            'tipo' => $r->REQU_TIPO,
            'activo' => $r->REQU_ACTIVO,
            'area' => $r->area ? ['area_id' => $r->area->AREA_ID, 'nombre' => $r->area->AREA_NOMBRE] : null,
            'entidad' => ['entidad_id' => $r->REQU_ENTIDAD_ID, 'sigla' => $r->entidad?->ENTI_SIGLA],
            'fecha_creacion' => $r->REQU_CREADO_EN,
            'rubros' => $r->rubros->map(fn (Rubro $rubro) => ['rubro_id' => $rubro->RUBR_ID, 'nombre' => $rubro->RUBR_NOMBRE])->values(),
            'cantidad_items' => $r->cantidad_items,
            'tiene_convocatoria' => $r->convocatorias_count > 0,
        ]);

        return ApiResponse::paginado($paginador);
    }

    public function show(int $id): JsonResponse
    {
        $requerimiento = Requerimiento::query()
            ->select(['REQU_ID', 'REQU_ENTIDAD_ID', 'REQU_NUMERO', 'REQU_OBJETO', 'REQU_DESCRIPCION', 'REQU_TIPO', 'REQU_AREA_ID', 'REQU_CREADO_EN'])
            ->with([
                'rubros' => fn ($q) => $q->select(['RUBR_ID', 'RUBR_NOMBRE']),
                'area' => fn ($q) => $q->select(['AREA_ID', 'AREA_NOMBRE']),
                'entidad' => fn ($q) => $q->select(['ENTI_ID', 'ENTI_SIGLA']),
                'items' => fn ($q) => $q->select(['REIT_ID', 'REIT_REQUERIMIENTO_ID', 'REIT_NUMERO', 'REIT_DESCRIPCION', 'REIT_UNIDAD_MEDIDA_ID', 'REIT_CANTIDAD', 'REIT_PRESENTACION'])->orderBy('REIT_NUMERO'),
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
            'area' => $requerimiento->area ? ['area_id' => $requerimiento->area->AREA_ID, 'nombre' => $requerimiento->area->AREA_NOMBRE] : null,
            'entidad' => ['entidad_id' => $requerimiento->REQU_ENTIDAD_ID, 'sigla' => $requerimiento->entidad?->ENTI_SIGLA],
            'fecha_creacion' => $requerimiento->REQU_CREADO_EN,
            'rubros' => $requerimiento->rubros->map(fn (Rubro $rubro) => ['rubro_id' => $rubro->RUBR_ID, 'nombre' => $rubro->RUBR_NOMBRE])->values(),
            'items' => $requerimiento->items->map(fn ($item) => [
                'requerimiento_item_id' => $item->REIT_ID,
                'numero' => $item->REIT_NUMERO,
                'descripcion' => $item->REIT_DESCRIPCION,
                'unidad_medida' => ['codigo' => $item->unidadMedida->UNME_CODIGO, 'nombre' => $item->unidadMedida->UNME_NOMBRE],
                'cantidad' => $item->REIT_CANTIDAD,
                'presentacion' => $item->REIT_PRESENTACION,
            ]),
            'archivo_tdr' => $archivoTdr ? ['archivo_id' => $archivoTdr->ARCH_ID, 'nombre' => $archivoTdr->ARCH_NOMBRE_ORIGINAL] : null,
        ]);
    }

    public function store(RequerimientoStoreRequest $request): JsonResponse
    {
        $requerimiento = $this->requerimientoService->crear($request->validated(), $request->user(), $request->file('archivo_tdr'));

        return ApiResponse::success(
            ['requerimiento_id' => $requerimiento->REQU_ID, 'numero' => $requerimiento->REQU_NUMERO],
            'Requerimiento creado.',
            201,
        );
    }

    public function update(RequerimientoUpdateRequest $request, int $id): JsonResponse
    {
        $requerimiento = Requerimiento::select(['REQU_ID'])->findOrFail($id);

        $this->requerimientoService->actualizar($requerimiento, $request->validated(), $request->file('archivo_tdr'));

        return ApiResponse::success(null, 'Requerimiento actualizado.');
    }

    public function desactivar(int $id): JsonResponse
    {
        $requerimiento = Requerimiento::select(['REQU_ID'])->findOrFail($id);

        $this->requerimientoService->desactivar($requerimiento);

        return ApiResponse::success(null, 'Requerimiento desactivado.');
    }

    public function activar(int $id): JsonResponse
    {
        $requerimiento = Requerimiento::select(['REQU_ID'])->findOrFail($id);

        $this->requerimientoService->activar($requerimiento);

        return ApiResponse::success(null, 'Requerimiento activado.');
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

    /** Catálogo de unidades de medida para la sub-tabla de ítems de E2. Solo
     * existía `GET /admin/unidades-medida` (administracion.catalogos), que
     * ENTIDAD nunca tiene; este endpoint espeja el mismo shape bajo
     * `requerimientos.ver`, que ENTIDAD sí tiene. */
    public function unidadesMedida(): JsonResponse
    {
        // Solo activas (05C hardening, pedido explícitamente por el
        // usuario): una unidad desactivada deja de ofrecerse en la
        // sub-tabla de ítems de un requerimiento nuevo.
        $items = UnidadMedida::select(['UNME_ID', 'UNME_CODIGO', 'UNME_NOMBRE'])
            ->where('UNME_ACTIVO', true)
            ->orderBy('UNME_CODIGO')
            ->get()
            ->map(fn (UnidadMedida $u) => ['unidad_medida_id' => $u->UNME_ID, 'codigo' => $u->UNME_CODIGO, 'nombre' => $u->UNME_NOMBRE]);

        return ApiResponse::success($items);
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
