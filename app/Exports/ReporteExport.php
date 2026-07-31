<?php

namespace App\Exports;

use App\Services\ReporteService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exportación XLSX de reportes (02B M10), reutilizando ReporteService para
 * no duplicar la lógica de filtros/columnas del endpoint JSON.
 */
class ReporteExport implements FromQuery, WithHeadings, WithMapping
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
}
