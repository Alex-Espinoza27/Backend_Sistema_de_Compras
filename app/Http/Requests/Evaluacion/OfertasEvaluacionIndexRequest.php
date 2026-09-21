<?php

namespace App\Http\Requests\Evaluacion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paginación de la bandeja de evaluación (05C hardening, pedido explícitamente
 * por el usuario): una convocatoria puede recibir más de 100 ofertas, y antes
 * `EvaluacionController::ofertas()` las traía todas de un solo golpe.
 */
class OfertasEvaluacionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
