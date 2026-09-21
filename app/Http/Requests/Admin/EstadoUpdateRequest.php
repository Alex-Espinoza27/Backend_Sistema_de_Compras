<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EstadoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contexto' => ['required', Rule::in(['CONVOCATORIA', 'OFERTA', 'ENTIDAD', 'PROVEEDOR'])],
            'codigo' => ['required', 'string', 'max:20', Rule::unique('ESTADO', 'ESTA_CODIGO')->where(fn ($q) => $q->where('ESTA_CONTEXTO', $this->input('contexto')))->ignore($this->route('id'), 'ESTA_ID')],
            'nombre' => ['required', 'string', 'max:60'],
            'color' => ['required', 'string', 'max:20'],
            'orden' => ['required', 'integer', 'min:1'],
            'activo' => ['required', 'boolean'],
        ];
    }
}
