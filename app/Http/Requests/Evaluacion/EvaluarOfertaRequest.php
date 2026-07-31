<?php

namespace App\Http\Requests\Evaluacion;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EvaluarOfertaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'eval_tecnica' => ['sometimes', 'nullable', Rule::in(['APROBADO', 'DESAPROBADO'])],
            'eval_tecnica_motivo' => [Rule::requiredIf(fn () => $this->input('eval_tecnica') === 'DESAPROBADO'), 'nullable', 'string', 'max:1000'],
            'eval_logistica' => ['sometimes', 'nullable', Rule::in(['APROBADO', 'DESAPROBADO'])],
            'eval_logistica_motivo' => [Rule::requiredIf(fn () => $this->input('eval_logistica') === 'DESAPROBADO'), 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->has('eval_tecnica') && ! $this->has('eval_logistica')) {
                $validator->errors()->add('eval_tecnica', 'Debe registrar el resultado de al menos una compuerta.');
            }
        });
    }
}
