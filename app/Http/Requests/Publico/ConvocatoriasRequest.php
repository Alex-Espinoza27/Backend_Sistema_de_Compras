<?php

namespace App\Http\Requests\Publico;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvocatoriasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado_grupo' => ['required', Rule::in(['VIGENTES', 'EN_EVALUACION', 'CONCLUIDAS'])],
            'entidad_id' => ['nullable', 'integer', Rule::exists('ENTIDAD', 'ENTI_ID')],
            'buscar' => ['nullable', 'string', 'max:150'],
            'ordenar_por' => ['nullable', Rule::in(['fecha_fin', 'fecha_inicio', 'numero'])],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
