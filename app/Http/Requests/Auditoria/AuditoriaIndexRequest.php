<?php

namespace App\Http\Requests\Auditoria;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditoriaIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usuario_id' => ['nullable', 'integer'],
            'modulo' => ['nullable', 'string', 'max:30'],
            'accion' => ['nullable', Rule::in(['LOGIN', 'CREAR', 'ACTUALIZAR', 'ELIMINAR', 'PUBLICAR', 'EVALUAR', 'ADJUDICAR', 'DESIERTA', 'CANCELAR'])],
            'tabla' => ['nullable', 'string', 'max:40'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'buscar' => ['nullable', 'string', 'max:150'],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
