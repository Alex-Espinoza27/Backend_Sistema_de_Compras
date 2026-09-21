<?php

namespace App\Http\Requests\Admin;

use App\Models\Entidad;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Edición de usuario de plataforma (05C hardening) — ver `UsuarioPlataformaStoreRequest`. */
class UsuarioPlataformaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dni' => [
                'required',
                'string',
                'digits:8',
                Rule::unique('users', 'dni')
                    ->ignore($this->route('id'))
                    ->where(fn ($q) => $q->where('entidad_id', $this->input('entidad_id'))),
            ],
            'nombres' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['required', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($this->route('id'))],
            'sexo' => ['required', 'string', Rule::in(['M', 'F'])],
            'rol' => ['nullable', Rule::exists('roles', 'name')->where(fn ($q) => $q->where('name', '!=', 'PROVEEDOR'))],
            'entidad_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rol = $this->input('rol');
            $entidadId = $this->input('entidad_id');

            if (! $rol) {
                return;
            }

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
