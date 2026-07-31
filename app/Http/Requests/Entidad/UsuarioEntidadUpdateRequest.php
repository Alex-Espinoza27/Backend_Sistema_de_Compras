<?php

namespace App\Http\Requests\Entidad;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UsuarioEntidadUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'correo' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($this->route('userId'))],
        ];
    }
}
