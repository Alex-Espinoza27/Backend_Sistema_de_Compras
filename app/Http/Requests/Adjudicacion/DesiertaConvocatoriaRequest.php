<?php

namespace App\Http\Requests\Adjudicacion;

use Illuminate\Foundation\Http\FormRequest;

class DesiertaConvocatoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:1000'],
        ];
    }
}
