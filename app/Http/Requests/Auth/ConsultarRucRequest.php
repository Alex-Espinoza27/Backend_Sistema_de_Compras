<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ConsultarRucRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_documento' => ['required', 'digits_between:8,11'],
            'captcha' => ['required', 'string'],
        ];
    }
}
