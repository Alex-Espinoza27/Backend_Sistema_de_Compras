<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Genera CONV_NUMERO al publicar (01 §6.1): contador propio de cada ENTIDAD,
 * bloqueo de fila (FOR UPDATE) y reinicio anual. Nunca usa MAX() ni el id.
 * Debe invocarse dentro de la transacción de publicación.
 */
class NumeracionService
{
    public function generar(int $entidadId): string
    {
        $fila = DB::table('ENTIDAD')
            ->select(['ENTI_CORRELATIVO_CONVOCATORIA', 'ENTI_CORRELATIVO_ANIO', 'ENTI_SIGLA'])
            ->where('ENTI_ID', $entidadId)
            ->lockForUpdate()
            ->first();

        $anioActual = (int) now()->format('Y');
        $nuevo = ((int) $fila->ENTI_CORRELATIVO_ANIO === $anioActual)
            ? (int) $fila->ENTI_CORRELATIVO_CONVOCATORIA + 1
            : 1;

        DB::table('ENTIDAD')
            ->where('ENTI_ID', $entidadId)
            ->update([
                'ENTI_CORRELATIVO_CONVOCATORIA' => $nuevo,
                'ENTI_CORRELATIVO_ANIO' => $anioActual,
            ]);

        return sprintf('CM-%03d-%d-%s', $nuevo, $anioActual, $fila->ENTI_SIGLA);
    }
}
