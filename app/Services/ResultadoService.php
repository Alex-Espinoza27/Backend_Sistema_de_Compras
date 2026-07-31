<?php

namespace App\Services;

use App\Models\Convocatoria;
use App\Models\Oferta;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * PDF público de resultados de una convocatoria adjudicada (02A §7.8, 00 §8.1).
 * Incluye a todos los postores, no solo al ganador (00 §8, ítem 6).
 */
class ResultadoService
{
    public function generarPdf(Convocatoria $convocatoria): DomPdf
    {
        // Se re-consulta con las columnas propias que necesita la plantilla:
        // no se puede confiar en qué seleccionó el llamador (02A §12).
        $convocatoria = Convocatoria::select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ENTIDAD_ID'])
            ->with(['entidad' => fn ($sub) => $sub->select(['ENTI_ID', 'ENTI_RAZON_SOCIAL'])])
            ->findOrFail($convocatoria->CONV_ID);

        $ofertas = Oferta::query()
            ->select([
                'OFER_ID', 'OFER_PROVEEDOR_ID', 'OFER_ESTADO_ID',
                'OFER_EVAL_TECNICA', 'OFER_EVAL_TECNICA_MOTIVO',
                'OFER_EVAL_LOGISTICA', 'OFER_EVAL_LOGISTICA_MOTIVO',
                'OFER_TOTAL_GENERAL',
            ])
            ->where('OFER_CONVOCATORIA_ID', $convocatoria->CONV_ID)
            ->with([
                'proveedor' => fn ($sub) => $sub->select(['PROV_ID', 'PROV_NUMERO_DOCUMENTO', 'PROV_RAZON_SOCIAL']),
                'estado' => fn ($sub) => $sub->select(['ESTA_ID', 'ESTA_NOMBRE']),
            ])
            ->orderBy('OFER_ID')
            ->get();

        return Pdf::loadView('pdf.resultados-convocatoria', [
            'convocatoria' => $convocatoria,
            'ofertas' => $ofertas,
        ]);
    }
}
