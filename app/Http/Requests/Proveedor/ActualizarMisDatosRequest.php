<?php

namespace App\Http\Requests\Proveedor;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarMisDatosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'correo' => ['required', 'email', 'max:120'],
            'telefono' => ['required', 'string', 'max:20'],
            'persona_contacto' => ['required', 'string', 'max:120'],
        ];
    }
}
