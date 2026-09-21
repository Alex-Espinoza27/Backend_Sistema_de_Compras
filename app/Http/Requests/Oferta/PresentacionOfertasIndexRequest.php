<?php

namespace App\Http\Requests\Oferta;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PresentacionOfertasIndexRequest extends FormRequest
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
            'estado' => ['nullable', Rule::in(['ENVIADA', 'EN_EVALUACION', 'APROBADA', 'DESAPROBADA', 'ADJUDICADA', 'NO_ADJUDICADA'])],
            // Filtro por fecha de envío (05C hardening, pedido explícitamente
            // por el usuario — antes este listado no tenía filtro de fechas).
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'ordenar_por' => ['nullable', Rule::in(['fecha_envio', 'total_general'])],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
