<?php

namespace App\Http\Requests\Entidad;

use Illuminate\Foundation\Http\FormRequest;

class AprobarEntidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usuario_inicial' => ['nullable', 'array'],
            'usuario_inicial.nombre' => ['required_with:usuario_inicial', 'string', 'max:120'],
            'usuario_inicial.correo' => ['required_with:usuario_inicial', 'email', 'max:120', 'unique:users,email'],
        ];
    }
}
