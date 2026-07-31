<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adjudicacion\AdjudicarConvocatoriaRequest;
use App\Http\Requests\Adjudicacion\DesiertaConvocatoriaRequest;
use App\Models\Convocatoria;
use App\Models\Oferta;
use App\Services\AdjudicacionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdjudicacionController extends Controller
{
    public function __construct(private AdjudicacionService $adjudicacionService) {}

    public function adjudicar(AdjudicarConvocatoriaRequest $request, int $id): JsonResponse
    {
        $convocatoria = Convocatoria::select(['CONV_ID', 'CONV_ENTIDAD_ID', 'CONV_ESTADO_ID', 'CONV_NUMERO'])->findOrFail($id);

        $resultado = $this->adjudicacionService->adjudicar($convocatoria, $request->input('oferta_id'));

        return ApiResponse::success($resultado, 'Convocatoria adjudicada.');
    }

    public function desierta(DesiertaConvocatoriaRequest $request, int $id): JsonResponse
    {
        $convocatoria = Convocatoria::select(['CONV_ID', 'CONV_ENTIDAD_ID', 'CONV_ESTADO_ID', 'CONV_NUMERO'])->findOrFail($id);

        $this->adjudicacionService->desierta($convocatoria, $request->input('motivo'));

        return ApiResponse::success(null, 'Convocatoria declarada desierta.');
    }

    public function resultados(int $id): JsonResponse
    {
        $convocatoria = Convocatoria::select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ESTADO_ID', 'CONV_OFERTA_ADJUDICADA_ID', 'CONV_MOTIVO_CIERRE', 'CONV_FECHA_RESULTADO'])
            ->with(['estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE'])])
            ->findOrFail($id);

        if (! in_array($convocatoria->estado->ESTA_CODIGO, ['ADJUDICADA', 'DESIERTA'], true)) {
            throw new HttpException(409, 'Esta convocatoria aún no tiene resultados.');
        }

        $ofertas = Oferta::select(['OFER_ID', 'OFER_PROVEEDOR_ID', 'OFER_TOTAL_GENERAL', 'OFER_ESTADO_ID', 'OFER_EVAL_TECNICA', 'OFER_EVAL_TECNICA_MOTIVO', 'OFER_EVAL_LOGISTICA', 'OFER_EVAL_LOGISTICA_MOTIVO'])
            ->where('OFER_CONVOCATORIA_ID', $id)
            ->with([
                'proveedor' => fn ($q) => $q->select(['PROV_ID', 'PROV_NUMERO_DOCUMENTO', 'PROV_RAZON_SOCIAL']),
                'estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE']),
            ])
            ->orderBy('OFER_ID')
            ->get()
            ->map(fn (Oferta $o) => [
                'oferta_id' => $o->OFER_ID,
                'proveedor' => ['proveedor_id' => $o->proveedor->PROV_ID, 'numero_documento' => $o->proveedor->PROV_NUMERO_DOCUMENTO, 'razon_social' => $o->proveedor->PROV_RAZON_SOCIAL],
                'eval_tecnica' => $o->OFER_EVAL_TECNICA,
                'eval_tecnica_motivo' => $o->OFER_EVAL_TECNICA_MOTIVO,
                'eval_logistica' => $o->OFER_EVAL_LOGISTICA,
                'eval_logistica_motivo' => $o->OFER_EVAL_LOGISTICA_MOTIVO,
                'resultado' => $o->estado->ESTA_CODIGO,
                'monto' => $o->OFER_TOTAL_GENERAL,
            ]);

        return ApiResponse::success([
            'convocatoria_id' => $convocatoria->CONV_ID,
            'numero' => $convocatoria->CONV_NUMERO,
            'nombre_servicio' => $convocatoria->CONV_NOMBRE_SERVICIO,
            'estado' => ['codigo' => $convocatoria->estado->ESTA_CODIGO, 'nombre' => $convocatoria->estado->ESTA_NOMBRE],
            'oferta_adjudicada_id' => $convocatoria->CONV_OFERTA_ADJUDICADA_ID,
            'motivo_cierre' => $convocatoria->CONV_MOTIVO_CIERRE,
            'fecha_resultado' => $convocatoria->CONV_FECHA_RESULTADO,
            'ofertas' => $ofertas,
        ]);
    }
}
