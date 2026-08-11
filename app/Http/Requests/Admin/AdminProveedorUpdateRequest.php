<?php

namespace App\Http\Requests\Admin;

use App\Models\Proveedor;
use App\Models\TipoDocumento;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edición de proveedor por un administrador (05C hardening). `correo` vive
 * en dos columnas (`users.email` y `PROV_CORREO`) — la unicidad se valida
 * contra `users.email` ignorando el propio `PROV_USER_ID` del proveedor.
 */
class AdminProveedorUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $proveedorId = $this->route('id');
        $usuarioId = Proveedor::where('PROV_ID', $proveedorId)->value('PROV_USER_ID');

        return [
            'numero_documento' => ['required', 'digits_between:8,11', Rule::unique('PROVEEDOR', 'PROV_NUMERO_DOCUMENTO')->ignore($proveedorId, 'PROV_ID')],
            'tipo_documento' => ['required', Rule::in(['RUC', 'DNI', 'CE'])],
            'razon_social' => ['required', 'string', 'max:200'],
            'tipo_persona' => ['required', Rule::in(['JURIDICA', 'NATURAL'])],
            'correo' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($usuarioId)],
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
