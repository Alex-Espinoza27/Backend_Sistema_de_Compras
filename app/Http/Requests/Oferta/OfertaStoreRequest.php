<?php

namespace App\Http\Requests\Oferta;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OfertaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = config('archivo.max_mb') * 1024;

        return [
            'convocatoria_id' => ['required', 'integer', Rule::exists('CONVOCATORIA', 'CONV_ID')],
            // Comprobante de pago: solo se exige para convocatorias de tipo
            // SERVICIO (05C hardening — decisión explícita del usuario,
            // desviación consciente de `00_CONTEXTO_TDR.md` §6, documentada
            // en `05C_FRONTEND_FASES.md`).
            'tipo_comprobante_id' => [
                Rule::requiredIf(fn () => $this->tipoConvocatoria() === 'SERVICIO'),
                'nullable', 'integer', Rule::exists('TIPO_COMPROBANTE', 'TICO_ID')->where(fn ($q) => $q->where('TICO_ACTIVO', true)),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.requerimiento_item_id' => ['required', 'integer', Rule::exists('REQUERIMIENTO_ITEM', 'REIT_ID')],
            'items.*.marca_modelo' => ['required', 'string', 'max:150'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'validez_dias' => ['required', Rule::in([15, 30])],
            'plazo_ejecucion_segun_tdr' => ['required', Rule::in(['true', 'false', '1', '0'])],
            'plazo_ejecucion_dias' => [Rule::requiredIf(fn () => ! filter_var($this->input('plazo_ejecucion_segun_tdr'), FILTER_VALIDATE_BOOLEAN)), 'nullable', 'integer', 'min:1'],
            'forma_pago_segun_tdr' => ['required', Rule::in(['true', 'false', '1', '0'])],
            'forma_pago_dias' => [Rule::requiredIf(fn () => ! filter_var($this->input('forma_pago_segun_tdr'), FILTER_VALIDATE_BOOLEAN)), 'nullable', 'integer', 'min:1'],
            'plazo_garantia' => ['required', 'string', 'max:120'],
            'contacto_nombre' => ['required', 'string', 'max:120'],
            'contacto_telefono' => ['required', 'string', 'max:20'],
            'contacto_correo' => ['required', 'email', 'max:120'],
            'declaracion_jurada' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:'.$maxKb],
            // Un solo archivo de sustento (05C hardening: antes admitía
            // varios, pero la nota mostrada al proveedor siempre dijo "un
            // único archivo" — se resuelve la inconsistencia aceptando
            // exactamente lo que se comunica).
            'sustento' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:'.$maxKb],
        ];
    }

    private function tipoConvocatoria(): ?string
    {
        $convocatoriaId = $this->input('convocatoria_id');

        if (! $convocatoriaId) {
            return null;
        }

        $requerimientoId = DB::table('CONVOCATORIA')->where('CONV_ID', $convocatoriaId)->value('CONV_REQUERIMIENTO_ID');

        return $requerimientoId ? DB::table('REQUERIMIENTO')->where('REQU_ID', $requerimientoId)->value('REQU_TIPO') : null;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $convocatoriaId = $this->input('convocatoria_id');

            if (! $convocatoriaId) {
                return;
            }

            $requerimientoId = DB::table('CONVOCATORIA')->where('CONV_ID', $convocatoriaId)->value('CONV_REQUERIMIENTO_ID');

            if (! $requerimientoId) {
                return;
            }

            $idsEsperados = DB::table('REQUERIMIENTO_ITEM')
                ->where('REIT_REQUERIMIENTO_ID', $requerimientoId)
                ->pluck('REIT_ID')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->toArray();

            $idsEnviados = collect($this->input('items', []))
                ->pluck('requerimiento_item_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->toArray();

            if ($idsEsperados !== $idsEnviados) {
                $validator->errors()->add('items', 'Debe cotizar todos los ítems de la convocatoria, ni más ni menos.');
            }
        });
    }
}
