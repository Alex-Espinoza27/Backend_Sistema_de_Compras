<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Oferta\PresentacionOfertasIndexRequest;
use App\Models\Convocatoria;
use App\Models\Estado;
use App\Models\Oferta;
use App\Services\EvaluacionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * M06 del TDR (00_CONTEXTO_TDR.md §4, 05C hardening): todas las ofertas
 * recibidas por las convocatorias de la propia entidad, sin filtrar por
 * estado de convocatoria (a diferencia de `EvaluacionController::ofertas`,
 * que exige EN_EVALUACION/ADJUDICADA/DESIERTA para una convocatoria puntual).
 * El scope por entidad lo aplica el global scope `DeSuEntidad` de
 * `Convocatoria` a través de `whereHas('convocatoria')`.
 */
class PresentacionOfertasController extends Controller
{
    public function __construct(private EvaluacionService $evaluacionService) {}

    public function index(PresentacionOfertasIndexRequest $request): JsonResponse
    {
        $query = Oferta::query()
            ->select(['OFER_ID', 'OFER_CONVOCATORIA_ID', 'OFER_PROVEEDOR_ID', 'OFER_CONSTANCIA_CODIGO', 'OFER_TOTAL_GENERAL', 'OFER_ESTADO_ID', 'OFER_FECHA_ENVIO'])
            ->whereHas('convocatoria', function ($q) use ($request) {
                if ($request->filled('buscar')) {
                    $buscar = $request->string('buscar');
                    $q->where(function ($sub) use ($buscar) {
                        $sub->where('CONV_NOMBRE_SERVICIO', 'ilike', "%{$buscar}%")->orWhere('CONV_NUMERO', 'ilike', "%{$buscar}%");
                    });
                }
            })
            ->when($request->filled('convocatoria_id'), fn ($q) => $q->where('OFER_CONVOCATORIA_ID', $request->integer('convocatoria_id')))
            ->when($request->filled('estado'), function ($q) use ($request) {
                $estadoId = Estado::where('ESTA_CONTEXTO', 'OFERTA')->where('ESTA_CODIGO', $request->input('estado'))->value('ESTA_ID');
                $q->where('OFER_ESTADO_ID', $estadoId);
            })
            // `whereDate` compara solo la parte de fecha: "hasta" así incluye
            // el día completo, no solo la medianoche (05C hardening, pedido
            // explícitamente por el usuario, con valor por defecto "hoy").
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('OFER_FECHA_ENVIO', '>=', $request->input('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('OFER_FECHA_ENVIO', '<=', $request->input('hasta')))
            ->with([
                'convocatoria' => fn ($q) => $q->select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO']),
                'proveedor' => fn ($q) => $q->select(['PROV_ID', 'PROV_NUMERO_DOCUMENTO', 'PROV_RAZON_SOCIAL']),
                'estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE', 'ESTA_COLOR']),
            ])
            ->orderBy(
                $request->input('ordenar_por') === 'total_general' ? 'OFER_TOTAL_GENERAL' : 'OFER_FECHA_ENVIO',
                $request->input('direccion', 'desc'),
            );

        $paginador = $query->paginate(
            $request->integer('por_pagina', 15),
            page: $request->integer('pagina', 1),
        );

        $paginador->getCollection()->transform(fn (Oferta $o) => [
            'oferta_id' => $o->OFER_ID,
            'constancia_codigo' => $o->OFER_CONSTANCIA_CODIGO,
            'convocatoria' => ['convocatoria_id' => $o->convocatoria->CONV_ID, 'numero' => $o->convocatoria->CONV_NUMERO, 'nombre_servicio' => $o->convocatoria->CONV_NOMBRE_SERVICIO],
            'proveedor' => ['proveedor_id' => $o->proveedor->PROV_ID, 'numero_documento' => $o->proveedor->PROV_NUMERO_DOCUMENTO, 'razon_social' => $o->proveedor->PROV_RAZON_SOCIAL],
            'total_general' => $o->OFER_TOTAL_GENERAL,
            'estado' => ['codigo' => $o->estado->ESTA_CODIGO, 'nombre' => $o->estado->ESTA_NOMBRE, 'color' => $o->estado->ESTA_COLOR],
            'fecha_envio' => $o->OFER_FECHA_ENVIO,
        ]);

        return ApiResponse::paginado($paginador);
    }

    public function show(int $id): JsonResponse
    {
        $oferta = Oferta::query()
            ->select([
                'OFER_ID', 'OFER_CONVOCATORIA_ID', 'OFER_PROVEEDOR_ID', 'OFER_TOTAL_GENERAL', 'OFER_VALIDEZ_DIAS',
                'OFER_PLAZO_EJECUCION_SEGUN_TDR', 'OFER_PLAZO_EJECUCION_DIAS', 'OFER_FORMA_PAGO_SEGUN_TDR', 'OFER_FORMA_PAGO_DIAS',
                'OFER_PLAZO_GARANTIA', 'OFER_CONTACTO_NOMBRE', 'OFER_CONTACTO_TELEFONO', 'OFER_CONTACTO_CORREO',
                'OFER_EVAL_TECNICA', 'OFER_EVAL_TECNICA_MOTIVO', 'OFER_EVAL_LOGISTICA', 'OFER_EVAL_LOGISTICA_MOTIVO',
            ])
            ->whereHas('convocatoria')
            ->findOrFail($id);

        Convocatoria::select(['CONV_ID'])->findOrFail($oferta->OFER_CONVOCATORIA_ID);

        return ApiResponse::success($this->evaluacionService->detalle($oferta));
    }
}
