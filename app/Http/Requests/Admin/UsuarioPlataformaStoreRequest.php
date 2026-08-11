<?php

namespace App\Http\Requests\Admin;

use App\Models\Entidad;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de usuario de plataforma (05C hardening — módulo "Usuarios" unificado:
 * internos + Entidad; reemplaza `UsuarioInternoStoreRequest`, acotado antes a
 * ADMINISTRADOR/AUDITOR). `rol` acepta cualquier rol excepto PROVEEDOR (esos
 * se gestionan en Mantenimiento de Proveedores). `entidad_id` es obligatorio
 * solo si `rol === 'ENTIDAD'`, y debe apuntar a una entidad `APROBADA`.
 */
class UsuarioPlataformaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'correo' => ['required', 'email', 'max:120', 'unique:users,email'],
            'rol' => ['required', Rule::exists('roles', 'name')->where(fn ($q) => $q->where('name', '!=', 'PROVEEDOR'))],
            'entidad_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rol = $this->input('rol');
            $entidadId = $this->input('entidad_id');

            if ($rol === 'ENTIDAD') {
                if (! $entidadId) {
                    $validator->errors()->add('entidad_id', 'Debe seleccionar una entidad para un usuario de rol ENTIDAD.');

                    return;
                }

                $entidad = Entidad::select(['ENTI_ID', 'ENTI_ESTADO_ID'])->with('estado:ESTA_ID,ESTA_CODIGO')->find($entidadId);

                if (! $entidad || $entidad->estado->ESTA_CODIGO !== 'APROBADA') {
                    $validator->errors()->add('entidad_id', 'La entidad debe existir y estar aprobada.');
                }
            } elseif ($entidadId) {
                $validator->errors()->add('entidad_id', 'Solo se puede indicar entidad para un usuario de rol ENTIDAD.');
            }
        });
    }
}
