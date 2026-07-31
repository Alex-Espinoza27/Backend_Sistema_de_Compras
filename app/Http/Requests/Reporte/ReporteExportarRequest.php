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
            'area_usuaria' => ['nullable', 'string', 'max:150'],
            'proveedor_id' => ['nullable', 'integer', Rule::exists('PROVEEDOR', 'PROV_ID')],
            'estado' => ['nullable', 'string', 'max:20'],
        ];
    }
}
