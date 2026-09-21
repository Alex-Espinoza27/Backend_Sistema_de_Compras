<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnidadMedidaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:20', Rule::unique('UNIDAD_MEDIDA', 'UNME_CODIGO')->ignore($this->route('id'), 'UNME_ID')],
            'nombre' => ['required', 'string', 'max:60'],
            'activo' => ['required', 'boolean'],
        ];
    }
}
