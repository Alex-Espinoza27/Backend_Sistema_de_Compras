<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\AdjudicacionesExport;
use App\Exports\ConvocatoriasExport;
use App\Http\Controllers\Controller;
use App\Models\Convocatoria;
use App\Models\Estado;
use App\Models\Oferta;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatosAbiertosController extends Controller
{
    public function convocatoriasCsv(): BinaryFileResponse
    {
        return Excel::download(new ConvocatoriasExport(), 'convocatorias.csv', ExcelFormat::CSV);
    }

    public function convocatoriasXlsx(): BinaryFileResponse
    {
        return Excel::download(new ConvocatoriasExport(), 'convocatorias.xlsx', ExcelFormat::XLSX);
    }

    public function adjudicacionesCsv(): BinaryFileResponse
    {
        return Excel::download(new AdjudicacionesExport(), 'adjudicaciones.csv', ExcelFormat::CSV);
    }

    public function adjudicacionesXlsx(): BinaryFileResponse
    {
        return Excel::download(new AdjudicacionesExport(), 'adjudicaciones.xlsx', ExcelFormat::XLSX);
    }

    public function estadisticas(): JsonResponse
    {
        $borradorId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', 'BORRADOR')->value('ESTA_ID');
        $adjudicadaId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', 'ADJUDICADA')->value('ESTA_ID');

        $porEstado = Convocatoria::query()
            ->select(['CONV_ESTADO_ID'])
            ->selectRaw('count(*) as total')
            ->where('CONV_ESTADO_ID', '!=', $borradorId)
            ->with(['estado' => fn ($sub) => $sub->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE'])])
            ->groupBy('CONV_ESTADO_ID')
            ->get()
            ->map(fn ($fila) => [
                'codigo' => $fila->estado->ESTA_CODIGO,
                'nombre' => $fila->estado->ESTA_NOMBRE,
                'total' => (int) $fila->total,
            ])
            ->values();

        $adjudicadasPorMes = Convocatoria::query()
            ->join('OFERTA', 'OFERTA.OFER_ID', '=', 'CONVOCATORIA.CONV_OFERTA_ADJUDICADA_ID')
            ->where('CONVOCATORIA.CONV_ESTADO_ID', $adjudicadaId)
            ->selectRaw('to_char("CONVOCATORIA"."CONV_FECHA_RESULTADO", \'YYYY-MM\') as mes')
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum("OFERTA"."OFER_TOTAL_GENERAL"), 0) as monto_adjudicado')
            ->groupByRaw('to_char("CONVOCATORIA"."CONV_FECHA_RESULTADO", \'YYYY-MM\')')
            ->orderBy('mes')
            ->get()
            ->map(fn ($fila) => [
                'mes' => $fila->mes,
                'total' => (int) $fila->total,
                'monto_adjudicado' => (float) $fila->monto_adjudicado,
            ]);

        $proveedoresParticipantes = Oferta::query()->distinct('OFER_PROVEEDOR_ID')->count('OFER_PROVEEDOR_ID');

        return ApiResponse::success([
            'convocatorias_por_estado' => $porEstado,
            'adjudicadas_por_mes' => $adjudicadasPorMes,
            'proveedores_participantes' => $proveedoresParticipantes,
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
