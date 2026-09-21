<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RubroUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(['BIEN', 'SERVICIO'])],
            'codigo' => ['required', 'string', 'max:20', Rule::unique('RUBRO', 'RUBR_CODIGO')->where(fn ($q) => $q->where('RUBR_TIPO', $this->input('tipo')))->ignore($this->route('id'), 'RUBR_ID')],
            'nombre' => ['required', 'string', 'max:100'],
            'activo' => ['required', 'boolean'],
        ];
    }
}
