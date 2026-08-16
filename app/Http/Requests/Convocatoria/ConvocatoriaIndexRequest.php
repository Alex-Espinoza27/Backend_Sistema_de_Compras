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
            // Módulo Evaluación (05C hardening, pedido explícitamente por el
            // usuario): convocatorias PUBLICADA o EN_EVALUACION con al menos
            // una oferta — evaluar ya no espera a que venza el plazo.
            'lista_para_evaluar' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
            // Módulo Adjudicación (M08 del TDR, 05C hardening): convocatorias
            // EN_EVALUACION donde ninguna oferta sigue pendiente de evaluar
            // (mismo criterio que ya exige `AdjudicacionService::adjudicar`,
            // sin agregar un estado nuevo — decisión confirmada con el usuario).
            'lista_para_adjudicar' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
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
