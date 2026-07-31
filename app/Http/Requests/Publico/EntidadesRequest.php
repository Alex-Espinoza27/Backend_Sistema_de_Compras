<?php

namespace App\Http\Requests\Publico;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntidadesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:150'],
            'solo_con_vigentes' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
        ];
    }
}
