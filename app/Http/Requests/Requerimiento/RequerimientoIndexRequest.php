<?php

namespace App\Http\Requests\Requerimiento;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequerimientoIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:150'],
            'tipo' => ['nullable', Rule::in(['BIEN', 'SERVICIO'])],
            'rubro_id' => ['nullable', 'integer', Rule::exists('RUBRO', 'RUBR_ID')],
            'convocado' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
            'ordenar_por' => ['nullable', Rule::in(['numero', 'objeto', 'tipo'])],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
