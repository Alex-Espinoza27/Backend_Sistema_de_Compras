<?php

namespace App\Http\Requests\Area;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AreaIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:150'],
            'activo' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
            // Solo tiene efecto para ADMINISTRADOR/AUDITOR (05C hardening,
            // combo del filtro de Reportes) — para ENTIDAD el scope real lo
            // aplica el global scope `DeSuEntidad` del modelo, no este campo.
            'entidad_id' => ['nullable', 'integer', Rule::exists('ENTIDAD', 'ENTI_ID')],
            'ordenar_por' => ['nullable', Rule::in(['nombre'])],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
