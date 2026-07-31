<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\ReporteExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reporte\ReporteBuscarRequest;
use App\Http\Requests\Reporte\ReporteExportarRequest;
use App\Services\ReporteService;
use App\Support\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReporteController extends Controller
{
    private const COLUMNAS_ORDEN = [
        'ADJUDICACION' => ['fecha_resultado' => 'CONV_FECHA_RESULTADO', 'numero' => 'CONV_NUMERO'],
        'POR_PERIODO' => ['fecha_resultado' => 'CONV_FECHA_RESULTADO', 'numero' => 'CONV_NUMERO'],
        'POR_DEPENDENCIA' => ['fecha_resultado' => 'CONV_FECHA_RESULTADO', 'numero' => 'CONV_NUMERO'],
        'POR_PROVEEDOR' => ['fecha_envio' => 'OFER_FECHA_ENVIO', 'total_general' => 'OFER_TOTAL_GENERAL'],
    ];

    public function __construct(private ReporteService $reporteService) {}

    public function buscar(ReporteBuscarRequest $request): JsonResponse
    {
        $filtros = $this->filtrosConScope($request);

        $query = $this->reporteService->buscar($filtros);

        $columnaOrden = self::COLUMNAS_ORDEN[$filtros['tipo']][$request->input('ordenar_por')] ?? null;

        if ($columnaOrden) {
            $query->reorder($columnaOrden, $request->input('direccion', 'desc'));
        }

        $paginador = $query->paginate(
            $request->integer('por_pagina', 25),
            page: $request->integer('pagina', 1),
        );

        $paginador->getCollection()->transform(fn ($fila) => $this->reporteService->mapearFila($filtros['tipo'], $fila));

        return ApiResponse::paginado($paginador);
    }

    public function exportarXlsx(ReporteExportarRequest $request): BinaryFileResponse
    {
        $filtros = $this->filtrosConScope($request);

        return Excel::download(
            new ReporteExport($this->reporteService, $filtros),
            'reporte-'.strtolower($filtros['tipo']).'.xlsx',
            ExcelFormat::XLSX,
        );
    }

    public function exportarPdf(ReporteExportarRequest $request): Response
    {
        $filtros = $this->filtrosConScope($request);

        $filas = $this->reporteService->buscar($filtros)->limit(1000)->get()
            ->map(fn ($fila) => array_values($this->reporteService->mapearFila($filtros['tipo'], $fila)));

        $pdf = Pdf::loadView('pdf.reporte', [
            'tipo' => $filtros['tipo'],
            'cabeceras' => $this->reporteService->cabeceras($filtros['tipo']),
            'filas' => $filas,
        ]);

        return $pdf->stream('reporte-'.strtolower($filtros['tipo']).'.pdf');
    }

    private function filtrosConScope(ReporteBuscarRequest|ReporteExportarRequest $request): array
    {
        $filtros = $request->validated();

        if ($request->user()->hasRole('ENTIDAD')) {
            $filtros['entidad_id'] = $request->user()->entidad_id;
        }

        return $filtros;
    }
}
