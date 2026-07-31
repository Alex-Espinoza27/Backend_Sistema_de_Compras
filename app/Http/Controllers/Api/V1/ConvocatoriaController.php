<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convocatoria\CancelarConvocatoriaRequest;
use App\Http\Requests\Convocatoria\ConvocatoriaIndexRequest;
use App\Http\Requests\Convocatoria\ConvocatoriaStoreRequest;
use App\Http\Requests\Convocatoria\ConvocatoriaUpdateRequest;
use App\Http\Requests\Convocatoria\SubirPlantillaDjRequest;
use App\Models\Archivo;
use App\Models\Convocatoria;
use App\Models\Estado;
use App\Services\ArchivoService;
use App\Services\ConvocatoriaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConvocatoriaController extends Controller
{
    public function __construct(
        private ConvocatoriaService $convocatoriaService,
        private ArchivoService $archivoService,
    ) {}

    public function index(ConvocatoriaIndexRequest $request): JsonResponse
    {
        $columnaOrden = [
            'fecha_fin' => 'CONV_FECHA_FIN',
            'fecha_inicio' => 'CONV_FECHA_INICIO',
            'numero' => 'CONV_NUMERO',
            'estado' => 'CONV_ESTADO_ID',
        ][$request->input('ordenar_por', 'fecha_fin')] ?? 'CONV_FECHA_FIN';

        $esAdminOAuditor = $request->user()->hasAnyRole(['ADMINISTRADOR', 'AUDITOR']);

        $query = Convocatoria::query()
            ->select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_FECHA_INICIO', 'CONV_FECHA_FIN', 'CONV_ESTADO_ID'])
            ->selectRaw('fn_convocatoria_urgencia("CONV_ID") as urgencia_dias')
            ->withCount('ofertas as total_ofertas')
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $buscar = $request->string('buscar');
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('CONV_NOMBRE_SERVICIO', 'ilike', "%{$buscar}%")->orWhere('CONV_NUMERO', 'ilike', "%{$buscar}%");
                });
            })
            ->when($request->filled('estado'), function ($q) use ($request) {
                $estadoId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', $request->input('estado'))->value('ESTA_ID');
                $q->where('CONV_ESTADO_ID', $estadoId);
            })
            ->when($esAdminOAuditor && $request->filled('entidad_id'), fn ($q) => $q->where('CONV_ENTIDAD_ID', $request->integer('entidad_id')))
            ->when($request->filled('desde'), fn ($q) => $q->where('CONV_FECHA_FIN', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->where('CONV_FECHA_FIN', '<=', $request->date('hasta')))
            ->with(['estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE', 'ESTA_COLOR'])])
            ->orderBy($columnaOrden, $request->input('direccion', 'asc'));

        $paginador = $query->paginate(
            $request->integer('por_pagina', 15),
            page: $request->integer('pagina', 1),
        );

        $paginador->getCollection()->transform(fn (Convocatoria $c) => [
            'convocatoria_id' => $c->CONV_ID,
            'numero' => $c->CONV_NUMERO,
            'nombre_servicio' => $c->CONV_NOMBRE_SERVICIO,
            'fecha_inicio' => $c->CONV_FECHA_INICIO,
            'fecha_fin' => $c->CONV_FECHA_FIN,
            'estado' => ['codigo' => $c->estado->ESTA_CODIGO, 'nombre' => $c->estado->ESTA_NOMBRE, 'color' => $c->estado->ESTA_COLOR],
            'urgencia_dias' => (int) $c->urgencia_dias,
            'total_ofertas' => $c->total_ofertas,
        ]);

        return ApiResponse::paginado($paginador);
    }

    public function show(int $id): JsonResponse
    {
        $conv = Convocatoria::query()
            ->select([
                'CONV_ID', 'CONV_REQUERIMIENTO_ID', 'CONV_ENTIDAD_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO',
                'CONV_FECHA_INICIO', 'CONV_FECHA_FIN', 'CONV_ESTADO_ID', 'CONV_MOTIVO_CIERRE',
                'CONV_FECHA_PUBLICACION', 'CONV_FECHA_APERTURA', 'CONV_FECHA_RESULTADO',
            ])
            ->withCount('ofertas as total_ofertas')
            ->with([
                'estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE', 'ESTA_COLOR']),
                'requerimiento' => fn ($q) => $q->select(['REQU_ID', 'REQU_NUMERO', 'REQU_OBJETO']),
                'requerimiento.items' => fn ($q) => $q->select(['REIT_ID', 'REIT_REQUERIMIENTO_ID', 'REIT_NUMERO', 'REIT_DESCRIPCION', 'REIT_UNIDAD_MEDIDA_ID', 'REIT_CANTIDAD'])->orderBy('REIT_NUMERO'),
                'requerimiento.items.unidadMedida' => fn ($q) => $q->select(['UNME_ID', 'UNME_CODIGO', 'UNME_NOMBRE']),
            ])
            ->findOrFail($id);

        $archivoTdr = Archivo::select(['ARCH_ID', 'ARCH_NOMBRE_ORIGINAL'])
            ->where('ARCH_CONTEXTO', 'REQUERIMIENTO_TDR')
            ->where('ARCH_REFERENCIA_ID', $conv->CONV_REQUERIMIENTO_ID)
            ->first();

        $plantillaDj = Archivo::select(['ARCH_ID', 'ARCH_NOMBRE_ORIGINAL'])
            ->where('ARCH_CONTEXTO', 'CONVOCATORIA_PLANTILLA_DJ')
            ->where('ARCH_REFERENCIA_ID', $conv->CONV_ID)
            ->first();

        return ApiResponse::success([
            'convocatoria_id' => $conv->CONV_ID,
            'numero' => $conv->CONV_NUMERO,
            'nombre_servicio' => $conv->CONV_NOMBRE_SERVICIO,
            'fecha_inicio' => $conv->CONV_FECHA_INICIO,
            'fecha_fin' => $conv->CONV_FECHA_FIN,
            'estado' => ['codigo' => $conv->estado->ESTA_CODIGO, 'nombre' => $conv->estado->ESTA_NOMBRE, 'color' => $conv->estado->ESTA_COLOR],
            'motivo_cierre' => $conv->CONV_MOTIVO_CIERRE,
            'fecha_publicacion' => $conv->CONV_FECHA_PUBLICACION,
            'fecha_apertura' => $conv->CONV_FECHA_APERTURA,
            'fecha_resultado' => $conv->CONV_FECHA_RESULTADO,
            'requerimiento' => [
                'requerimiento_id' => $conv->requerimiento->REQU_ID,
                'numero' => $conv->requerimiento->REQU_NUMERO,
                'objeto' => $conv->requerimiento->REQU_OBJETO,
                'items' => $conv->requerimiento->items->map(fn ($item) => [
                    'requerimiento_item_id' => $item->REIT_ID,
                    'numero' => $item->REIT_NUMERO,
                    'descripcion' => $item->REIT_DESCRIPCION,
                    'unidad_medida' => ['codigo' => $item->unidadMedida->UNME_CODIGO, 'nombre' => $item->unidadMedida->UNME_NOMBRE],
                    'cantidad' => $item->REIT_CANTIDAD,
                ]),
            ],
            'archivos' => [
                'tdr' => $archivoTdr ? ['archivo_id' => $archivoTdr->ARCH_ID, 'nombre' => $archivoTdr->ARCH_NOMBRE_ORIGINAL] : null,
                'plantilla_dj' => $plantillaDj ? ['archivo_id' => $plantillaDj->ARCH_ID, 'nombre' => $plantillaDj->ARCH_NOMBRE_ORIGINAL] : null,
            ],
            'total_ofertas' => $conv->total_ofertas,
        ]);
    }

    public function store(ConvocatoriaStoreRequest $request): JsonResponse
    {
        $entidadId = $request->user()->entidad_id;

        if (! $entidadId) {
            throw new HttpException(403, 'Debe pertenecer a una entidad para crear convocatorias.');
        }

        $convocatoria = $this->convocatoriaService->crear($request->validated(), $entidadId);

        return ApiResponse::success(['convocatoria_id' => $convocatoria->CONV_ID], 'Convocatoria creada en borrador.', 201);
    }

    public function update(ConvocatoriaUpdateRequest $request, int $id): JsonResponse
    {
        $convocatoria = Convocatoria::select(['CONV_ID', 'CONV_ESTADO_ID'])->findOrFail($id);

        $this->convocatoriaService->actualizar($convocatoria, $request->validated());

        return ApiResponse::success(null, 'Convocatoria actualizada.');
    }

    public function subirPlantillaDj(SubirPlantillaDjRequest $request, int $id): JsonResponse
    {
        $convocatoria = Convocatoria::select(['CONV_ID'])->findOrFail($id);

        $archivo = $this->archivoService->subir($request->file('archivo'), 'CONVOCATORIA_PLANTILLA_DJ', $convocatoria->CONV_ID);

        return ApiResponse::success(['archivo_id' => $archivo->ARCH_ID, 'nombre' => $archivo->ARCH_NOMBRE_ORIGINAL], 'Plantilla de declaración jurada subida.', 201);
    }

    public function eliminarPlantillaDj(int $id, int $archivoId): JsonResponse
    {
        Convocatoria::select(['CONV_ID'])->findOrFail($id);

        $archivo = Archivo::where('ARCH_ID', $archivoId)
            ->where('ARCH_CONTEXTO', 'CONVOCATORIA_PLANTILLA_DJ')
            ->where('ARCH_REFERENCIA_ID', $id)
            ->firstOr(function () {
                throw new HttpException(404, 'El archivo no existe para esta convocatoria.');
            });

        $this->archivoService->eliminar($archivo);

        return ApiResponse::success(null, 'Plantilla eliminada.');
    }

    public function publicar(int $id): JsonResponse
    {
        $convocatoria = Convocatoria::select(['CONV_ID', 'CONV_ENTIDAD_ID', 'CONV_REQUERIMIENTO_ID', 'CONV_ESTADO_ID', 'CONV_FECHA_INICIO', 'CONV_FECHA_FIN'])
            ->findOrFail($id);

        $resultado = $this->convocatoriaService->publicar($convocatoria);

        return ApiResponse::success($resultado, 'Convocatoria publicada.');
    }

    public function cancelar(CancelarConvocatoriaRequest $request, int $id): JsonResponse
    {
        $convocatoria = Convocatoria::select(['CONV_ID', 'CONV_ESTADO_ID'])->findOrFail($id);

        $this->convocatoriaService->cancelar($convocatoria, $request->input('motivo'));

        return ApiResponse::success(null, 'Convocatoria cancelada.');
    }
}
