<?php

namespace App\Http\Requests\Proveedor;

use Illuminate\Foundation\Http\FormRequest;

class SuspenderProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirmacion' => ['required', 'accepted'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
