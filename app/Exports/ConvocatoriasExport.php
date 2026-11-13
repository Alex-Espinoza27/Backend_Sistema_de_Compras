<?php

namespace App\Exports;

use App\Models\Archivo;
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
 * Datos abiertos (02A §8, 02B M09): solo convocatorias no BORRADOR.
 *
 * 05C hardening (pedido explícitamente por el usuario): la ficha pública
 * ofrece esta descarga como "bases, requerimientos, fechas límites de
 * postulación y estado actual de cada proceso" — antes el archivo solo traía
 * fechas/estado/entidad y nunca el tipo, la descripción del requerimiento ni
 * el enlace a las bases (TDR), así que la promesa del texto no se cumplía.
 * Se agregan esas columnas y el mismo estilo institucional de ReporteExport.
 */
class ConvocatoriasExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles
{
    /** `$entidadId` opcional (05C hardening) — acota la descarga a una sola
     * entidad, usado por el portal libre por entidad (`/entidad/{id}`). */
    public function __construct(private ?int $entidadId = null) {}

    public function query(): Builder
    {
        $borradorId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', 'BORRADOR')->value('ESTA_ID');

        return Convocatoria::query()
            ->select([
                'CONV_ID', 'CONV_REQUERIMIENTO_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ENTIDAD_ID',
                'CONV_FECHA_INICIO', 'CONV_FECHA_FIN', 'CONV_ESTADO_ID',
                'CONV_FECHA_PUBLICACION', 'CONV_FECHA_RESULTADO',
            ])
            ->where('CONV_ESTADO_ID', '!=', $borradorId)
            ->when($this->entidadId, fn ($q) => $q->where('CONV_ENTIDAD_ID', $this->entidadId))
            ->with([
                'entidad' => fn ($sub) => $sub->select(['ENTI_ID', 'ENTI_SIGLA']),
                'estado' => fn ($sub) => $sub->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE']),
                // `withTrashed()`: una convocatoria concluida puede referenciar
                // un requerimiento ya borrado (soft delete) — igual que
                // PublicoController::convocatorias, no debe romper el export.
                'requerimiento' => fn ($sub) => $sub->select(['REQU_ID', 'REQU_TIPO', 'REQU_DESCRIPCION'])->withTrashed(),
            ])
            ->orderByDesc('CONV_FECHA_PUBLICACION');
    }

    public function headings(): array
    {
        return [
            'Número', 'Nombre del servicio', 'Tipo', 'Descripción del requerimiento', 'Entidad',
            'Fecha inicio', 'Fecha fin (límite de postulación)', 'Estado', 'Fecha publicación',
            'Fecha resultado', 'Enlace de bases (TDR/EETT)',
        ];
    }

    public function map($conv): array
    {
        $tieneTdr = Archivo::where('ARCH_CONTEXTO', 'REQUERIMIENTO_TDR')
            ->where('ARCH_REFERENCIA_ID', $conv->CONV_REQUERIMIENTO_ID)
            ->exists();

        return [
            $conv->CONV_NUMERO,
            $conv->CONV_NOMBRE_SERVICIO,
            $conv->requerimiento?->REQU_TIPO === 'BIEN' ? 'Bien' : 'Servicio',
            $conv->requerimiento?->REQU_DESCRIPCION,
            $conv->entidad->ENTI_SIGLA,
            optional($conv->CONV_FECHA_INICIO)->format('Y-m-d H:i'),
            optional($conv->CONV_FECHA_FIN)->format('Y-m-d H:i'),
            $conv->estado->ESTA_NOMBRE,
            optional($conv->CONV_FECHA_PUBLICACION)->format('Y-m-d H:i'),
            optional($conv->CONV_FECHA_RESULTADO)->format('Y-m-d H:i'),
            $tieneTdr ? url("/api/v1/publico/convocatorias/{$conv->CONV_ID}/tdr") : 'No disponible',
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
