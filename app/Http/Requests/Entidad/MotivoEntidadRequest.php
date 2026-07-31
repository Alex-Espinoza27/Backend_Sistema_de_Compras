<?php

namespace App\Http\Requests\Entidad;

use Illuminate\Foundation\Http\FormRequest;

class MotivoEntidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:1000'],
        ];
    }
}
