<?php

namespace App\Http\Requests\Convocatoria;

use Illuminate\Foundation\Http\FormRequest;

class AmpliarFechaFinConvocatoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Que la nueva fecha sea posterior a la CONV_FECHA_FIN *actual* (no
        // solo "una fecha futura") se valida en ConvocatoriaService::
        // ampliarFechaFin() — ahí sí se compara contra el valor real en BD,
        // no contra otro campo del mismo request.
        return [
            'fecha_fin' => ['required', 'date'],
        ];
    }
}
