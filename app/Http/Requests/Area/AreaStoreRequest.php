<?php

namespace App\Http\Requests\Area;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AreaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Entidad efectiva para crear el área: la propia si el usuario tiene
     * rol ENTIDAD, o la elegida explícitamente en cualquier otro caso (05C
     * hardening — ADMINISTRADOR y cualquier rol "staff interno" custom no
     * tienen entidad propia). Se verifica el ROL, no solo si `entidad_id`
     * está vacío en la fila del usuario — un usuario no-ENTIDAD nunca debe
     * usar su propio `entidad_id` aunque esa columna tuviera algún valor. */
    private function entidadIdEfectiva(): ?int
    {
        return $this->user()->hasRole('ENTIDAD') ? $this->user()->entidad_id : ($this->integer('entidad_id') ?: null);
    }

    public function rules(): array
    {
        $entidadId = $this->entidadIdEfectiva();

        return [
            // Solo obligatorio para quien no tiene entidad propia.
            'entidad_id' => [
                Rule::requiredIf(fn () => ! $this->user()->hasRole('ENTIDAD')),
                'nullable', 'integer', Rule::exists('ENTIDAD', 'ENTI_ID'),
            ],
            'nombre' => [
                'required', 'string', 'max:150',
                Rule::unique('AREA', 'AREA_NOMBRE')->where(fn ($q) => $q->where('AREA_ENTIDAD_ID', $entidadId)),
            ],
            'sigla' => ['nullable', 'string', 'max:20'],
        ];
    }
}
