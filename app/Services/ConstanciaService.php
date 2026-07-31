<?php

namespace App\Services;

use App\Models\Oferta;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * PDF de constancia de presentación al enviar una oferta (02A §7.8, 00 §6).
 * Se genera al vuelo (no hay contexto CONSTANCIA en ARCHIVO, 01 §4.5).
 */
class ConstanciaService
{
    public function generarPdf(Oferta $oferta): DomPdf
    {
        $oferta->loadMissing([
            'convocatoria' => fn ($q) => $q->select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO']),
            'proveedor' => fn ($q) => $q->select(['PROV_ID', 'PROV_NUMERO_DOCUMENTO', 'PROV_RAZON_SOCIAL']),
            'items' => fn ($q) => $q->select(['OFIT_ID', 'OFIT_OFERTA_ID', 'OFIT_REQUERIMIENTO_ITEM_ID', 'OFIT_MARCA_MODELO', 'OFIT_PRECIO_UNITARIO', 'OFIT_PRECIO_TOTAL']),
            'items.requerimientoItem' => fn ($q) => $q->select(['REIT_ID', 'REIT_NUMERO', 'REIT_DESCRIPCION']),
        ]);

        return Pdf::loadView('pdf.constancia-oferta', ['oferta' => $oferta]);
    }
}
