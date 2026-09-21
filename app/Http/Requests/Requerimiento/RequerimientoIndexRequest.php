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
            'rubro_ids' => ['nullable', 'array'],
            'rubro_ids.*' => ['integer', Rule::exists('RUBRO', 'RUBR_ID')],
            'convocado' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
            // Solo con efecto para ADMINISTRADOR/AUDITOR (05C hardening).
            'entidad_id' => ['nullable', 'integer', Rule::exists('ENTIDAD', 'ENTI_ID')],
            // Filtro por fecha de creación (05C hardening, pedido
            // explícitamente por el usuario — antes este listado no tenía
            // ningún filtro de fechas).
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'ordenar_por' => ['nullable', Rule::in(['numero', 'objeto', 'tipo', 'fecha_creacion'])],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
