<?php

namespace App\Http\Requests\Convocatoria;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvocatoriaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requerimiento_id' => ['required', 'integer', Rule::exists('REQUERIMIENTO', 'REQU_ID')->whereNull('REQU_ELIMINADO_EN')],
            'nombre_servicio' => ['required', 'string', 'max:250'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
            'archivo_plantilla_dj' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:'.(config('archivo.max_mb') * 1024)],
        ];
    }
}
