<?php

namespace App\Http\Requests\Convocatoria;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvocatoriaIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:150'],
            'estado' => ['nullable', Rule::in(['BORRADOR', 'PUBLICADA', 'EN_EVALUACION', 'ADJUDICADA', 'DESIERTA', 'CANCELADA'])],
            'entidad_id' => ['nullable', 'integer', Rule::exists('ENTIDAD', 'ENTI_ID')],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'ordenar_por' => ['nullable', Rule::in(['fecha_fin', 'fecha_inicio', 'numero', 'estado'])],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
