<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardRequest;
use App\Models\Convocatoria;
use App\Models\Estado;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(DashboardRequest $request): JsonResponse
    {
        $user = $request->user();
        $esAdminOAuditor = $user->hasAnyRole(['ADMINISTRADOR', 'AUDITOR']);
        $entidadId = $esAdminOAuditor ? $request->integer('entidad_id') ?: null : $user->entidad_id;

        $desde = $request->filled('desde') ? Carbon::parse($request->input('desde'))->startOfDay() : null;
        $hasta = $request->filled('hasta') ? Carbon::parse($request->input('hasta'))->endOfDay() : null;

        $estados = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->pluck('ESTA_ID', 'ESTA_CODIGO');
        $ahora = now();

        $convocatoriasVigentes = Convocatoria::query()
            ->when($entidadId, fn ($q) => $q->where('CONV_ENTIDAD_ID', $entidadId))
            ->where('CONV_ESTADO_ID', $estados['PUBLICADA'])
            ->where('CONV_FECHA_INICIO', '<=', $ahora)
            ->where('CONV_FECHA_FIN', '>=', $ahora)
            ->count();

        $convocatoriasEnEvaluacion = Convocatoria::query()
            ->when($entidadId, fn ($q) => $q->where('CONV_ENTIDAD_ID', $entidadId))
            ->where('CONV_ESTADO_ID', $estados['EN_EVALUACION'])
            ->count();

        $contarResueltas = function (string $codigo) use ($entidadId, $desde, $hasta, $estados) {
            return Convocatoria::query()
                ->when($entidadId, fn ($q) => $q->where('CONV_ENTIDAD_ID', $entidadId))
                ->where('CONV_ESTADO_ID', $estados[$codigo])
                ->when($desde, fn ($q) => $q->where('CONV_FECHA_RESULTADO', '>=', $desde))
                ->when($hasta, fn ($q) => $q->where('CONV_FECHA_RESULTADO', '<=', $hasta))
                ->count();
        };

        $procesosAdjudicados = $contarResueltas('ADJUDICADA');
        $procesosDesiertos = $contarResueltas('DESIERTA');
        $procesosCancelados = $contarResueltas('CANCELADA');

        $proveedoresParticipantes = DB::table('OFERTA')
            ->join('CONVOCATORIA', 'CONVOCATORIA.CONV_ID', '=', 'OFERTA.OFER_CONVOCATORIA_ID')
            ->when($entidadId, fn ($q) => $q->where('CONVOCATORIA.CONV_ENTIDAD_ID', $entidadId))
            ->when($desde, fn ($q) => $q->where('OFERTA.OFER_FECHA_ENVIO', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('OFERTA.OFER_FECHA_ENVIO', '<=', $hasta))
            ->whereNull('OFERTA.OFER_ELIMINADO_EN')
            ->distinct()
            ->count('OFERTA.OFER_PROVEEDOR_ID');

        $montoTotalAdjudicado = DB::table('CONVOCATORIA')
            ->join('OFERTA', 'OFERTA.OFER_ID', '=', 'CONVOCATORIA.CONV_OFERTA_ADJUDICADA_ID')
            ->where('CONVOCATORIA.CONV_ESTADO_ID', $estados['ADJUDICADA'])
            ->when($entidadId, fn ($q) => $q->where('CONVOCATORIA.CONV_ENTIDAD_ID', $entidadId))
            ->when($desde, fn ($q) => $q->where('CONVOCATORIA.CONV_FECHA_RESULTADO', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('CONVOCATORIA.CONV_FECHA_RESULTADO', '<=', $hasta))
            ->sum('OFERTA.OFER_TOTAL_GENERAL');

        $adjudicadasPorMes = DB::table('CONVOCATORIA')
            ->join('OFERTA', 'OFERTA.OFER_ID', '=', 'CONVOCATORIA.CONV_OFERTA_ADJUDICADA_ID')
            ->where('CONVOCATORIA.CONV_ESTADO_ID', $estados['ADJUDICADA'])
            ->when($entidadId, fn ($q) => $q->where('CONVOCATORIA.CONV_ENTIDAD_ID', $entidadId))
            ->when($desde, fn ($q) => $q->where('CONVOCATORIA.CONV_FECHA_RESULTADO', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('CONVOCATORIA.CONV_FECHA_RESULTADO', '<=', $hasta))
            ->selectRaw('to_char("CONVOCATORIA"."CONV_FECHA_RESULTADO", \'YYYY-MM\') as mes')
            ->selectRaw('count(*) as cantidad')
            ->selectRaw('coalesce(sum("OFERTA"."OFER_TOTAL_GENERAL"), 0) as monto')
            ->groupByRaw('to_char("CONVOCATORIA"."CONV_FECHA_RESULTADO", \'YYYY-MM\')')
            ->orderBy('mes')
            ->get()
            ->map(fn ($fila) => ['mes' => $fila->mes, 'cantidad' => (int) $fila->cantidad, 'monto' => (float) $fila->monto]);

        $topRubros = DB::table('CONVOCATORIA')
            ->join('REQUERIMIENTO', 'REQUERIMIENTO.REQU_ID', '=', 'CONVOCATORIA.CONV_REQUERIMIENTO_ID')
            ->join('RUBRO', 'RUBRO.RUBR_ID', '=', 'REQUERIMIENTO.REQU_RUBRO_ID')
            ->where('CONVOCATORIA.CONV_ESTADO_ID', $estados['ADJUDICADA'])
            ->when($entidadId, fn ($q) => $q->where('CONVOCATORIA.CONV_ENTIDAD_ID', $entidadId))
            ->when($desde, fn ($q) => $q->where('CONVOCATORIA.CONV_FECHA_RESULTADO', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('CONVOCATORIA.CONV_FECHA_RESULTADO', '<=', $hasta))
            ->select('RUBRO.RUBR_NOMBRE')
            ->selectRaw('count(*) as cantidad')
            ->groupBy('RUBRO.RUBR_NOMBRE')
            ->orderByDesc('cantidad')
            ->limit(10)
            ->get()
            ->map(fn ($fila) => ['rubro' => $fila->RUBR_NOMBRE, 'cantidad' => (int) $fila->cantidad]);

        return ApiResponse::success([
            'convocatorias_vigentes' => $convocatoriasVigentes,
            'convocatorias_en_evaluacion' => $convocatoriasEnEvaluacion,
            'procesos_adjudicados' => $procesosAdjudicados,
            'procesos_desiertos' => $procesosDesiertos,
            'procesos_cancelados' => $procesosCancelados,
            'proveedores_participantes' => $proveedoresParticipantes,
            'monto_total_adjudicado' => (float) $montoTotalAdjudicado,
            'adjudicadas_por_mes' => $adjudicadasPorMes,
            'top_rubros' => $topRubros,
        ])->header('Cache-Control', 'private, max-age=60');
    }
}
