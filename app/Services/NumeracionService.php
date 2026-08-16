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

    /**
     * Genera REQU_NUMERO al crear un requerimiento: mismo mecanismo que
     * generar() pero con su propio contador (independiente del de
     * convocatorias) y sin sigla, formato REQ-AÑO-### (igual que el seed).
     */
    public function generarRequerimiento(int $entidadId): string
    {
        $fila = DB::table('ENTIDAD')
            ->select(['ENTI_CORRELATIVO_REQUERIMIENTO', 'ENTI_CORRELATIVO_REQUERIMIENTO_ANIO'])
            ->where('ENTI_ID', $entidadId)
            ->lockForUpdate()
            ->first();

        $anioActual = (int) now()->format('Y');
        $nuevo = ((int) $fila->ENTI_CORRELATIVO_REQUERIMIENTO_ANIO === $anioActual)
            ? (int) $fila->ENTI_CORRELATIVO_REQUERIMIENTO + 1
            : 1;

        DB::table('ENTIDAD')
            ->where('ENTI_ID', $entidadId)
            ->update([
                'ENTI_CORRELATIVO_REQUERIMIENTO' => $nuevo,
                'ENTI_CORRELATIVO_REQUERIMIENTO_ANIO' => $anioActual,
            ]);

        return sprintf('REQ-%d-%03d', $anioActual, $nuevo);
    }

    /**
     * Genera OFER_CONSTANCIA_CODIGO al enviar una postulación: mismo mecanismo
     * que generar()/generarRequerimiento() pero con contador propio del
     * PROVEEDOR (antes usaba el OFER_ID crudo, que no significa nada para el
     * proveedor y se prestaba a confusión con otro tipo de documento).
     *
     * Bug real corregido: el formato anterior ("CP-{correlativo}-{año}") no
     * incluía nada específico del proveedor, así que dos proveedores
     * distintos en su primera oferta del año generaban el mismo código —
     * OFER_CONSTANCIA_CODIGO es único a nivel de toda la tabla (no por
     * proveedor), así que chocaba contra esa restricción apenas había más de
     * un proveedor activo. Se agrega el PROV_ID (globalmente único) como
     * sufijo, igual que CONV_NUMERO usa la sigla de la entidad para lo mismo.
     */
    public function generarOferta(int $proveedorId): string
    {
        $fila = DB::table('PROVEEDOR')
            ->select(['PROV_CORRELATIVO_OFERTA', 'PROV_CORRELATIVO_OFERTA_ANIO'])
            ->where('PROV_ID', $proveedorId)
            ->lockForUpdate()
            ->first();

        $anioActual = (int) now()->format('Y');
        $nuevo = ((int) $fila->PROV_CORRELATIVO_OFERTA_ANIO === $anioActual)
            ? (int) $fila->PROV_CORRELATIVO_OFERTA + 1
            : 1;

        DB::table('PROVEEDOR')
            ->where('PROV_ID', $proveedorId)
            ->update([
                'PROV_CORRELATIVO_OFERTA' => $nuevo,
                'PROV_CORRELATIVO_OFERTA_ANIO' => $anioActual,
            ]);

        return sprintf('CP-%03d-%d-%d', $nuevo, $anioActual, $proveedorId);
    }

    /**
     * Genera CONV_NUMERO_INDAGACION al generar el PDF de resultados
     * (ADJUDICADA o DESIERTA, 05C hardening pedido explícitamente por el
     * usuario): mismo mecanismo que generar()/generarRequerimiento(), pero
     * concatenando entidad+año+correlativo en un solo número (sin guiones),
     * formato observado en el documento de referencia de Lima Compras
     * ("INDAGACIÓN DE MERCADO N° {ENTI_ID}{AÑO}{correlativo de 6 dígitos}").
     * Debe invocarse una sola vez por convocatoria, dentro de la transacción
     * que genera el PDF.
     */
    public function generarIndagacionMercado(int $entidadId): string
    {
        $fila = DB::table('ENTIDAD')
            ->select(['ENTI_CORRELATIVO_INDAGACION', 'ENTI_CORRELATIVO_INDAGACION_ANIO'])
            ->where('ENTI_ID', $entidadId)
            ->lockForUpdate()
            ->first();

        $anioActual = (int) now()->format('Y');
        $nuevo = ((int) $fila->ENTI_CORRELATIVO_INDAGACION_ANIO === $anioActual)
            ? (int) $fila->ENTI_CORRELATIVO_INDAGACION + 1
            : 1;

        DB::table('ENTIDAD')
            ->where('ENTI_ID', $entidadId)
            ->update([
                'ENTI_CORRELATIVO_INDAGACION' => $nuevo,
                'ENTI_CORRELATIVO_INDAGACION_ANIO' => $anioActual,
            ]);

        return sprintf('%d%d%06d', $entidadId, $anioActual, $nuevo);
    }
}
