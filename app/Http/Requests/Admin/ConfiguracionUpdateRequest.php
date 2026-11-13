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

    /** COLOR_PRIMARIO (05C hardening, pedido explícitamente por el usuario):
     * se aplica en caliente al tema de toda la plataforma (header + botones
     * primarios) — un valor que no sea un hex válido rompería el color en
     * cualquier pantalla, así que se valida aquí y no solo en el frontend. */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            foreach ($this->input('configuraciones', []) as $i => $item) {
                $valor = $item['valor'] ?? '';
                if (($item['clave'] ?? null) === 'COLOR_PRIMARIO' && $valor !== '' && ! preg_match('/^#[0-9A-Fa-f]{6}$/', $valor)) {
                    $validator->errors()->add("configuraciones.{$i}.valor", 'El color principal debe ser un hexadecimal válido, ej. #0090D8.');
                }
            }
        });
    }
}
