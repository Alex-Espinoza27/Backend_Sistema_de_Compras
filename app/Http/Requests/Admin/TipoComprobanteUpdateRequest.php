<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoComprobanteUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:20', Rule::unique('TIPO_COMPROBANTE', 'TICO_CODIGO')->ignore($this->route('id'), 'TICO_ID')],
            'nombre' => ['required', 'string', 'max:60'],
            'incluye_igv' => ['required', 'boolean'],
        ];
    }
}
