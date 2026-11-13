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
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatosAbiertosController extends Controller
{
    // 05C hardening: `entidad_id` opcional en los 4 endpoints de descarga y
    // en estadísticas — permite mostrar los mismos datos de "Datos abiertos"
    // acotados a una sola entidad, pedido explícitamente para el portal
    // libre por entidad (`/entidad/{id}`).
    public function convocatoriasCsv(Request $request): BinaryFileResponse
    {
        return Excel::download(new ConvocatoriasExport($request->integer('entidad_id') ?: null), 'convocatorias.csv', ExcelFormat::CSV);
    }

    public function convocatoriasXlsx(Request $request): BinaryFileResponse
    {
        return Excel::download(new ConvocatoriasExport($request->integer('entidad_id') ?: null), 'convocatorias.xlsx', ExcelFormat::XLSX);
    }

    public function adjudicacionesCsv(Request $request): BinaryFileResponse
    {
        return Excel::download(new AdjudicacionesExport($request->integer('entidad_id') ?: null), 'adjudicaciones.csv', ExcelFormat::CSV);
    }

    public function adjudicacionesXlsx(Request $request): BinaryFileResponse
    {
        return Excel::download(new AdjudicacionesExport($request->integer('entidad_id') ?: null), 'adjudicaciones.xlsx', ExcelFormat::XLSX);
    }

    public function estadisticas(Request $request): JsonResponse
    {
        $entidadId = $request->integer('entidad_id') ?: null;
        $borradorId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', 'BORRADOR')->value('ESTA_ID');
        $publicadaId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', 'PUBLICADA')->value('ESTA_ID');
        $ahora = now();

        $porEstado = Convocatoria::query()
            ->select(['CONV_ESTADO_ID'])
            ->selectRaw('count(*) as total')
            ->where('CONV_ESTADO_ID', '!=', $borradorId)
            // Una PUBLICADA solo cuenta como "Vigente" aquí si cae dentro de su
            // ventana de fechas (05C hardening, pedido explícitamente por el
            // usuario) — mismo criterio que `PublicoController::convocatorias`
            // aplica al listado real de VIGENTES. Antes este conteo incluía
            // también PUBLICADA que todavía no inician o ya vencieron sin que
            // el scheduler las transicionara, dando un total de tarjeta mayor
            // al que realmente aparecía al filtrar por "Vigentes".
            ->when($publicadaId, fn ($q) => $q->where(function ($sub) use ($publicadaId, $ahora) {
                $sub->where('CONV_ESTADO_ID', '!=', $publicadaId)
                    ->orWhere(function ($sub2) use ($publicadaId, $ahora) {
                        $sub2->where('CONV_ESTADO_ID', $publicadaId)
                            ->where('CONV_FECHA_INICIO', '<=', $ahora)
                            ->where('CONV_FECHA_FIN', '>=', $ahora);
                    });
            }))
            ->when($entidadId, fn ($q) => $q->where('CONV_ENTIDAD_ID', $entidadId))
            ->with(['estado' => fn ($sub) => $sub->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE'])])
            ->groupBy('CONV_ESTADO_ID')
            ->get()
            ->map(fn ($fila) => [
                'codigo' => $fila->estado->ESTA_CODIGO,
                'nombre' => $fila->estado->ESTA_NOMBRE,
                'total' => (int) $fila->total,
            ])
            ->values();

        $proveedoresParticipantes = Oferta::query()
            ->when($entidadId, fn ($q) => $q->whereHas('convocatoria', fn ($sub) => $sub->where('CONV_ENTIDAD_ID', $entidadId)))
            ->distinct('OFER_PROVEEDOR_ID')
            ->count('OFER_PROVEEDOR_ID');

        return ApiResponse::success([
            'convocatorias_por_estado' => $porEstado,
            'proveedores_participantes' => $proveedoresParticipantes,
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
