<?php

namespace App\Services;

use App\Models\Convocatoria;
use App\Models\Estado;
use App\Models\Oferta;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Consulta parametrizada de procesos (02B M10): por período, por dependencia,
 * por proveedor, de adjudicación. Única excepción POST /buscar (02A §3.3).
 * Reutilizado por el JSON paginado y por las exportaciones XLSX/PDF.
 */
class ReporteService
{
    public function buscar(array $filtros): Builder
    {
        return match ($filtros['tipo']) {
            'ADJUDICACION' => $this->queryAdjudicacion($filtros),
            'POR_PERIODO' => $this->queryPorPeriodo($filtros),
            'POR_DEPENDENCIA' => $this->queryPorDependencia($filtros),
            'POR_PROVEEDOR' => $this->queryPorProveedor($filtros),
            default => throw new HttpException(422, 'Tipo de reporte no reconocido.'),
        };
    }

    public function cabeceras(string $tipo): array
    {
        return match ($tipo) {
            'ADJUDICACION' => ['Número', 'Servicio', 'Entidad', 'Proveedor adjudicado', 'Monto', 'Fecha de resultado'],
            'POR_PERIODO' => ['Número', 'Servicio', 'Entidad', 'Estado', 'Fecha publicación', 'Fecha resultado'],
            'POR_DEPENDENCIA' => ['Número', 'Servicio', 'Entidad', 'Área usuaria', 'Estado', 'Fecha resultado'],
            'POR_PROVEEDOR' => ['Proveedor', 'Convocatoria', 'Entidad', 'Total ofertado', 'Estado', 'Fecha de envío'],
            default => [],
        };
    }

    public function mapearFila(string $tipo, Convocatoria|Oferta $fila): array
    {
        return match ($tipo) {
            'ADJUDICACION' => [
                'convocatoria_id' => $fila->CONV_ID,
                'numero' => $fila->CONV_NUMERO,
                'nombre_servicio' => $fila->CONV_NOMBRE_SERVICIO,
                'entidad' => $fila->entidad?->ENTI_SIGLA,
                'proveedor_adjudicado' => $fila->ofertaAdjudicada?->proveedor?->PROV_RAZON_SOCIAL,
                'monto' => $fila->ofertaAdjudicada?->OFER_TOTAL_GENERAL,
                'fecha_resultado' => $fila->CONV_FECHA_RESULTADO,
            ],
            'POR_PERIODO' => [
                'convocatoria_id' => $fila->CONV_ID,
                'numero' => $fila->CONV_NUMERO,
                'nombre_servicio' => $fila->CONV_NOMBRE_SERVICIO,
                'entidad' => $fila->entidad?->ENTI_SIGLA,
                'estado' => $fila->estado?->ESTA_CODIGO,
                'fecha_publicacion' => $fila->CONV_FECHA_PUBLICACION,
                'fecha_resultado' => $fila->CONV_FECHA_RESULTADO,
            ],
            'POR_DEPENDENCIA' => [
                'convocatoria_id' => $fila->CONV_ID,
                'numero' => $fila->CONV_NUMERO,
                'nombre_servicio' => $fila->CONV_NOMBRE_SERVICIO,
                'entidad' => $fila->entidad?->ENTI_SIGLA,
                'area_usuaria' => $fila->requerimiento?->REQU_AREA_USUARIA,
                'estado' => $fila->estado?->ESTA_CODIGO,
                'fecha_resultado' => $fila->CONV_FECHA_RESULTADO,
            ],
            'POR_PROVEEDOR' => [
                'oferta_id' => $fila->OFER_ID,
                'proveedor' => $fila->proveedor?->PROV_RAZON_SOCIAL,
                'convocatoria' => $fila->convocatoria?->CONV_NUMERO,
                'entidad' => $fila->convocatoria?->entidad?->ENTI_SIGLA,
                'total_general' => $fila->OFER_TOTAL_GENERAL,
                'estado' => $fila->estado?->ESTA_CODIGO,
                'fecha_envio' => $fila->OFER_FECHA_ENVIO,
            ],
            default => [],
        };
    }

    private function queryAdjudicacion(array $filtros): Builder
    {
        $estadoAdjudicadaId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', 'ADJUDICADA')->value('ESTA_ID');

        return Convocatoria::query()
            ->select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ENTIDAD_ID', 'CONV_OFERTA_ADJUDICADA_ID', 'CONV_FECHA_RESULTADO'])
            ->where('CONV_ESTADO_ID', $estadoAdjudicadaId)
            ->when($filtros['entidad_id'] ?? null, fn (Builder $q, $v) => $q->where('CONV_ENTIDAD_ID', $v))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $v) => $q->where('CONV_FECHA_RESULTADO', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $v) => $q->where('CONV_FECHA_RESULTADO', '<=', $v))
            ->with([
                'entidad' => fn ($q) => $q->select(['ENTI_ID', 'ENTI_SIGLA']),
                'ofertaAdjudicada' => fn ($q) => $q->select(['OFER_ID', 'OFER_PROVEEDOR_ID', 'OFER_TOTAL_GENERAL'])
                    ->with(['proveedor' => fn ($qq) => $qq->select(['PROV_ID', 'PROV_RAZON_SOCIAL'])]),
            ])
            ->orderByDesc('CONV_FECHA_RESULTADO');
    }

    private function queryPorPeriodo(array $filtros): Builder
    {
        return Convocatoria::query()
            ->select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ENTIDAD_ID', 'CONV_ESTADO_ID', 'CONV_FECHA_PUBLICACION', 'CONV_FECHA_RESULTADO'])
            ->when($filtros['entidad_id'] ?? null, fn (Builder $q, $v) => $q->where('CONV_ENTIDAD_ID', $v))
            ->when($filtros['estado'] ?? null, function (Builder $q, $v) {
                $estadoId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', $v)->value('ESTA_ID');
                $q->where('CONV_ESTADO_ID', $estadoId);
            })
            ->when($filtros['desde'] ?? null, fn (Builder $q, $v) => $q->where('CONV_FECHA_PUBLICACION', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $v) => $q->where('CONV_FECHA_PUBLICACION', '<=', $v))
            ->with([
                'entidad' => fn ($q) => $q->select(['ENTI_ID', 'ENTI_SIGLA']),
                'estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE']),
            ])
            ->orderByDesc('CONV_FECHA_PUBLICACION');
    }

    private function queryPorDependencia(array $filtros): Builder
    {
        return Convocatoria::query()
            ->select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ENTIDAD_ID', 'CONV_REQUERIMIENTO_ID', 'CONV_ESTADO_ID', 'CONV_FECHA_RESULTADO'])
            ->when(! empty($filtros['area_usuaria']), fn (Builder $q) => $q->whereHas(
                'requerimiento',
                fn ($sub) => $sub->where('REQU_AREA_USUARIA', 'ilike', '%'.$filtros['area_usuaria'].'%'),
            ))
            ->when($filtros['entidad_id'] ?? null, fn (Builder $q, $v) => $q->where('CONV_ENTIDAD_ID', $v))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $v) => $q->where('CONV_FECHA_RESULTADO', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $v) => $q->where('CONV_FECHA_RESULTADO', '<=', $v))
            ->with([
                'entidad' => fn ($q) => $q->select(['ENTI_ID', 'ENTI_SIGLA']),
                'requerimiento' => fn ($q) => $q->select(['REQU_ID', 'REQU_AREA_USUARIA']),
                'estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE']),
            ])
            ->orderByDesc('CONV_FECHA_RESULTADO');
    }

    private function queryPorProveedor(array $filtros): Builder
    {
        return Oferta::query()
            ->select(['OFER_ID', 'OFER_CONVOCATORIA_ID', 'OFER_PROVEEDOR_ID', 'OFER_TOTAL_GENERAL', 'OFER_ESTADO_ID', 'OFER_FECHA_ENVIO'])
            ->when($filtros['proveedor_id'] ?? null, fn (Builder $q, $v) => $q->where('OFER_PROVEEDOR_ID', $v))
            ->when($filtros['estado'] ?? null, fn (Builder $q, $v) => $q->whereHas('estado', fn ($sub) => $sub->where('ESTA_CODIGO', $v)))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $v) => $q->where('OFER_FECHA_ENVIO', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $v) => $q->where('OFER_FECHA_ENVIO', '<=', $v))
            ->when($filtros['entidad_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('convocatoria', fn ($sub) => $sub->where('CONV_ENTIDAD_ID', $v)))
            ->with([
                'proveedor' => fn ($q) => $q->select(['PROV_ID', 'PROV_NUMERO_DOCUMENTO', 'PROV_RAZON_SOCIAL']),
                'convocatoria' => fn ($q) => $q->select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ENTIDAD_ID'])
                    ->with(['entidad' => fn ($qq) => $qq->select(['ENTI_ID', 'ENTI_SIGLA'])]),
                'estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE']),
            ])
            ->orderByDesc('OFER_FECHA_ENVIO');
    }
}
