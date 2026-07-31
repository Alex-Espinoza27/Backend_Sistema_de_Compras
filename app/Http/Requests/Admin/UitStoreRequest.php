<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UitStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'anio' => ['required', 'integer', 'min:2000', 'max:2100', 'unique:UIT,UITA_ANIO'],
            'valor' => ['required', 'numeric', 'min:0'],
            'vigente' => ['nullable', 'boolean'],
        ];
    }
}
