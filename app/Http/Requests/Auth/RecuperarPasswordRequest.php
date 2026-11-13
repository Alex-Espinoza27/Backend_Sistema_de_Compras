<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RecuperarPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            // 05C hardening (pedido explícitamente por el usuario): mismo
            // captcha que ya exige el registro, para que este endpoint
            // público (sin más límite que `throttle:auth`) no sea un vector
            // fácil de automatizar envíos masivos de enlaces.
            'captcha' => ['required', 'string'],
        ];
    }
}
