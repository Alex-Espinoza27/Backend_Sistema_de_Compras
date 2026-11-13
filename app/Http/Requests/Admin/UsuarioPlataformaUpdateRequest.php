<?php

namespace App\Http\Requests\Admin;

use App\Models\Entidad;
use App\Models\TipoDocumento;
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
                Rule::unique('users', 'dni')
                    ->ignore($this->route('id'))
                    ->where(fn ($q) => $q->where('entidad_id', $this->input('entidad_id'))),
            ],
            'tipo_documento' => ['nullable', Rule::in(['DNI', 'CE', 'PASAPORTE'])],
            'nombres' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['required', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($this->route('id'))],
            'sexo' => ['required', 'string', Rule::in(['M', 'F'])],
            'celular' => ['nullable', 'string', 'regex:/^9[0-9]{8}$/'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'rol' => ['nullable', Rule::exists('roles', 'name')->where(fn ($q) => $q->where('name', '!=', 'PROVEEDOR'))],
            'entidad_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tipoDocumento = TipoDocumento::where('TIDO_CODIGO', $this->input('tipo_documento', 'DNI'))->first();
            $dni = (string) $this->input('dni');

            if ($tipoDocumento && $dni !== '' && strlen($dni) !== $tipoDocumento->TIDO_LONGITUD) {
                $validator->errors()->add('dni', "El número de documento debe tener {$tipoDocumento->TIDO_LONGITUD} dígitos para {$tipoDocumento->TIDO_CODIGO}.");
            }

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
