<?php

namespace App\Http\Requests\Entidad;

use Illuminate\Foundation\Http\FormRequest;

class UsuarioEntidadStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'correo' => ['required', 'email', 'max:120', 'unique:users,email'],
        ];
    }
}
