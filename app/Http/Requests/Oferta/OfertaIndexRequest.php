<?php

namespace App\Http\Requests\Oferta;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfertaIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:150'],
            'convocatoria_id' => ['nullable', 'integer', Rule::exists('CONVOCATORIA', 'CONV_ID')],
            'entidad_id' => ['nullable', 'integer', Rule::exists('ENTIDAD', 'ENTI_ID')],
            'estado' => ['nullable', Rule::in(['ENVIADA', 'EN_EVALUACION', 'APROBADA', 'DESAPROBADA', 'ADJUDICADA', 'NO_ADJUDICADA'])],
            'ordenar_por' => ['nullable', Rule::in(['fecha_envio', 'total_general', 'estado'])],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
