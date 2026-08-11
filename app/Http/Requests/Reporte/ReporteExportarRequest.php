<?php

namespace App\Http\Requests\Reporte;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReporteExportarRequest extends FormRequest
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
            'tipo_requerimiento' => ['nullable', Rule::in(['BIEN', 'SERVICIO'])],
            'rubro_id' => ['nullable', 'integer', Rule::exists('RUBRO', 'RUBR_ID')],
            'tipo_comprobante_id' => ['nullable', 'integer', Rule::exists('TIPO_COMPROBANTE', 'TICO_ID')],
        ];
    }
}
