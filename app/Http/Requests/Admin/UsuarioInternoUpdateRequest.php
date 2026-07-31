<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UsuarioInternoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'correo' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($this->route('id'))],
            'rol' => ['nullable', Rule::in(['ADMINISTRADOR', 'AUDITOR'])],
        ];
    }
}
