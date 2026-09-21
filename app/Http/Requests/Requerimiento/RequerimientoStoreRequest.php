<?php

namespace App\Http\Requests\Requerimiento;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequerimientoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Entidad efectiva para crear el requerimiento: la propia si el usuario
     * tiene rol ENTIDAD, o la elegida explícitamente en cualquier otro caso
     * (05C hardening — ADMINISTRADOR y cualquier rol "staff interno" custom
     * no tienen entidad propia). Se verifica el ROL, no solo si
     * `entidad_id` está vacío en la fila del usuario. */
    private function entidadIdEfectiva(): ?int
    {
        return $this->user()->hasRole('ENTIDAD') ? $this->user()->entidad_id : ($this->integer('entidad_id') ?: null);
    }

    public function rules(): array
    {
        $maxKb = config('archivo.max_mb') * 1024;
        $entidadId = $this->entidadIdEfectiva();

        return [
            // Solo obligatorio para quien no tiene entidad propia.
            'entidad_id' => [
                Rule::requiredIf(fn () => ! $this->user()->hasRole('ENTIDAD')),
                'nullable', 'integer', Rule::exists('ENTIDAD', 'ENTI_ID'),
            ],
            'objeto' => ['required', 'string', 'max:250'],
            'descripcion' => ['required', 'string'],
            'tipo' => ['required', Rule::in(['BIEN', 'SERVICIO'])],
            // Un requerimiento puede tener más de un rubro (05C hardening).
            'rubro_ids' => ['nullable', 'array'],
            'rubro_ids.*' => ['integer', Rule::exists('RUBRO', 'RUBR_ID')->where(fn ($q) => $q->where('RUBR_TIPO', $this->input('tipo'))->where('RUBR_ACTIVO', true))],
            // Catálogo de áreas por entidad (05C hardening) — reemplaza el
            // texto libre; debe ser un área activa de la entidad efectiva.
            'area_id' => [
                'required', 'integer',
                Rule::exists('AREA', 'AREA_ID')
                    ->where(fn ($q) => $q->where('AREA_ENTIDAD_ID', $entidadId)->where('AREA_ACTIVO', true)),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.numero' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.descripcion' => ['required', 'string'],
            'items.*.unidad_medida_id' => ['required', 'integer', Rule::exists('UNIDAD_MEDIDA', 'UNME_ID')->where(fn ($q) => $q->where('UNME_ACTIVO', true))],
            'items.*.cantidad' => ['required', 'numeric', 'min:1', 'max:10000000'],
            // Presentación/contenido opcional (05C hardening, ej. "500 ml"
            // cuando la unidad de medida por sí sola no basta).
            'items.*.presentacion' => ['nullable', 'string', 'max:120'],
            'archivo_tdr' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:'.$maxKb],
        ];
    }
}
