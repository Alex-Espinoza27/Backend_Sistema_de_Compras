<?php

namespace App\Http\Requests\Admin;

use App\Models\TipoDocumento;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta directa de proveedor por un administrador (05C hardening) — mismas
 * reglas que `RegistroProveedorRequest` (registro público), menos
 * password/captcha/origen: el admin no pasa por el registro público, la
 * cuenta se crea con contraseña aleatoria + enlace de restablecimiento
 * (mismo patrón que `AdministracionService::crearUsuarioInterno`).
 */
class AdminProveedorStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_documento' => ['required', 'digits_between:8,11', 'unique:PROVEEDOR,PROV_NUMERO_DOCUMENTO'],
            'tipo_documento' => ['required', Rule::in(['RUC', 'DNI', 'CE'])],
            'razon_social' => ['required', 'string', 'max:200'],
            'tipo_persona' => ['required', Rule::in(['JURIDICA', 'NATURAL'])],
            'correo' => ['required', 'email', 'max:120', 'unique:users,email'],
            'telefono' => ['required', 'string', 'max:20'],
            'persona_contacto' => ['required', 'string', 'max:120'],
            'rubros_servicios' => ['array'],
            'rubros_servicios.*' => ['integer', 'distinct', Rule::exists('RUBRO', 'RUBR_ID')->where('RUBR_TIPO', 'SERVICIO')],
            'rubros_bienes' => ['array'],
            'rubros_bienes.*' => ['integer', 'distinct', Rule::exists('RUBRO', 'RUBR_ID')->where('RUBR_TIPO', 'BIEN')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (empty($this->input('rubros_servicios', [])) && empty($this->input('rubros_bienes', []))) {
                $validator->errors()->add('rubros_servicios', 'Debe seleccionar al menos un rubro.');
            }

            $tipoDocumento = TipoDocumento::where('TIDO_CODIGO', $this->input('tipo_documento'))->first();
            $numeroDocumento = (string) $this->input('numero_documento');

            if ($tipoDocumento && $numeroDocumento !== '' && strlen($numeroDocumento) !== $tipoDocumento->TIDO_LONGITUD) {
                $validator->errors()->add('numero_documento', "El número de documento debe tener {$tipoDocumento->TIDO_LONGITUD} dígitos para {$tipoDocumento->TIDO_CODIGO}.");
            }
        });
    }
}
