<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluacion\EvaluarOfertaRequest;
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

        // PUBLICADA incluida (05C hardening, pedido explícitamente por el
        // usuario): se puede evaluar antes de que venza el plazo.
        if (! in_array($codigoConvocatoria, ['PUBLICADA', 'EN_EVALUACION', 'ADJUDICADA', 'DESIERTA'], true)) {
            throw new HttpException(409, 'Las ofertas de esta convocatoria aún no están disponibles para evaluación.');
        }

        $items = Oferta::query()
            ->select(['OFER_ID', 'OFER_PROVEEDOR_ID', 'OFER_CONSTANCIA_CODIGO', 'OFER_TOTAL_GENERAL', 'OFER_TIPO_COMPROBANTE_ID', 'OFER_EVAL_TECNICA', 'OFER_EVAL_LOGISTICA', 'OFER_ESTADO_ID', 'OFER_FECHA_ENVIO'])
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
                'constancia_codigo' => $o->OFER_CONSTANCIA_CODIGO,
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
            ->findOrFail($id);

        Convocatoria::select(['CONV_ID'])->findOrFail($oferta->OFER_CONVOCATORIA_ID);

        return ApiResponse::success($this->evaluacionService->detalle($oferta));
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
