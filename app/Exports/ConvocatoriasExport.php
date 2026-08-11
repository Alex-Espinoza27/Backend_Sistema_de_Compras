<?php

namespace App\Exports;

use App\Models\Convocatoria;
use App\Models\Estado;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Datos abiertos (02A §8, 02B M09): solo convocatorias no BORRADOR.
 */
class ConvocatoriasExport implements FromQuery, WithHeadings, WithMapping
{
    /** `$entidadId` opcional (05C hardening) — acota la descarga a una sola
     * entidad, usado por el portal libre por entidad (`/entidad/{id}`). */
    public function __construct(private ?int $entidadId = null) {}

    public function query(): Builder
    {
        $borradorId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', 'BORRADOR')->value('ESTA_ID');

        return Convocatoria::query()
            ->select([
                'CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ENTIDAD_ID',
                'CONV_FECHA_INICIO', 'CONV_FECHA_FIN', 'CONV_ESTADO_ID',
                'CONV_FECHA_PUBLICACION', 'CONV_FECHA_RESULTADO',
            ])
            ->where('CONV_ESTADO_ID', '!=', $borradorId)
            ->when($this->entidadId, fn ($q) => $q->where('CONV_ENTIDAD_ID', $this->entidadId))
            ->with([
                'entidad' => fn ($sub) => $sub->select(['ENTI_ID', 'ENTI_SIGLA']),
                'estado' => fn ($sub) => $sub->select(['ESTA_ID', 'ESTA_CODIGO']),
            ])
            ->orderByDesc('CONV_FECHA_PUBLICACION');
    }

    public function headings(): array
    {
        return ['Número', 'Nombre del servicio', 'Entidad', 'Fecha inicio', 'Fecha fin', 'Estado', 'Fecha publicación', 'Fecha resultado'];
    }

    public function map($conv): array
    {
        return [
            $conv->CONV_NUMERO,
            $conv->CONV_NOMBRE_SERVICIO,
            $conv->entidad->ENTI_SIGLA,
            optional($conv->CONV_FECHA_INICIO)->format('Y-m-d H:i'),
            optional($conv->CONV_FECHA_FIN)->format('Y-m-d H:i'),
            $conv->estado->ESTA_CODIGO,
            optional($conv->CONV_FECHA_PUBLICACION)->format('Y-m-d H:i'),
            optional($conv->CONV_FECHA_RESULTADO)->format('Y-m-d H:i'),
        ];
    }
}
