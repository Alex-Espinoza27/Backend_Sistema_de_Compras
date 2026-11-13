<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoDocumentoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:10', Rule::unique('TIPO_DOCUMENTO', 'TIDO_CODIGO')->ignore($this->route('id'), 'TIDO_ID')],
            'nombre' => ['required', 'string', 'max:60'],
            'longitud' => ['required', 'integer', 'min:1', 'max:20'],
            'activo' => ['required', 'boolean'],
        ];
    }
}
