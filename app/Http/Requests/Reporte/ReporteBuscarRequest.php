<?php

namespace App\Http\Requests\Reporte;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReporteBuscarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(['ADJUDICACION', 'POR_PERIODO', 'POR_DEPENDENCIA', 'POR_PROVEEDOR'])],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'entidad_id' => ['nullable', 'integer', Rule::exists('ENTIDAD', 'ENTI_ID')],
            'area_id' => ['nullable', 'integer', Rule::exists('AREA', 'AREA_ID')],
            'proveedor_id' => ['nullable', 'integer', Rule::exists('PROVEEDOR', 'PROV_ID')],
            'estado' => ['nullable', 'string', 'max:20'],
            // 05C hardening: más filtros para ADJUDICACION/POR_PERIODO/POR_DEPENDENCIA
            // (parten de Convocatoria->requerimiento) y tipo_comprobante_id solo
            // para POR_PROVEEDOR (parte de Oferta). `tipo_requerimiento` (no
            // `tipo`, ya ocupado por el tipo de reporte) filtra BIEN/SERVICIO.
            'tipo_requerimiento' => ['nullable', Rule::in(['BIEN', 'SERVICIO'])],
            'rubro_id' => ['nullable', 'integer', Rule::exists('RUBRO', 'RUBR_ID')],
            'tipo_comprobante_id' => ['nullable', 'integer', Rule::exists('TIPO_COMPROBANTE', 'TICO_ID')],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
            'ordenar_por' => ['nullable', Rule::in(['fecha_resultado', 'numero', 'total_general', 'fecha_envio'])],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
