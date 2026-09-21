<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\AuditoriaExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auditoria\AuditoriaIndexRequest;
use App\Models\Auditoria;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuditoriaController extends Controller
{
    public function index(AuditoriaIndexRequest $request): JsonResponse
    {
        $paginador = $this->consultaFiltrada($request)
            ->select(['AUDI_ID', 'AUDI_USER_ID', 'AUDI_MODULO', 'AUDI_ACCION', 'AUDI_TABLA', 'AUDI_REGISTRO_ID', 'AUDI_IP', 'AUDI_FECHA'])
            ->with(['user' => fn ($q) => $q->select(['id', 'name', 'email'])])
            ->paginate(
                $request->integer('por_pagina', 25),
                page: $request->integer('pagina', 1),
            );

        $paginador->getCollection()->transform(fn (Auditoria $a) => [
            'auditoria_id' => $a->AUDI_ID,
            'usuario' => $a->user ? ['id' => $a->user->id, 'nombre' => $a->user->name] : null,
            'modulo' => $a->AUDI_MODULO,
            'accion' => $a->AUDI_ACCION,
            'tabla' => $a->AUDI_TABLA,
            'registro_id' => $a->AUDI_REGISTRO_ID,
            'ip' => $a->AUDI_IP,
            'fecha' => $a->AUDI_FECHA,
        ]);

        return ApiResponse::paginado($paginador);
    }

    public function show(int $id): JsonResponse
    {
        $auditoria = Auditoria::query()
            ->select(['AUDI_ID', 'AUDI_USER_ID', 'AUDI_MODULO', 'AUDI_ACCION', 'AUDI_TABLA', 'AUDI_REGISTRO_ID', 'AUDI_VALOR_ANTERIOR', 'AUDI_VALOR_NUEVO', 'AUDI_IP', 'AUDI_USER_AGENT', 'AUDI_ENDPOINT', 'AUDI_FECHA'])
            ->with(['user' => fn ($q) => $q->select(['id', 'name', 'email'])])
            ->findOrFail($id);

        return ApiResponse::success([
            'auditoria_id' => $auditoria->AUDI_ID,
            'usuario' => $auditoria->user ? ['id' => $auditoria->user->id, 'nombre' => $auditoria->user->name, 'correo' => $auditoria->user->email] : null,
            'modulo' => $auditoria->AUDI_MODULO,
            'accion' => $auditoria->AUDI_ACCION,
            'tabla' => $auditoria->AUDI_TABLA,
            'registro_id' => $auditoria->AUDI_REGISTRO_ID,
            'valor_anterior' => $auditoria->AUDI_VALOR_ANTERIOR,
            'valor_nuevo' => $auditoria->AUDI_VALOR_NUEVO,
            'ip' => $auditoria->AUDI_IP,
            'user_agent' => $auditoria->AUDI_USER_AGENT,
            'endpoint' => $auditoria->AUDI_ENDPOINT,
            'fecha' => $auditoria->AUDI_FECHA,
        ]);
    }

    public function exportarXlsx(AuditoriaIndexRequest $request): BinaryFileResponse
    {
        $query = $this->consultaFiltrada($request)
            ->select(['AUDI_ID', 'AUDI_USER_ID', 'AUDI_MODULO', 'AUDI_ACCION', 'AUDI_TABLA', 'AUDI_REGISTRO_ID', 'AUDI_IP', 'AUDI_FECHA'])
            ->with(['user' => fn ($q) => $q->select(['id', 'name'])]);

        return Excel::download(new AuditoriaExport($query), 'auditoria.xlsx', ExcelFormat::XLSX);
    }

    private function consultaFiltrada(AuditoriaIndexRequest $request): Builder
    {
        return Auditoria::query()
            ->when($request->filled('usuario_id'), fn ($q) => $q->where('AUDI_USER_ID', $request->integer('usuario_id')))
            ->when($request->filled('modulo'), fn ($q) => $q->where('AUDI_MODULO', $request->input('modulo')))
            ->when($request->filled('accion'), fn ($q) => $q->where('AUDI_ACCION', $request->input('accion')))
            ->when($request->filled('tabla'), fn ($q) => $q->where('AUDI_TABLA', $request->input('tabla')))
            // `whereDate` compara solo la parte de fecha: "hasta" así incluye
            // el día completo, no solo la medianoche (mismo bug corregido en
            // ConvocatoriaController — relevante ahora que el filtro por
            // defecto del frontend es "hoy", 05C hardening).
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('AUDI_FECHA', '>=', $request->input('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('AUDI_FECHA', '<=', $request->input('hasta')))
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $buscar = $request->string('buscar');
                $q->where(fn ($sub) => $sub->where('AUDI_ENDPOINT', 'ilike', "%{$buscar}%")->orWhere('AUDI_MODULO', 'ilike', "%{$buscar}%"));
            })
            ->orderBy('AUDI_FECHA', $request->input('direccion', 'desc'));
    }
}
