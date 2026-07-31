<?php

namespace App\Http\Requests\Requerimiento;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequerimientoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero' => ['required', 'string', 'max:40'],
            'objeto' => ['required', 'string', 'max:250'],
            'descripcion' => ['required', 'string'],
            'tipo' => ['required', Rule::in(['BIEN', 'SERVICIO'])],
            'rubro_id' => ['nullable', 'integer', Rule::exists('RUBRO', 'RUBR_ID')->where(fn ($q) => $q->where('RUBR_TIPO', $this->input('tipo')))],
            'area_usuaria' => ['required', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.numero' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.descripcion' => ['required', 'string'],
            'items.*.unidad_medida_id' => ['required', 'integer', Rule::exists('UNIDAD_MEDIDA', 'UNME_ID')],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
