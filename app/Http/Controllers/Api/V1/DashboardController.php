<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardRequest;
use App\Models\Convocatoria;
use App\Models\Estado;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(DashboardRequest $request): JsonResponse
    {
        $user = $request->user();
        $desde = $request->filled('desde') ? Carbon::parse($request->input('desde'))->startOfDay() : null;
        $hasta = $request->filled('hasta') ? Carbon::parse($request->input('hasta'))->endOfDay() : null;

        if ($user->hasRole('PROVEEDOR')) {
            return ApiResponse::success($this->paraProveedor($user, $desde, $hasta))
                ->header('Cache-Control', 'private, max-age=60');
        }

        $esAdminOAuditor = $user->hasAnyRole(['ADMINISTRADOR', 'AUDITOR']);
        $entidadId = $esAdminOAuditor ? $request->integer('entidad_id') ?: null : $user->entidad_id;

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

        // Un requerimiento puede tener más de un rubro (05C hardening): el
        // join pasa por la tabla puente, así que una convocatoria con varios
        // rubros aporta un conteo a cada uno (antes era 1:1 vía REQU_RUBRO_ID).
        $topRubros = DB::table('CONVOCATORIA')
            ->join('REQUERIMIENTO', 'REQUERIMIENTO.REQU_ID', '=', 'CONVOCATORIA.CONV_REQUERIMIENTO_ID')
            ->join('REQUERIMIENTO_RUBRO', 'REQUERIMIENTO_RUBRO.RERU_REQUERIMIENTO_ID', '=', 'REQUERIMIENTO.REQU_ID')
            ->join('RUBRO', 'RUBRO.RUBR_ID', '=', 'REQUERIMIENTO_RUBRO.RERU_RUBRO_ID')
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

        // Embudo de estados (dona, 05C hardening): estado actual de todas las
        // convocatorias iniciadas en el periodo (CONV_FECHA_INICIO), no solo
        // las 3 resueltas — a diferencia de $contarResueltas, incluye también
        // BORRADOR/PUBLICADA/EN_EVALUACION en una sola consulta agrupada.
        $conteosPorEstado = Convocatoria::query()
            ->when($entidadId, fn ($q) => $q->where('CONV_ENTIDAD_ID', $entidadId))
            ->when($desde, fn ($q) => $q->where('CONV_FECHA_INICIO', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('CONV_FECHA_INICIO', '<=', $hasta))
            ->select('CONV_ESTADO_ID')
            ->selectRaw('count(*) as cantidad')
            ->groupBy('CONV_ESTADO_ID')
            ->pluck('cantidad', 'CONV_ESTADO_ID');

        $convocatoriasPorEstado = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')
            ->orderBy('ESTA_ORDEN')
            ->get()
            ->map(fn (Estado $estado) => [
                'codigo' => $estado->ESTA_CODIGO,
                'nombre' => $estado->ESTA_NOMBRE,
                'color' => $estado->ESTA_COLOR,
                'cantidad' => (int) ($conteosPorEstado[$estado->ESTA_ID] ?? 0),
            ])
            ->values();

        // Línea mensual de 3 etapas (05C hardening, pedido por el usuario):
        // cuántas convocatorias entraron a cada etapa por mes, cada una con
        // su propia fecha de referencia (no son mutuamente excluyentes: una
        // misma convocatoria puede aportar a las 3 series en meses distintos).
        $contarPorMes = function (string $columnaFecha) use ($entidadId, $desde, $hasta): Collection {
            return Convocatoria::query()
                ->when($entidadId, fn ($q) => $q->where('CONV_ENTIDAD_ID', $entidadId))
                ->whereNotNull($columnaFecha)
                ->when($desde, fn ($q) => $q->where($columnaFecha, '>=', $desde))
                ->when($hasta, fn ($q) => $q->where($columnaFecha, '<=', $hasta))
                ->selectRaw("to_char(\"{$columnaFecha}\", 'YYYY-MM') as mes")
                ->selectRaw('count(*) as cantidad')
                ->groupByRaw("to_char(\"{$columnaFecha}\", 'YYYY-MM')")
                ->pluck('cantidad', 'mes');
        };

        $presentadasPorMes = $contarPorMes('CONV_FECHA_PUBLICACION');
        $enEvaluacionPorMes = $contarPorMes('CONV_FECHA_APERTURA');
        $concluidasPorMes = $contarPorMes('CONV_FECHA_RESULTADO');

        $mesesConvocatorias = collect([$presentadasPorMes, $enEvaluacionPorMes, $concluidasPorMes])
            ->flatMap(fn (Collection $serie) => $serie->keys())
            ->unique()
            ->sort()
            ->values();

        $convocatoriasPorMes = $mesesConvocatorias->map(fn (string $mes) => [
            'mes' => $mes,
            'presentadas' => (int) ($presentadasPorMes[$mes] ?? 0),
            'en_evaluacion' => (int) ($enEvaluacionPorMes[$mes] ?? 0),
            'concluidas' => (int) ($concluidasPorMes[$mes] ?? 0),
        ]);

        // Comparativa entre entidades (05C hardening): solo tiene sentido en
        // la vista general de admin/auditor (sin una entidad ya filtrada).
        $comparativaEntidades = [];
        if ($esAdminOAuditor && ! $entidadId) {
            $comparativaEntidades = DB::table('CONVOCATORIA')
                ->join('ENTIDAD', 'ENTIDAD.ENTI_ID', '=', 'CONVOCATORIA.CONV_ENTIDAD_ID')
                ->whereIn('CONVOCATORIA.CONV_ESTADO_ID', [$estados['ADJUDICADA'], $estados['DESIERTA'], $estados['CANCELADA']])
                ->when($desde, fn ($q) => $q->where('CONVOCATORIA.CONV_FECHA_RESULTADO', '>=', $desde))
                ->when($hasta, fn ($q) => $q->where('CONVOCATORIA.CONV_FECHA_RESULTADO', '<=', $hasta))
                ->select('ENTIDAD.ENTI_ID', 'ENTIDAD.ENTI_SIGLA')
                ->selectRaw('count(*) filter (where "CONVOCATORIA"."CONV_ESTADO_ID" = ?) as adjudicadas', [$estados['ADJUDICADA']])
                ->selectRaw('count(*) filter (where "CONVOCATORIA"."CONV_ESTADO_ID" = ?) as desiertas', [$estados['DESIERTA']])
                ->selectRaw('count(*) filter (where "CONVOCATORIA"."CONV_ESTADO_ID" = ?) as canceladas', [$estados['CANCELADA']])
                ->groupBy('ENTIDAD.ENTI_ID', 'ENTIDAD.ENTI_SIGLA')
                // Postgres no permite reusar el alias de una columna calculada
                // dentro de una expresión en ORDER BY; como el WHERE ya
                // restringe a ADJUDICADA/DESIERTA/CANCELADA, count(*) del
                // grupo equivale exactamente a la suma de los 3 filtrados.
                ->orderByRaw('count(*) desc')
                ->limit(10)
                ->get()
                ->map(fn ($fila) => [
                    'entidad_id' => $fila->ENTI_ID,
                    'sigla' => $fila->ENTI_SIGLA,
                    'adjudicadas' => (int) $fila->adjudicadas,
                    'desiertas' => (int) $fila->desiertas,
                    'canceladas' => (int) $fila->canceladas,
                ]);
        }

        return ApiResponse::success([
            'contexto' => 'ENTIDAD_ADMIN',
            'convocatorias_vigentes' => $convocatoriasVigentes,
            'convocatorias_en_evaluacion' => $convocatoriasEnEvaluacion,
            'procesos_adjudicados' => $procesosAdjudicados,
            'procesos_desiertos' => $procesosDesiertos,
            'procesos_cancelados' => $procesosCancelados,
            'proveedores_participantes' => $proveedoresParticipantes,
            'monto_total_adjudicado' => (float) $montoTotalAdjudicado,
            'adjudicadas_por_mes' => $adjudicadasPorMes,
            'top_rubros' => $topRubros,
            'convocatorias_por_estado' => $convocatoriasPorEstado,
            'convocatorias_por_mes' => $convocatoriasPorMes,
            'comparativa_entidades' => $comparativaEntidades,
        ])->header('Cache-Control', 'private, max-age=60');
    }

    /**
     * Vista propia del proveedor (05C hardening, pedido explícitamente por el
     * usuario): antes PROVEEDOR no tenía acceso al Dashboard. Métricas de sus
     * propias ofertas, nunca de otros proveedores ni de convocatorias ajenas.
     */
    private function paraProveedor(User $user, ?Carbon $desde, ?Carbon $hasta): array
    {
        $proveedorId = $user->proveedor->PROV_ID;
        $estados = Estado::where('ESTA_CONTEXTO', 'OFERTA')->pluck('ESTA_ID', 'ESTA_CODIGO');

        $misOfertasEnviadas = DB::table('OFERTA')
            ->where('OFER_PROVEEDOR_ID', $proveedorId)
            ->whereNull('OFER_ELIMINADO_EN')
            ->when($desde, fn ($q) => $q->where('OFER_FECHA_ENVIO', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('OFER_FECHA_ENVIO', '<=', $hasta))
            ->count();

        $misEnEvaluacion = DB::table('OFERTA')
            ->where('OFER_PROVEEDOR_ID', $proveedorId)
            ->whereNull('OFER_ELIMINADO_EN')
            ->where('OFER_ESTADO_ID', $estados['EN_EVALUACION'])
            ->count();

        $contarPorResultado = function (string $codigo) use ($proveedorId, $desde, $hasta, $estados) {
            return DB::table('OFERTA')
                ->join('CONVOCATORIA', 'CONVOCATORIA.CONV_ID', '=', 'OFERTA.OFER_CONVOCATORIA_ID')
                ->where('OFERTA.OFER_PROVEEDOR_ID', $proveedorId)
                ->where('OFERTA.OFER_ESTADO_ID', $estados[$codigo])
                ->when($desde, fn ($q) => $q->where('CONVOCATORIA.CONV_FECHA_RESULTADO', '>=', $desde))
                ->when($hasta, fn ($q) => $q->where('CONVOCATORIA.CONV_FECHA_RESULTADO', '<=', $hasta))
                ->count();
        };

        $misAdjudicadas = $contarPorResultado('ADJUDICADA');
        $misNoAdjudicadas = $contarPorResultado('NO_ADJUDICADA');

        $montoTotalGanado = DB::table('OFERTA')
            ->join('CONVOCATORIA', 'CONVOCATORIA.CONV_ID', '=', 'OFERTA.OFER_CONVOCATORIA_ID')
            ->where('OFERTA.OFER_PROVEEDOR_ID', $proveedorId)
            ->where('OFERTA.OFER_ESTADO_ID', $estados['ADJUDICADA'])
            ->when($desde, fn ($q) => $q->where('CONVOCATORIA.CONV_FECHA_RESULTADO', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('CONVOCATORIA.CONV_FECHA_RESULTADO', '<=', $hasta))
            ->sum('OFERTA.OFER_TOTAL_GENERAL');

        $enviadasPorMes = DB::table('OFERTA')
            ->where('OFER_PROVEEDOR_ID', $proveedorId)
            ->whereNull('OFER_ELIMINADO_EN')
            ->selectRaw('to_char("OFER_FECHA_ENVIO", \'YYYY-MM\') as mes')
            ->selectRaw('count(*) as cantidad')
            ->groupByRaw('to_char("OFER_FECHA_ENVIO", \'YYYY-MM\')')
            ->pluck('cantidad', 'mes');

        $adjudicadasPorMes = DB::table('OFERTA')
            ->join('CONVOCATORIA', 'CONVOCATORIA.CONV_ID', '=', 'OFERTA.OFER_CONVOCATORIA_ID')
            ->where('OFERTA.OFER_PROVEEDOR_ID', $proveedorId)
            ->where('OFERTA.OFER_ESTADO_ID', $estados['ADJUDICADA'])
            ->whereNotNull('CONVOCATORIA.CONV_FECHA_RESULTADO')
            ->selectRaw('to_char("CONVOCATORIA"."CONV_FECHA_RESULTADO", \'YYYY-MM\') as mes')
            ->selectRaw('count(*) as cantidad')
            ->groupByRaw('to_char("CONVOCATORIA"."CONV_FECHA_RESULTADO", \'YYYY-MM\')')
            ->pluck('cantidad', 'mes');

        $mesesProveedor = collect([$enviadasPorMes, $adjudicadasPorMes])
            ->flatMap(fn (Collection $serie) => $serie->keys())
            ->unique()
            ->sort()
            ->values();

        $ofertasPorMes = $mesesProveedor->map(fn (string $mes) => [
            'mes' => $mes,
            'enviadas' => (int) ($enviadasPorMes[$mes] ?? 0),
            'adjudicadas' => (int) ($adjudicadasPorMes[$mes] ?? 0),
        ]);

        $topRubros = DB::table('OFERTA')
            ->join('CONVOCATORIA', 'CONVOCATORIA.CONV_ID', '=', 'OFERTA.OFER_CONVOCATORIA_ID')
            ->join('REQUERIMIENTO', 'REQUERIMIENTO.REQU_ID', '=', 'CONVOCATORIA.CONV_REQUERIMIENTO_ID')
            ->join('REQUERIMIENTO_RUBRO', 'REQUERIMIENTO_RUBRO.RERU_REQUERIMIENTO_ID', '=', 'REQUERIMIENTO.REQU_ID')
            ->join('RUBRO', 'RUBRO.RUBR_ID', '=', 'REQUERIMIENTO_RUBRO.RERU_RUBRO_ID')
            ->where('OFERTA.OFER_PROVEEDOR_ID', $proveedorId)
            ->whereNull('OFERTA.OFER_ELIMINADO_EN')
            ->select('RUBRO.RUBR_NOMBRE')
            ->selectRaw('count(*) as cantidad')
            ->groupBy('RUBRO.RUBR_NOMBRE')
            ->orderByDesc('cantidad')
            ->limit(10)
            ->get()
            ->map(fn ($fila) => ['rubro' => $fila->RUBR_NOMBRE, 'cantidad' => (int) $fila->cantidad]);

        return [
            'contexto' => 'PROVEEDOR',
            'mis_ofertas_enviadas' => $misOfertasEnviadas,
            'mis_ofertas_en_evaluacion' => $misEnEvaluacion,
            'mis_ofertas_adjudicadas' => $misAdjudicadas,
            'mis_ofertas_no_adjudicadas' => $misNoAdjudicadas,
            'monto_total_ganado' => (float) $montoTotalGanado,
            'ofertas_por_mes' => $ofertasPorMes,
            'top_rubros_postulados' => $topRubros,
        ];
    }
}
