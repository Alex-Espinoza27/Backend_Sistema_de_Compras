<?php

namespace App\Http\Requests\Requerimiento;

/**
 * Límites y mensajes compartidos por crear y editar un requerimiento. Los
 * límites son los mismos que muestra el formulario (contadores y "máx. N
 * caracteres"); los mensajes están escritos para quien llena el formulario.
 */
trait ReglasRequerimiento
{
    public const MAX_OBJETO = 250;

    public const MAX_DESCRIPCION = 2000;

    public const MAX_ITEM_DESCRIPCION = 500;

    public const MAX_PRESENTACION = 120;

    public const MAX_CANTIDAD = 10000000;

    public function attributes(): array
    {
        return [
            'entidad_id' => 'entidad',
            'objeto' => 'objeto',
            'descripcion' => 'descripción',
            'tipo' => 'tipo de requerimiento',
            'rubro_ids' => 'rubros',
            'rubro_ids.*' => 'rubro',
            'area_id' => 'área usuaria',
            'items' => 'ítems',
            'items.*.numero' => 'número del ítem :position',
            'items.*.descripcion' => 'descripción del ítem :position',
            'items.*.unidad_medida_id' => 'unidad de medida del ítem :position',
            'items.*.cantidad' => 'cantidad del ítem :position',
            'items.*.presentacion' => 'presentación del ítem :position',
            'archivo_tdr' => 'archivo TDR / EETT',
        ];
    }

    public function messages(): array
    {
        $mb = (int) config('archivo.max_mb');

        return [
            'items.required' => 'Agregue al menos un ítem.',
            'items.*.descripcion.required' => 'Indique la descripción del ítem :position.',
            'items.*.unidad_medida_id.required' => 'Seleccione la unidad de medida del ítem :position.',
            'items.*.cantidad.required' => 'Indique la cantidad del ítem :position.',
            'items.*.cantidad.numeric' => 'La cantidad del ítem :position debe ser un número.',
            'items.*.cantidad.min' => 'La cantidad del ítem :position debe ser al menos 1.',
            'items.*.cantidad.max' => 'La cantidad del ítem :position no puede superar '.number_format(self::MAX_CANTIDAD, 0, ',', '.').'.',
            'archivo_tdr.mimes' => 'El archivo TDR / EETT debe ser PDF, DOC o DOCX.',
            'archivo_tdr.max' => "El archivo TDR / EETT no puede pesar más de {$mb} MB.",
            'archivo_tdr.uploaded' => "No se pudo subir el archivo TDR / EETT. Verifique que no pese más de {$mb} MB e inténtelo de nuevo.",
        ];
    }
}
