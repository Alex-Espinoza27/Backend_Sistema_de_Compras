<?php

namespace App\Services;

use App\Models\Archivo;
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

        // Evaluar ya no espera a que venza el plazo (05C hardening, pedido
        // explícitamente por el usuario): la entidad puede empezar a
        // calificar ofertas apenas llegan, mientras la convocatoria sigue
        // PUBLICADA. Adjudicar sigue exigiendo EN_EVALUACION (plazo
        // realmente vencido) — ver AdjudicacionService::adjudicar/desierta.
        if (! in_array($codigoConvocatoria, ['PUBLICADA', 'EN_EVALUACION'], true)) {
            throw new HttpException(409, 'Solo se puede evaluar mientras la convocatoria admite postulaciones o está en evaluación.');
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
                    // La primera evaluación saca a la oferta de ENVIADA (05C
                    // hardening): $codigoDestino nunca vale ENVIADA, así que
                    // no hace falta un self-loop para ese origen.
                    'ENVIADA' => ['EN_EVALUACION', 'APROBADA', 'DESAPROBADA'],
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

    /**
     * Shape de detalle de una oferta para evaluación (ítems + archivos +
     * condiciones + resultado de las dos compuertas). Reutilizado por
     * `EvaluacionController::evaluacionDetalle` y por
     * `PresentacionOfertasController::show` (M06 del TDR, 05C hardening) —
     * la entidad ve exactamente los mismos datos desde ambos módulos.
     */
    public function detalle(Oferta $oferta): array
    {
        $oferta->load([
            'proveedor' => fn ($q) => $q->select(['PROV_ID', 'PROV_NUMERO_DOCUMENTO', 'PROV_RAZON_SOCIAL']),
            'items' => fn ($q) => $q->select(['OFIT_ID', 'OFIT_OFERTA_ID', 'OFIT_REQUERIMIENTO_ITEM_ID', 'OFIT_MARCA_MODELO', 'OFIT_PRECIO_UNITARIO', 'OFIT_PRECIO_TOTAL']),
            'items.requerimientoItem' => fn ($q) => $q->select(['REIT_ID', 'REIT_NUMERO', 'REIT_DESCRIPCION', 'REIT_UNIDAD_MEDIDA_ID', 'REIT_CANTIDAD', 'REIT_PRESENTACION'])
                ->with(['unidadMedida' => fn ($qq) => $qq->select(['UNME_ID', 'UNME_CODIGO', 'UNME_NOMBRE'])]),
        ]);

        $archivos = Archivo::select(['ARCH_ID', 'ARCH_CONTEXTO', 'ARCH_NOMBRE_ORIGINAL', 'ARCH_HASH_SHA256'])
            ->where('ARCH_REFERENCIA_ID', $oferta->OFER_ID)
            ->whereIn('ARCH_CONTEXTO', ['OFERTA_DJ', 'OFERTA_SUSTENTO'])
            ->get();

        return [
            'oferta_id' => $oferta->OFER_ID,
            'proveedor' => ['proveedor_id' => $oferta->proveedor->PROV_ID, 'numero_documento' => $oferta->proveedor->PROV_NUMERO_DOCUMENTO, 'razon_social' => $oferta->proveedor->PROV_RAZON_SOCIAL],
            'items' => $oferta->items->map(fn ($item) => [
                'numero' => $item->requerimientoItem->REIT_NUMERO,
                'descripcion' => $item->requerimientoItem->REIT_DESCRIPCION,
                'unidad_medida' => ['codigo' => $item->requerimientoItem->unidadMedida->UNME_CODIGO, 'nombre' => $item->requerimientoItem->unidadMedida->UNME_NOMBRE],
                'cantidad' => $item->requerimientoItem->REIT_CANTIDAD,
                'presentacion' => $item->requerimientoItem->REIT_PRESENTACION,
                'marca_modelo' => $item->OFIT_MARCA_MODELO,
                'precio_unitario' => $item->OFIT_PRECIO_UNITARIO,
                'precio_total' => $item->OFIT_PRECIO_TOTAL,
            ]),
            'archivos' => $archivos->map(fn ($a) => [
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
        ];
    }
}
