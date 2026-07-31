<?php

namespace App\Services;

use App\Models\Convocatoria;
use App\Models\Estado;
use App\Models\Oferta;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Dos compuertas + precio (00 §7, 02B M07): evaluación técnica y logística,
 * cada una APROBADO|DESAPROBADO con motivo obligatorio si desaprueba.
 * Reevaluable mientras la convocatoria siga EN_EVALUACION; cada llamada
 * audita EVALUAR, cambie o no el estado resultante de la oferta.
 */
class EvaluacionService
{
    public function __construct(
        private EstadoService $estadoService,
    ) {}

    public function evaluar(Oferta $oferta, array $datos): Oferta
    {
        $convocatoria = Convocatoria::select(['CONV_ID', 'CONV_ESTADO_ID'])->findOrFail($oferta->OFER_CONVOCATORIA_ID);
        $codigoConvocatoria = Estado::where('ESTA_ID', $convocatoria->CONV_ESTADO_ID)->value('ESTA_CODIGO');

        if ($codigoConvocatoria !== 'EN_EVALUACION') {
            throw new HttpException(409, 'Solo se puede evaluar mientras la convocatoria está en evaluación.');
        }

        $evalTecnica = array_key_exists('eval_tecnica', $datos) ? $datos['eval_tecnica'] : $oferta->OFER_EVAL_TECNICA;
        $evalLogistica = array_key_exists('eval_logistica', $datos) ? $datos['eval_logistica'] : $oferta->OFER_EVAL_LOGISTICA;

        $codigoDestino = match (true) {
            $evalTecnica === 'APROBADO' && $evalLogistica === 'APROBADO' => 'APROBADA',
            $evalTecnica === 'DESAPROBADO' || $evalLogistica === 'DESAPROBADO' => 'DESAPROBADA',
            default => 'EN_EVALUACION',
        };

        return DB::transaction(function () use ($oferta, $datos, $codigoDestino) {
            if (array_key_exists('eval_tecnica', $datos)) {
                $oferta->OFER_EVAL_TECNICA = $datos['eval_tecnica'];
                $oferta->OFER_EVAL_TECNICA_MOTIVO = $datos['eval_tecnica_motivo'] ?? null;
            }

            if (array_key_exists('eval_logistica', $datos)) {
                $oferta->OFER_EVAL_LOGISTICA = $datos['eval_logistica'];
                $oferta->OFER_EVAL_LOGISTICA_MOTIVO = $datos['eval_logistica_motivo'] ?? null;
            }

            $oferta->OFER_EVALUADA_POR = auth()->id();
            $oferta->OFER_FECHA_EVALUACION = now();

            $this->estadoService->transicionar(
                modelo: $oferta,
                columnaEstado: 'OFER_ESTADO_ID',
                contexto: 'OFERTA',
                codigoDestino: $codigoDestino,
                transicionesPermitidas: [
                    'EN_EVALUACION' => ['EN_EVALUACION', 'APROBADA', 'DESAPROBADA'],
                    'APROBADA' => ['APROBADA', 'DESAPROBADA', 'EN_EVALUACION'],
                    'DESAPROBADA' => ['DESAPROBADA', 'APROBADA', 'EN_EVALUACION'],
                ],
                modulo: 'EVALUACION',
                tabla: 'OFERTA',
                accion: 'EVALUAR',
                datosAdicionales: [
                    'eval_tecnica' => $oferta->OFER_EVAL_TECNICA,
                    'eval_logistica' => $oferta->OFER_EVAL_LOGISTICA,
                ],
            );

            return $oferta;
        });
    }
}
