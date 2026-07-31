<?php

namespace App\Http\Requests\Adjudicacion;

use Illuminate\Foundation\Http\FormRequest;

class AdjudicarConvocatoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'oferta_id' => ['nullable', 'integer'],
        ];
    }
}
