<?php

namespace App\Http\Requests\Area;

use App\Models\Area;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AreaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // La entidad del área ya existente es fija — se toma de la propia
        // fila, no del usuario autenticado, para que también funcione
        // cuando edita ADMINISTRADOR (05C hardening; antes esta validación
        // quedaba siempre en `null` para ese rol, sin bloquear nada real
        // pero sin comprobar unicidad de verdad).
        $entidadId = Area::withoutGlobalScopes()->where('AREA_ID', $this->route('id'))->value('AREA_ENTIDAD_ID');

        return [
            'nombre' => [
                'required', 'string', 'max:150',
                Rule::unique('AREA', 'AREA_NOMBRE')
                    ->where(fn ($q) => $q->where('AREA_ENTIDAD_ID', $entidadId))
                    ->ignore($this->route('id'), 'AREA_ID'),
            ],
            'sigla' => ['nullable', 'string', 'max:20'],
            'activo' => ['required', 'boolean'],
        ];
    }
}
