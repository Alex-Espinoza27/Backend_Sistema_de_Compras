<?php

namespace App\Http\Requests\Proveedor;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarMisRubrosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
        });
    }
}
