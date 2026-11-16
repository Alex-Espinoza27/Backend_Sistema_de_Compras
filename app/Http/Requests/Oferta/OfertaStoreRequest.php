<?php

namespace App\Http\Requests\Oferta;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OfertaStoreRequest extends FormRequest
{
    /** Límites de longitud: única fuente. Las reglas los usan y
     * `OfertaController::postulacion` los entrega al frontend para que los
     * muestre en los campos (contador y "máx. N caracteres"). */
    public const MAX_MARCA_MODELO = 150;

    public const MAX_PLAZO_GARANTIA = 120;

    public const MAX_CONTACTO_NOMBRE = 120;

    public const MAX_CONTACTO_TELEFONO = 20;

    public const MAX_CONTACTO_CORREO = 120;

    public const FORMATOS_ARCHIVO = ['pdf', 'doc', 'docx'];

    /** @return array<string, int|string[]> */
    public static function limites(): array
    {
        return [
            'marca_modelo' => self::MAX_MARCA_MODELO,
            'plazo_garantia' => self::MAX_PLAZO_GARANTIA,
            'contacto_nombre' => self::MAX_CONTACTO_NOMBRE,
            'contacto_telefono' => self::MAX_CONTACTO_TELEFONO,
            'contacto_correo' => self::MAX_CONTACTO_CORREO,
            'archivo_max_mb' => (int) config('archivo.max_mb'),
            'archivo_formatos' => self::FORMATOS_ARCHIVO,
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function attributes(): array
    {
        return [
            'convocatoria_id' => 'convocatoria',
            'tipo_comprobante_id' => 'comprobante de pago',
            'items' => 'ítems de la propuesta',
            'items.*.requerimiento_item_id' => 'ítem :position',
            'items.*.marca_modelo' => 'marca/modelo del ítem :position',
            'items.*.precio_unitario' => 'precio unitario del ítem :position',
            'validez_dias' => 'validez de la oferta',
            'plazo_ejecucion_segun_tdr' => 'plazo de ejecución según TDR',
            'plazo_ejecucion_dias' => 'plazo de ejecución (días)',
            'forma_pago_segun_tdr' => 'forma de pago según TDR',
            'forma_pago_dias' => 'forma de pago (días)',
            'plazo_garantia' => 'plazo de garantía',
            'contacto_nombre' => 'nombre de contacto',
            'contacto_telefono' => 'teléfono de contacto',
            'contacto_correo' => 'correo de contacto',
            'declaracion_jurada' => 'declaración jurada',
            'sustento' => 'sustento',
        ];
    }

    /** Mensajes pensados para quien postula (no para quien programa): dicen
     * qué corregir, no el nombre técnico de la regla. */
    public function messages(): array
    {
        $mb = (int) config('archivo.max_mb');
        $formatos = 'PDF, DOC o DOCX';

        return [
            'contacto_correo.email' => 'Escriba un correo válido, por ejemplo nombre@dominio.com (sin espacios ni un punto justo antes de la @).',
            'items.*.precio_unitario.numeric' => 'El precio unitario del ítem :position debe ser un número.',
            'items.*.precio_unitario.min' => 'El precio unitario del ítem :position no puede ser negativo.',
            'validez_dias.in' => 'La validez de la oferta debe ser de 15 o 30 días.',
            'plazo_ejecucion_dias.integer' => 'El plazo de ejecución debe ser un número entero de días.',
            'plazo_ejecucion_dias.min' => 'El plazo de ejecución debe ser de al menos 1 día.',
            'forma_pago_dias.integer' => 'La forma de pago debe ser un número entero de días.',
            'forma_pago_dias.min' => 'La forma de pago debe ser de al menos 1 día.',
            'declaracion_jurada.required' => 'Adjunte la declaración jurada firmada.',
            'declaracion_jurada.mimes' => "La declaración jurada debe ser un archivo {$formatos}.",
            'declaracion_jurada.max' => "La declaración jurada no puede pesar más de {$mb} MB.",
            'declaracion_jurada.uploaded' => "No se pudo subir la declaración jurada. Verifique que no pese más de {$mb} MB e inténtelo de nuevo.",
            'sustento.required' => 'Adjunte el archivo de sustento.',
            'sustento.mimes' => "El sustento debe ser un archivo {$formatos}.",
            'sustento.max' => "El sustento no puede pesar más de {$mb} MB.",
            'sustento.uploaded' => "No se pudo subir el sustento. Verifique que no pese más de {$mb} MB e inténtelo de nuevo.",
        ];
    }

    public function rules(): array
    {
        $maxKb = config('archivo.max_mb') * 1024;
        $formatos = implode(',', self::FORMATOS_ARCHIVO);

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
            'items.*.marca_modelo' => ['required', 'string', 'max:'.self::MAX_MARCA_MODELO],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'validez_dias' => ['required', Rule::in([15, 30])],
            'plazo_ejecucion_segun_tdr' => ['required', Rule::in(['true', 'false', '1', '0'])],
            'plazo_ejecucion_dias' => [Rule::requiredIf(fn () => ! filter_var($this->input('plazo_ejecucion_segun_tdr'), FILTER_VALIDATE_BOOLEAN)), 'nullable', 'integer', 'min:1'],
            'forma_pago_segun_tdr' => ['required', Rule::in(['true', 'false', '1', '0'])],
            'forma_pago_dias' => [Rule::requiredIf(fn () => ! filter_var($this->input('forma_pago_segun_tdr'), FILTER_VALIDATE_BOOLEAN)), 'nullable', 'integer', 'min:1'],
            'plazo_garantia' => ['required', 'string', 'max:'.self::MAX_PLAZO_GARANTIA],
            'contacto_nombre' => ['required', 'string', 'max:'.self::MAX_CONTACTO_NOMBRE],
            'contacto_telefono' => ['required', 'string', 'max:'.self::MAX_CONTACTO_TELEFONO],
            'contacto_correo' => ['required', 'email', 'max:'.self::MAX_CONTACTO_CORREO],
            'declaracion_jurada' => ['required', 'file', 'mimes:'.$formatos, 'max:'.$maxKb],
            // Un solo archivo de sustento (05C hardening: antes admitía
            // varios, pero la nota mostrada al proveedor siempre dijo "un
            // único archivo" — se resuelve la inconsistencia aceptando
            // exactamente lo que se comunica).
            'sustento' => ['required', 'file', 'mimes:'.$formatos, 'max:'.$maxKb],
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
