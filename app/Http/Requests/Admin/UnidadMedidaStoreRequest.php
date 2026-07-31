<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UnidadMedidaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:20', 'unique:UNIDAD_MEDIDA,UNME_CODIGO'],
            'nombre' => ['required', 'string', 'max:60'],
        ];
    }
}
