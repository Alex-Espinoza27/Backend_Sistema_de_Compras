<?php

namespace App\Http\Requests\Requerimiento;

use App\Models\Requerimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequerimientoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = config('archivo.max_mb') * 1024;
        // La entidad del requerimiento ya existente es fija (no se elige de
        // nuevo al editar) — se toma de la propia fila, no del usuario
        // autenticado, para que también funcione cuando edita ADMINISTRADOR
        // (05C hardening; antes esta validación quedaba siempre en `null`
        // para ese rol y ningún `area_id` podía pasarla).
        $entidadId = Requerimiento::where('REQU_ID', $this->route('id'))->value('REQU_ENTIDAD_ID');

        return [
            'objeto' => ['required', 'string', 'max:250'],
            'descripcion' => ['required', 'string'],
            'tipo' => ['required', Rule::in(['BIEN', 'SERVICIO'])],
            // Un requerimiento puede tener más de un rubro (05C hardening).
            'rubro_ids' => ['nullable', 'array'],
            'rubro_ids.*' => ['integer', Rule::exists('RUBRO', 'RUBR_ID')->where(fn ($q) => $q->where('RUBR_TIPO', $this->input('tipo')))],
            // Catálogo de áreas por entidad (05C hardening) — reemplaza el
            // texto libre; debe ser un área activa de la propia entidad.
            'area_id' => [
                'required', 'integer',
                Rule::exists('AREA', 'AREA_ID')
                    ->where(fn ($q) => $q->where('AREA_ENTIDAD_ID', $entidadId)->where('AREA_ACTIVO', true)),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.numero' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.descripcion' => ['required', 'string'],
            'items.*.unidad_medida_id' => ['required', 'integer', Rule::exists('UNIDAD_MEDIDA', 'UNME_ID')],
            'items.*.cantidad' => ['required', 'numeric', 'min:1', 'max:10000000'],
            // Presentación/contenido opcional (05C hardening, ej. "500 ml"
            // cuando la unidad de medida por sí sola no basta).
            'items.*.presentacion' => ['nullable', 'string', 'max:120'],
            'archivo_tdr' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:'.$maxKb],
        ];
    }
}
