<?php

namespace App\Exports;

use App\Models\Convocatoria;
use App\Models\Estado;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Datos abiertos (02A §8, 02B M09): solo adjudicaciones resueltas, nunca ofertas en curso.
 *
 * 05C hardening (pedido explícitamente por el usuario): la ficha pública
 * promete "resultados definitivos, proveedores ganadores, actas y montos
 * contratados" — antes no traía el número de indagación ni el enlace al acta
 * de resultados (PDF), así que "actas" no estaba realmente en el archivo.
 * Se agregan esas columnas y el mismo estilo institucional de ReporteExport.
 */
class AdjudicacionesExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles
{
    /** `$entidadId` opcional (05C hardening) — acota la descarga a una sola
     * entidad, usado por el portal libre por entidad (`/entidad/{id}`). */
    public function __construct(private ?int $entidadId = null) {}

    public function query(): Builder
    {
        $adjudicadaId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', 'ADJUDICADA')->value('ESTA_ID');

        return Convocatoria::query()
            ->select([
                'CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ENTIDAD_ID',
                'CONV_OFERTA_ADJUDICADA_ID', 'CONV_FECHA_RESULTADO', 'CONV_NUMERO_INDAGACION',
            ])
            ->where('CONV_ESTADO_ID', $adjudicadaId)
            ->when($this->entidadId, fn ($q) => $q->where('CONV_ENTIDAD_ID', $this->entidadId))
            ->with([
                'entidad' => fn ($sub) => $sub->select(['ENTI_ID', 'ENTI_SIGLA']),
                'ofertaAdjudicada' => fn ($sub) => $sub->select(['OFER_ID', 'OFER_PROVEEDOR_ID', 'OFER_TOTAL_GENERAL'])
                    ->with(['proveedor' => fn ($sub2) => $sub2->select(['PROV_ID', 'PROV_NUMERO_DOCUMENTO', 'PROV_RAZON_SOCIAL'])]),
            ])
            ->orderByDesc('CONV_FECHA_RESULTADO');
    }

    public function headings(): array
    {
        return [
            'Número convocatoria', 'Nombre del servicio', 'Entidad', 'RUC proveedor', 'Proveedor adjudicado',
            'Monto adjudicado', 'Fecha de resultado', 'Número de indagación', 'Enlace del acta de resultados (PDF)',
        ];
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
            $conv->CONV_NUMERO_INDAGACION,
            $conv->CONV_NUMERO_INDAGACION ? url("/api/v1/publico/convocatorias/{$conv->CONV_ID}/resultados.pdf") : 'No disponible',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A2947']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $filas = $sheet->getHighestRow();
                $columnas = $sheet->getHighestColumn();

                $sheet->getRowDimension(1)->setRowHeight(22);
                $sheet->freezePane('A2');

                $sheet->getStyle("A1:{$columnas}{$filas}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9D9D9');

                for ($fila = 2; $fila <= $filas; $fila++) {
                    if ($fila % 2 === 0) {
                        $sheet->getStyle("A{$fila}:{$columnas}{$fila}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F8FB');
                    }
                }
            },
        ];
    }
}
