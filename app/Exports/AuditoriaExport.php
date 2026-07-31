<?php

namespace App\Exports;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exporta la bitácora filtrada (02B M12, ítem 3). Reutiliza el mismo builder
 * de filtros que AuditoriaController::index(), inyectado ya construido.
 */
class AuditoriaExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return ['Fecha', 'Usuario', 'Módulo', 'Acción', 'Tabla', 'Registro', 'IP'];
    }

    public function map($fila): array
    {
        /** @var Auditoria $fila */
        return [
            optional($fila->AUDI_FECHA)->format('Y-m-d H:i:s'),
            $fila->user?->name ?? 'Sistema',
            $fila->AUDI_MODULO,
            $fila->AUDI_ACCION,
            $fila->AUDI_TABLA,
            $fila->AUDI_REGISTRO_ID,
            $fila->AUDI_IP,
        ];
    }
}
