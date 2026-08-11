<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear un rol nuevo (05C hardening). Se comporta como "staff interno"
 * genérico: no hereda el scoping por entidad de `DeSuEntidad` ni el acceso a
 * las rutas de proveedor (`role:PROVEEDOR`), que solo miran el nombre exacto
 * `ENTIDAD`/`PROVEEDOR` — decisión confirmada explícitamente por el usuario.
 * Por eso el nombre no puede ser uno de los 4 roles reservados.
 */
class RolStoreRequest extends FormRequest
{
    private const RESERVADOS = ['ADMINISTRADOR', 'AUDITOR', 'ENTIDAD', 'PROVEEDOR'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => [
                'required', 'string', 'max:40',
                'regex:/^[A-Z_]+$/',
                Rule::notIn(self::RESERVADOS),
                'unique:roles,name',
            ],
            'permisos' => ['array'],
            'permisos.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.regex' => 'El nombre del rol solo puede tener letras mayúsculas y guion bajo (ej. SUPERVISOR).',
        ];
    }
}
