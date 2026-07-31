<?php

namespace App\Http\Requests\Entidad;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntidadIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:150'],
            'estado' => ['nullable', Rule::in(['SOLICITADA', 'APROBADA', 'OBSERVADA', 'RECHAZADA', 'SUSPENDIDA'])],
            'ordenar_por' => ['nullable', Rule::in(['razon_social', 'fecha_solicitud'])],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
