<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TipoComprobanteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:20', 'unique:TIPO_COMPROBANTE,TICO_CODIGO'],
            'nombre' => ['required', 'string', 'max:60'],
            'incluye_igv' => ['required', 'boolean'],
        ];
    }
}
