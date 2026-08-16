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
            // 'present'+'nullable' en vez de 'required': permite dejar una
            // clave en blanco a propósito (ej. SMTP_CIFRADO="" = sin cifrado)
            // — antes no se podía guardar ningún valor vacío (05C hardening).
            // 'nullable' hace falta porque el middleware global
            // ConvertEmptyStringsToNull convierte "" a null antes de validar.
            'configuraciones.*.valor' => ['present', 'nullable', 'string'],
        ];
    }
}
