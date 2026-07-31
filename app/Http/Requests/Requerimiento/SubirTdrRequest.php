<?php

namespace App\Http\Requests\Requerimiento;

use Illuminate\Foundation\Http\FormRequest;

class SubirTdrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:'.(config('archivo.max_mb') * 1024)],
        ];
    }
}
