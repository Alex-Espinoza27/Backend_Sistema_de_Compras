<?php

namespace App\Http\Requests\Entidad;

use Illuminate\Foundation\Http\FormRequest;

class AltaDirectaEntidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_documento' => ['required', 'digits:11', 'unique:ENTIDAD,ENTI_NUMERO_DOCUMENTO'],
            'razon_social' => ['required', 'string', 'max:200'],
            'sigla' => ['required', 'string', 'max:30'],
            'correo' => ['required', 'email', 'max:120'],
            'telefono' => ['required', 'string', 'max:20'],
            'direccion' => ['required', 'string', 'max:200'],
            'persona_contacto' => ['required', 'string', 'max:120'],
            'usuario_inicial' => ['required', 'array'],
            'usuario_inicial.nombre' => ['required', 'string', 'max:120'],
            'usuario_inicial.correo' => ['required', 'email', 'max:120', 'unique:users,email'],
        ];
    }
}
