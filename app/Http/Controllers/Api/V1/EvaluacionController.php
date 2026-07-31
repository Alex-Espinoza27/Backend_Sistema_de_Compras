<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluacion\EvaluarOfertaRequest;
use App\Models\Archivo;
use App\Models\Convocatoria;
use App\Models\Estado;
use App\Models\Oferta;
use App\Services\EvaluacionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EvaluacionController extends Controller
{
    public function __construct(private EvaluacionService $evaluacionService) {}

    public function ofertas(int $id): JsonResponse
    {
        $convocatoria = Convocatoria::select(['CONV_ID', 'CONV_ESTADO_ID'])->findOrFail($id);
        $codigoConvocatoria = Estado::where('ESTA_ID', $convocatoria->CONV_ESTADO_ID)->value('ESTA_CODIGO');

        if (! in_array($codigoConvocatoria, ['EN_EVALUACION', 'ADJUDICADA', 'DESIERTA'], true)) {
            throw new HttpException(409, 'Las ofertas de esta convocatoria aún no están disponibles para evaluación.');
        }

        $items = Oferta::query()
            ->select(['OFER_ID', 'OFER_PROVEEDOR_ID', 'OFER_TOTAL_GENERAL', 'OFER_TIPO_COMPROBANTE_ID', 'OFER_EVAL_TECNICA', 'OFER_EVAL_LOGISTICA', 'OFER_ESTADO_ID', 'OFER_FECHA_ENVIO'])
            ->where('OFER_CONVOCATORIA_ID', $id)
            ->with([
                'proveedor' => fn ($q) => $q->select(['PROV_ID', 'PROV_NUMERO_DOCUMENTO', 'PROV_RAZON_SOCIAL']),
                'tipoComprobante' => fn ($q) => $q->select(['TICO_ID', 'TICO_CODIGO']),
                'estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_COLOR']),
            ])
            ->orderBy('OFER_TOTAL_GENERAL')
            ->get()
            ->map(fn (Oferta $o) => [
                'oferta_id' => $o->OFER_ID,
                'proveedor' => ['proveedor_id' => $o->proveedor->PROV_ID, 'numero_documento' => $o->proveedor->PROV_NUMERO_DOCUMENTO, 'razon_social' => $o->proveedor->PROV_RAZON_SOCIAL],
                'total_general' => $o->OFER_TOTAL_GENERAL,
                'tipo_comprobante' => $o->tipoComprobante->TICO_CODIGO,
                'eval_tecnica' => $o->OFER_EVAL_TECNICA,
                'eval_logistica' => $o->OFER_EVAL_LOGISTICA,
                'estado' => ['codigo' => $o->estado->ESTA_CODIGO, 'color' => $o->estado->ESTA_COLOR],
                'fecha_envio' => $o->OFER_FECHA_ENVIO,
            ]);

        return ApiResponse::success(['items' => $items]);
    }

    public function evaluacionDetalle(int $id): JsonResponse
    {
        $oferta = Oferta::query()
            ->select([
                'OFER_ID', 'OFER_CONVOCATORIA_ID', 'OFER_PROVEEDOR_ID', 'OFER_TOTAL_GENERAL', 'OFER_VALIDEZ_DIAS',
                'OFER_PLAZO_EJECUCION_SEGUN_TDR', 'OFER_PLAZO_EJECUCION_DIAS', 'OFER_FORMA_PAGO_SEGUN_TDR', 'OFER_FORMA_PAGO_DIAS',
                'OFER_PLAZO_GARANTIA', 'OFER_CONTACTO_NOMBRE', 'OFER_CONTACTO_TELEFONO', 'OFER_CONTACTO_CORREO',
                'OFER_EVAL_TECNICA', 'OFER_EVAL_TECNICA_MOTIVO', 'OFER_EVAL_LOGISTICA', 'OFER_EVAL_LOGISTICA_MOTIVO',
            ])
            ->with([
                'proveedor' => fn ($q) => $q->select(['PROV_ID', 'PROV_NUMERO_DOCUMENTO', 'PROV_RAZON_SOCIAL']),
                'items' => fn ($q) => $q->select(['OFIT_ID', 'OFIT_OFERTA_ID', 'OFIT_REQUERIMIENTO_ITEM_ID', 'OFIT_MARCA_MODELO', 'OFIT_PRECIO_UNITARIO', 'OFIT_PRECIO_TOTAL']),
                'items.requerimientoItem' => fn ($q) => $q->select(['REIT_ID', 'REIT_NUMERO', 'REIT_DESCRIPCION', 'REIT_UNIDAD_MEDIDA_ID', 'REIT_CANTIDAD'])
                    ->with(['unidadMedida' => fn ($qq) => $qq->select(['UNME_ID', 'UNME_CODIGO', 'UNME_NOMBRE'])]),
            ])
            ->findOrFail($id);

        Convocatoria::select(['CONV_ID'])->findOrFail($oferta->OFER_CONVOCATORIA_ID);

        $archivos = Archivo::select(['ARCH_ID', 'ARCH_CONTEXTO', 'ARCH_NOMBRE_ORIGINAL', 'ARCH_HASH_SHA256'])
            ->where('ARCH_REFERENCIA_ID', $oferta->OFER_ID)
            ->whereIn('ARCH_CONTEXTO', ['OFERTA_DJ', 'OFERTA_SUSTENTO'])
            ->get();

        return ApiResponse::success([
            'oferta_id' => $oferta->OFER_ID,
            'proveedor' => ['proveedor_id' => $oferta->proveedor->PROV_ID, 'numero_documento' => $oferta->proveedor->PROV_NUMERO_DOCUMENTO, 'razon_social' => $oferta->proveedor->PROV_RAZON_SOCIAL],
            'items' => $oferta->items->map(fn ($item) => [
                'numero' => $item->requerimientoItem->REIT_NUMERO,
                'descripcion' => $item->requerimientoItem->REIT_DESCRIPCION,
                'unidad_medida' => ['codigo' => $item->requerimientoItem->unidadMedida->UNME_CODIGO, 'nombre' => $item->requerimientoItem->unidadMedida->UNME_NOMBRE],
                'cantidad' => $item->requerimientoItem->REIT_CANTIDAD,
                'marca_modelo' => $item->OFIT_MARCA_MODELO,
                'precio_unitario' => $item->OFIT_PRECIO_UNITARIO,
                'precio_total' => $item->OFIT_PRECIO_TOTAL,
            ]),
            'archivos' => $archivos->map(fn (Archivo $a) => [
                'archivo_id' => $a->ARCH_ID,
                'contexto' => $a->ARCH_CONTEXTO,
                'nombre' => $a->ARCH_NOMBRE_ORIGINAL,
                'hash_sha256' => $a->ARCH_HASH_SHA256,
                'descarga_url' => "/api/v1/archivos/{$a->ARCH_ID}/descargar",
            ]),
            'validez_dias' => $oferta->OFER_VALIDEZ_DIAS,
            'plazo_ejecucion' => ['segun_tdr' => $oferta->OFER_PLAZO_EJECUCION_SEGUN_TDR, 'dias' => $oferta->OFER_PLAZO_EJECUCION_DIAS],
            'forma_pago' => ['segun_tdr' => $oferta->OFER_FORMA_PAGO_SEGUN_TDR, 'dias' => $oferta->OFER_FORMA_PAGO_DIAS],
            'plazo_garantia' => $oferta->OFER_PLAZO_GARANTIA,
            'contacto' => ['nombre' => $oferta->OFER_CONTACTO_NOMBRE, 'telefono' => $oferta->OFER_CONTACTO_TELEFONO, 'correo' => $oferta->OFER_CONTACTO_CORREO],
            'eval_tecnica' => $oferta->OFER_EVAL_TECNICA,
            'eval_tecnica_motivo' => $oferta->OFER_EVAL_TECNICA_MOTIVO,
            'eval_logistica' => $oferta->OFER_EVAL_LOGISTICA,
            'eval_logistica_motivo' => $oferta->OFER_EVAL_LOGISTICA_MOTIVO,
        ]);
    }

    public function evaluar(EvaluarOfertaRequest $request, int $id): JsonResponse
    {
        $oferta = Oferta::select(['OFER_ID', 'OFER_CONVOCATORIA_ID', 'OFER_ESTADO_ID', 'OFER_EVAL_TECNICA', 'OFER_EVAL_LOGISTICA'])->findOrFail($id);

        Convocatoria::select(['CONV_ID'])->findOrFail($oferta->OFER_CONVOCATORIA_ID);

        $datos = [];

        if ($request->has('eval_tecnica')) {
            $datos['eval_tecnica'] = $request->input('eval_tecnica');
            $datos['eval_tecnica_motivo'] = $request->input('eval_tecnica_motivo');
        }

        if ($request->has('eval_logistica')) {
            $datos['eval_logistica'] = $request->input('eval_logistica');
            $datos['eval_logistica_motivo'] = $request->input('eval_logistica_motivo');
        }

        $this->evaluacionService->evaluar($oferta, $datos);

        return ApiResponse::success(null, 'Evaluación registrada.');
    }

    public function cuadroComparativo(int $id): JsonResponse
    {
        Convocatoria::select(['CONV_ID'])->findOrFail($id);

        $ofertas = Oferta::select(['OFER_ID', 'OFER_PROVEEDOR_ID', 'OFER_TOTAL_GENERAL', 'OFER_EVAL_TECNICA', 'OFER_EVAL_LOGISTICA', 'OFER_ESTADO_ID'])
            ->where('OFER_CONVOCATORIA_ID', $id)
            ->with([
                'proveedor' => fn ($q) => $q->select(['PROV_ID', 'PROV_NUMERO_DOCUMENTO', 'PROV_RAZON_SOCIAL']),
                'estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO']),
            ])
            ->orderBy('OFER_TOTAL_GENERAL')
            ->get();

        $aprobadas = $ofertas->filter(fn (Oferta $o) => $o->estado->ESTA_CODIGO === 'APROBADA');
        $pendientes = $ofertas->filter(fn (Oferta $o) => $o->estado->ESTA_CODIGO === 'EN_EVALUACION');
        $recomendada = $aprobadas->sortBy('OFER_TOTAL_GENERAL')->first();

        $items = $ofertas->map(fn (Oferta $o) => [
            'oferta_id' => $o->OFER_ID,
            'proveedor' => ['proveedor_id' => $o->proveedor->PROV_ID, 'numero_documento' => $o->proveedor->PROV_NUMERO_DOCUMENTO, 'razon_social' => $o->proveedor->PROV_RAZON_SOCIAL],
            'total_general' => $o->OFER_TOTAL_GENERAL,
            'eval_tecnica' => $o->OFER_EVAL_TECNICA,
            'eval_logistica' => $o->OFER_EVAL_LOGISTICA,
            'estado' => $o->estado->ESTA_CODIGO,
            'es_recomendada' => $recomendada && $o->OFER_ID === $recomendada->OFER_ID,
        ]);

        return ApiResponse::success([
            'items' => $items,
            'puede_adjudicar' => $aprobadas->isNotEmpty() && $pendientes->isEmpty(),
            'recomendada_oferta_id' => $recomendada?->OFER_ID,
        ]);
    }
}
