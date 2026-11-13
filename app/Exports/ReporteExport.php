<?php

namespace App\Exports;

use App\Services\ReporteService;
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
 * Exportación XLSX de reportes (02B M10), reutilizando ReporteService para
 * no duplicar la lógica de filtros/columnas del endpoint JSON.
 *
 * 05C hardening (pedido explícitamente por el usuario) — el archivo se veía
 * "muy mal": cabecera sin resaltar y columnas sin autoajuste. Se agrega
 * cabecera institucional (fondo azul, texto blanco, negrita), fila congelada
 * para desplazarse sin perderla de vista, bordes finos en todas las celdas
 * con datos y autoajuste de ancho de columna.
 */
class ReporteExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private readonly ReporteService $reporteService,
        private readonly array $filtros,
    ) {}

    public function query(): Builder
    {
        return $this->reporteService->buscar($this->filtros);
    }

    public function headings(): array
    {
        return $this->reporteService->cabeceras($this->filtros['tipo']);
    }

    public function map($fila): array
    {
        return array_values($this->reporteService->mapearFila($this->filtros['tipo'], $fila));
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

                // Bandas alternadas (zebra) para que sea más fácil leer una fila
                // completa sin perder la columna, igual que las tablas de pantalla.
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
