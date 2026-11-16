<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RestablecerPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            // Mínimo 8 caracteres, explícito (no depende de Password::defaults()).
            // La validación corre antes de tocar el token: una contraseña
            // corta no consume el enlace.
            'password' => ['required', 'confirmed', 'max:72', Password::min(8)],
        ];
    }
}
