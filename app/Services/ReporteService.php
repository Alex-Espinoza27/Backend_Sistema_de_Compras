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
 *
 * 05C hardening: se amplían las columnas de los 4 tipos con más datos de
 * negocio (RUC de entidad/proveedor, tipo bien/servicio, rubros, vigencia,
 * condiciones ofertadas, resultado de evaluación) — se excluye a propósito
 * cualquier columna de auditoría (creado/actualizado por/en), esas viven en
 * el módulo de Auditoría, no en Reportes.
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
            'ADJUDICACION' => [
                'Número', 'Servicio', 'Entidad', 'RUC entidad', 'Tipo', 'Rubros',
                'Fecha de inicio', 'Fecha de fin', 'Proveedor adjudicado', 'RUC proveedor',
                'Tipo de comprobante', 'Monto', 'Validez de oferta (días)', 'Plazo de ejecución',
                'Forma de pago', 'Plazo de garantía', 'Fecha de resultado',
            ],
            'POR_PERIODO' => [
                'Número', 'Servicio', 'Entidad', 'RUC entidad', 'Tipo', 'Rubros', 'Estado',
                'N.º de ofertas', 'Fecha de inicio', 'Fecha publicación', 'Fecha de resultado', 'Motivo de cierre',
            ],
            'POR_DEPENDENCIA' => [
                'Número', 'Servicio', 'Entidad', 'RUC entidad', 'Tipo', 'Rubros', 'Área usuaria',
                'Estado', 'N.º de ofertas', 'Fecha de inicio', 'Fecha de fin', 'Fecha de resultado',
            ],
            'POR_PROVEEDOR' => [
                'Proveedor', 'RUC/DNI proveedor', 'Tipo de persona', 'Convocatoria', 'Entidad', 'RUC entidad',
                'Tipo de comprobante', 'Total ofertado', 'Validez de oferta (días)', 'Plazo de ejecución',
                'Forma de pago', 'Plazo de garantía', 'Evaluación técnica', 'Evaluación logística',
                'Contacto', 'Teléfono de contacto', 'Correo de contacto', 'Estado', 'Fecha de envío',
            ],
            default => [],
        };
    }

    /**
     * El identificador de fila (`CONV_ID`/`OFER_ID`) NO va aquí — esta lista
     * debe quedar 1:1 con `cabeceras()` para que XLSX/PDF (que usan
     * `array_values()` posicional, sin claves) no queden desalineados. El id
     * lo agrega `ReporteController::buscar()` por separado, solo para el
     * JSON paginado (lo usa el frontend como `item-value` de la tabla).
     */
    public function mapearFila(string $tipo, Convocatoria|Oferta $fila): array
    {
        return match ($tipo) {
            'ADJUDICACION' => [
                'numero' => $fila->CONV_NUMERO,
                'nombre_servicio' => $fila->CONV_NOMBRE_SERVICIO,
                'entidad' => $fila->entidad?->ENTI_SIGLA,
                'entidad_ruc' => $fila->entidad?->ENTI_NUMERO_DOCUMENTO,
                'tipo' => $fila->requerimiento?->REQU_TIPO,
                'rubros' => $fila->requerimiento?->rubros->pluck('RUBR_NOMBRE')->implode(', '),
                'fecha_inicio' => $fila->CONV_FECHA_INICIO,
                'fecha_fin' => $fila->CONV_FECHA_FIN,
                'proveedor_adjudicado' => $fila->ofertaAdjudicada?->proveedor?->PROV_RAZON_SOCIAL,
                'proveedor_ruc' => $fila->ofertaAdjudicada?->proveedor?->PROV_NUMERO_DOCUMENTO,
                'tipo_comprobante' => $fila->ofertaAdjudicada?->tipoComprobante?->TICO_CODIGO,
                'monto' => $fila->ofertaAdjudicada?->OFER_TOTAL_GENERAL,
                'validez_dias' => $fila->ofertaAdjudicada?->OFER_VALIDEZ_DIAS,
                'plazo_ejecucion' => $this->formatearPlazo($fila->ofertaAdjudicada?->OFER_PLAZO_EJECUCION_SEGUN_TDR, $fila->ofertaAdjudicada?->OFER_PLAZO_EJECUCION_DIAS),
                'forma_pago' => $this->formatearPlazo($fila->ofertaAdjudicada?->OFER_FORMA_PAGO_SEGUN_TDR, $fila->ofertaAdjudicada?->OFER_FORMA_PAGO_DIAS),
                'plazo_garantia' => $fila->ofertaAdjudicada?->OFER_PLAZO_GARANTIA,
                'fecha_resultado' => $fila->CONV_FECHA_RESULTADO,
            ],
            'POR_PERIODO' => [
                'numero' => $fila->CONV_NUMERO,
                'nombre_servicio' => $fila->CONV_NOMBRE_SERVICIO,
                'entidad' => $fila->entidad?->ENTI_SIGLA,
                'entidad_ruc' => $fila->entidad?->ENTI_NUMERO_DOCUMENTO,
                'tipo' => $fila->requerimiento?->REQU_TIPO,
                'rubros' => $fila->requerimiento?->rubros->pluck('RUBR_NOMBRE')->implode(', '),
                'estado' => $fila->estado?->ESTA_CODIGO,
                'total_ofertas' => $fila->total_ofertas,
                'fecha_inicio' => $fila->CONV_FECHA_INICIO,
                'fecha_publicacion' => $fila->CONV_FECHA_PUBLICACION,
                'fecha_resultado' => $fila->CONV_FECHA_RESULTADO,
                'motivo_cierre' => $fila->CONV_MOTIVO_CIERRE,
            ],
            'POR_DEPENDENCIA' => [
                'numero' => $fila->CONV_NUMERO,
                'nombre_servicio' => $fila->CONV_NOMBRE_SERVICIO,
                'entidad' => $fila->entidad?->ENTI_SIGLA,
                'entidad_ruc' => $fila->entidad?->ENTI_NUMERO_DOCUMENTO,
                'tipo' => $fila->requerimiento?->REQU_TIPO,
                'rubros' => $fila->requerimiento?->rubros->pluck('RUBR_NOMBRE')->implode(', '),
                'area_usuaria' => $fila->requerimiento?->area?->AREA_NOMBRE,
                'estado' => $fila->estado?->ESTA_CODIGO,
                'total_ofertas' => $fila->total_ofertas,
                'fecha_inicio' => $fila->CONV_FECHA_INICIO,
                'fecha_fin' => $fila->CONV_FECHA_FIN,
                'fecha_resultado' => $fila->CONV_FECHA_RESULTADO,
            ],
            'POR_PROVEEDOR' => [
                'proveedor' => $fila->proveedor?->PROV_RAZON_SOCIAL,
                'proveedor_ruc' => $fila->proveedor?->PROV_NUMERO_DOCUMENTO,
                'tipo_persona' => $fila->proveedor?->PROV_TIPO_PERSONA,
                'convocatoria' => $fila->convocatoria?->CONV_NUMERO,
                'entidad' => $fila->convocatoria?->entidad?->ENTI_SIGLA,
                'entidad_ruc' => $fila->convocatoria?->entidad?->ENTI_NUMERO_DOCUMENTO,
                'tipo_comprobante' => $fila->tipoComprobante?->TICO_CODIGO,
                'total_general' => $fila->OFER_TOTAL_GENERAL,
                'validez_dias' => $fila->OFER_VALIDEZ_DIAS,
                'plazo_ejecucion' => $this->formatearPlazo($fila->OFER_PLAZO_EJECUCION_SEGUN_TDR, $fila->OFER_PLAZO_EJECUCION_DIAS),
                'forma_pago' => $this->formatearPlazo($fila->OFER_FORMA_PAGO_SEGUN_TDR, $fila->OFER_FORMA_PAGO_DIAS),
                'plazo_garantia' => $fila->OFER_PLAZO_GARANTIA,
                'eval_tecnica' => $fila->OFER_EVAL_TECNICA,
                'eval_logistica' => $fila->OFER_EVAL_LOGISTICA,
                'contacto_nombre' => $fila->OFER_CONTACTO_NOMBRE,
                'contacto_telefono' => $fila->OFER_CONTACTO_TELEFONO,
                'contacto_correo' => $fila->OFER_CONTACTO_CORREO,
                'estado' => $fila->estado?->ESTA_CODIGO,
                'fecha_envio' => $fila->OFER_FECHA_ENVIO,
            ],
            default => [],
        };
    }

    private function formatearPlazo(?bool $segunTdr, ?int $dias): ?string
    {
        if ($segunTdr === null) {
            return null;
        }

        return $segunTdr ? 'Según TDR' : "{$dias} días";
    }

    private function queryAdjudicacion(array $filtros): Builder
    {
        $estadoAdjudicadaId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', 'ADJUDICADA')->value('ESTA_ID');

        return Convocatoria::query()
            ->select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ENTIDAD_ID', 'CONV_REQUERIMIENTO_ID', 'CONV_OFERTA_ADJUDICADA_ID', 'CONV_FECHA_INICIO', 'CONV_FECHA_FIN', 'CONV_FECHA_RESULTADO'])
            ->where('CONV_ESTADO_ID', $estadoAdjudicadaId)
            ->when($filtros['entidad_id'] ?? null, fn (Builder $q, $v) => $q->where('CONV_ENTIDAD_ID', $v))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $v) => $q->where('CONV_FECHA_RESULTADO', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $v) => $q->where('CONV_FECHA_RESULTADO', '<=', $v))
            ->when($filtros['tipo_requerimiento'] ?? null, fn (Builder $q, $v) => $q->whereHas('requerimiento', fn ($sub) => $sub->where('REQU_TIPO', $v)))
            ->when($filtros['rubro_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('requerimiento.rubros', fn ($sub) => $sub->where('RUBR_ID', $v)))
            ->with([
                'entidad' => fn ($q) => $q->select(['ENTI_ID', 'ENTI_SIGLA', 'ENTI_NUMERO_DOCUMENTO']),
                'requerimiento' => fn ($q) => $q->select(['REQU_ID', 'REQU_TIPO'])->with('rubros:RUBR_ID,RUBR_NOMBRE'),
                'ofertaAdjudicada' => fn ($q) => $q->select([
                    'OFER_ID', 'OFER_PROVEEDOR_ID', 'OFER_TIPO_COMPROBANTE_ID', 'OFER_TOTAL_GENERAL',
                    'OFER_VALIDEZ_DIAS', 'OFER_PLAZO_EJECUCION_SEGUN_TDR', 'OFER_PLAZO_EJECUCION_DIAS',
                    'OFER_FORMA_PAGO_SEGUN_TDR', 'OFER_FORMA_PAGO_DIAS', 'OFER_PLAZO_GARANTIA',
                ])
                    ->with([
                        'proveedor' => fn ($qq) => $qq->select(['PROV_ID', 'PROV_RAZON_SOCIAL', 'PROV_NUMERO_DOCUMENTO']),
                        'tipoComprobante' => fn ($qq) => $qq->select(['TICO_ID', 'TICO_CODIGO']),
                    ]),
            ])
            ->orderByDesc('CONV_FECHA_RESULTADO');
    }

    private function queryPorPeriodo(array $filtros): Builder
    {
        return Convocatoria::query()
            ->select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ENTIDAD_ID', 'CONV_REQUERIMIENTO_ID', 'CONV_ESTADO_ID', 'CONV_MOTIVO_CIERRE', 'CONV_FECHA_INICIO', 'CONV_FECHA_PUBLICACION', 'CONV_FECHA_RESULTADO'])
            ->withCount('ofertas as total_ofertas')
            ->when($filtros['entidad_id'] ?? null, fn (Builder $q, $v) => $q->where('CONV_ENTIDAD_ID', $v))
            ->when($filtros['estado'] ?? null, function (Builder $q, $v) {
                $estadoId = Estado::where('ESTA_CONTEXTO', 'CONVOCATORIA')->where('ESTA_CODIGO', $v)->value('ESTA_ID');
                $q->where('CONV_ESTADO_ID', $estadoId);
            })
            ->when($filtros['desde'] ?? null, fn (Builder $q, $v) => $q->where('CONV_FECHA_PUBLICACION', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $v) => $q->where('CONV_FECHA_PUBLICACION', '<=', $v))
            ->when($filtros['tipo_requerimiento'] ?? null, fn (Builder $q, $v) => $q->whereHas('requerimiento', fn ($sub) => $sub->where('REQU_TIPO', $v)))
            ->when($filtros['rubro_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('requerimiento.rubros', fn ($sub) => $sub->where('RUBR_ID', $v)))
            ->with([
                'entidad' => fn ($q) => $q->select(['ENTI_ID', 'ENTI_SIGLA', 'ENTI_NUMERO_DOCUMENTO']),
                'requerimiento' => fn ($q) => $q->select(['REQU_ID', 'REQU_TIPO'])->with('rubros:RUBR_ID,RUBR_NOMBRE'),
                'estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE']),
            ])
            ->orderByDesc('CONV_FECHA_PUBLICACION');
    }

    private function queryPorDependencia(array $filtros): Builder
    {
        return Convocatoria::query()
            ->select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ENTIDAD_ID', 'CONV_REQUERIMIENTO_ID', 'CONV_ESTADO_ID', 'CONV_FECHA_INICIO', 'CONV_FECHA_FIN', 'CONV_FECHA_RESULTADO'])
            ->withCount('ofertas as total_ofertas')
            ->when(! empty($filtros['area_id']), fn (Builder $q) => $q->whereHas(
                'requerimiento',
                fn ($sub) => $sub->where('REQU_AREA_ID', $filtros['area_id']),
            ))
            ->when($filtros['entidad_id'] ?? null, fn (Builder $q, $v) => $q->where('CONV_ENTIDAD_ID', $v))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $v) => $q->where('CONV_FECHA_RESULTADO', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $v) => $q->where('CONV_FECHA_RESULTADO', '<=', $v))
            ->when($filtros['tipo_requerimiento'] ?? null, fn (Builder $q, $v) => $q->whereHas('requerimiento', fn ($sub) => $sub->where('REQU_TIPO', $v)))
            ->when($filtros['rubro_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('requerimiento.rubros', fn ($sub) => $sub->where('RUBR_ID', $v)))
            ->with([
                'entidad' => fn ($q) => $q->select(['ENTI_ID', 'ENTI_SIGLA', 'ENTI_NUMERO_DOCUMENTO']),
                'requerimiento' => fn ($q) => $q->select(['REQU_ID', 'REQU_TIPO', 'REQU_AREA_ID'])
                    ->with([
                        'area' => fn ($qq) => $qq->select(['AREA_ID', 'AREA_NOMBRE']),
                        'rubros:RUBR_ID,RUBR_NOMBRE',
                    ]),
                'estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE']),
            ])
            ->orderByDesc('CONV_FECHA_RESULTADO');
    }

    private function queryPorProveedor(array $filtros): Builder
    {
        return Oferta::query()
            ->select([
                'OFER_ID', 'OFER_CONVOCATORIA_ID', 'OFER_PROVEEDOR_ID', 'OFER_TIPO_COMPROBANTE_ID', 'OFER_TOTAL_GENERAL',
                'OFER_VALIDEZ_DIAS', 'OFER_PLAZO_EJECUCION_SEGUN_TDR', 'OFER_PLAZO_EJECUCION_DIAS',
                'OFER_FORMA_PAGO_SEGUN_TDR', 'OFER_FORMA_PAGO_DIAS', 'OFER_PLAZO_GARANTIA',
                'OFER_EVAL_TECNICA', 'OFER_EVAL_LOGISTICA', 'OFER_CONTACTO_NOMBRE', 'OFER_CONTACTO_TELEFONO', 'OFER_CONTACTO_CORREO',
                'OFER_ESTADO_ID', 'OFER_FECHA_ENVIO',
            ])
            ->when($filtros['proveedor_id'] ?? null, fn (Builder $q, $v) => $q->where('OFER_PROVEEDOR_ID', $v))
            ->when($filtros['estado'] ?? null, fn (Builder $q, $v) => $q->whereHas('estado', fn ($sub) => $sub->where('ESTA_CODIGO', $v)))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $v) => $q->where('OFER_FECHA_ENVIO', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $v) => $q->where('OFER_FECHA_ENVIO', '<=', $v))
            ->when($filtros['entidad_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('convocatoria', fn ($sub) => $sub->where('CONV_ENTIDAD_ID', $v)))
            ->when($filtros['tipo_comprobante_id'] ?? null, fn (Builder $q, $v) => $q->where('OFER_TIPO_COMPROBANTE_ID', $v))
            ->with([
                'proveedor' => fn ($q) => $q->select(['PROV_ID', 'PROV_NUMERO_DOCUMENTO', 'PROV_RAZON_SOCIAL', 'PROV_TIPO_PERSONA']),
                'convocatoria' => fn ($q) => $q->select(['CONV_ID', 'CONV_NUMERO', 'CONV_NOMBRE_SERVICIO', 'CONV_ENTIDAD_ID'])
                    ->with(['entidad' => fn ($qq) => $qq->select(['ENTI_ID', 'ENTI_SIGLA', 'ENTI_NUMERO_DOCUMENTO'])]),
                'tipoComprobante' => fn ($q) => $q->select(['TICO_ID', 'TICO_CODIGO']),
                'estado' => fn ($q) => $q->select(['ESTA_ID', 'ESTA_CODIGO', 'ESTA_NOMBRE']),
            ])
            ->orderByDesc('OFER_FECHA_ENVIO');
    }
}
