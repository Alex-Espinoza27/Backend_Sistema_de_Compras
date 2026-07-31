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
            'area_usuaria' => ['nullable', 'string', 'max:150'],
            'proveedor_id' => ['nullable', 'integer', Rule::exists('PROVEEDOR', 'PROV_ID')],
            'estado' => ['nullable', 'string', 'max:20'],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
            'ordenar_por' => ['nullable', Rule::in(['fecha_resultado', 'numero', 'total_general', 'fecha_envio'])],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
