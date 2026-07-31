<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfiguracionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'configuraciones' => ['required', 'array', 'min:1'],
            'configuraciones.*.clave' => ['required', 'string', Rule::exists('CONFIGURACION', 'CONF_CLAVE')],
            'configuraciones.*.valor' => ['required', 'string'],
        ];
    }
}
