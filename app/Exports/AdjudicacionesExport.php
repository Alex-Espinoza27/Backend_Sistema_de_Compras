<?php

namespace App\Exports;

use App\Models\Convocatoria;
use App\Models\Estado;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Datos abiertos (02A §8, 02B M09): solo adjudicaciones resueltas, nunca ofertas en curso.
 */
class AdjudicacionesExport implements FromQuery, WithHeadings, WithMapping
{
    public function query(): Builder
    {
        $adjudicadaId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', 'ADJUDICADA')->value('ESTA_ID');

        return Convocatoria::query()
            ->select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ENTIDAD_ID', 'CONV_OFERTA_ADJUDICADA_ID', 'CONV_FECHA_RESULTADO'])
            ->where('CONV_ESTADO_ID', $adjudicadaId)
            ->with([
                'entidad' => fn ($sub) => $sub->select(['ENTI_ID', 'ENTI_SIGLA']),
                'ofertaAdjudicada' => fn ($sub) => $sub->select(['OFER_ID', 'OFER_PROVEEDOR_ID', 'OFER_TOTAL_GENERAL'])
                    ->with(['proveedor' => fn ($sub2) => $sub2->select(['PROV_ID', 'PROV_NUMERO_DOCUMENTO', 'PROV_RAZON_SOCIAL'])]),
            ])
            ->orderByDesc('CONV_FECHA_RESULTADO');
    }

    public function headings(): array
    {
        return ['Número convocatoria', 'Nombre del servicio', 'Entidad', 'RUC proveedor', 'Proveedor adjudicado', 'Monto adjudicado', 'Fecha de resultado'];
    }

    public function map($conv): array
    {
        $oferta = $conv->ofertaAdjudicada;

        return [
            $conv->CONV_NUMERO,
            $conv->CONV_NOMBRE_SERVICIO,
            $conv->entidad->ENTI_SIGLA,
            $oferta?->proveedor?->PROV_NUMERO_DOCUMENTO,
            $oferta?->proveedor?->PROV_RAZON_SOCIAL,
            $oferta?->OFER_TOTAL_GENERAL,
            optional($conv->CONV_FECHA_RESULTADO)->format('Y-m-d H:i'),
        ];
    }
}
