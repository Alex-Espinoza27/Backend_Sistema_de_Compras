<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UitUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'anio' => ['nullable', 'integer', 'min:2000', 'max:2100', Rule::unique('UIT', 'UITA_ANIO')->ignore($this->route('id'), 'UITA_ID')],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'vigente' => ['nullable', 'boolean'],
        ];
    }
}
