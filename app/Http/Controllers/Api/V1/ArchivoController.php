<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Archivo;
use App\Models\Convocatoria;
use App\Models\Requerimiento;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Descarga transversal de ARCHIVO (03_API_FULL M06, adelantado a Fase 5 por
 * decisión del usuario). El permiso depende de ARCH_CONTEXTO; el scope por
 * entidad lo aplica el global scope DeSuEntidad al resolver el dueño.
 */
class ArchivoController extends Controller
{
    public function descargar(Request $request, int $id): StreamedResponse
    {
        $archivo = Archivo::select(['ARCH_ID', 'ARCH_CONTEXTO', 'ARCH_REFERENCIA_ID', 'ARCH_NOMBRE_ORIGINAL', 'ARCH_RUTA', 'ARCH_MIME'])
            ->findOrFail($id);

        $this->autorizar($request->user(), $archivo);

        if (! Storage::exists($archivo->ARCH_RUTA)) {
            throw new HttpException(404, 'El archivo ya no está disponible.');
        }

        return Storage::download($archivo->ARCH_RUTA, $archivo->ARCH_NOMBRE_ORIGINAL, [
            'Content-Type' => $archivo->ARCH_MIME,
        ]);
    }

    private function autorizar(User $user, Archivo $archivo): void
    {
        match ($archivo->ARCH_CONTEXTO) {
            'REQUERIMIENTO_TDR' => $this->autorizarRequerimiento($user, $archivo->ARCH_REFERENCIA_ID),
            'CONVOCATORIA_DOC', 'CONVOCATORIA_PLANTILLA_DJ' => $this->autorizarConvocatoria($user, $archivo->ARCH_REFERENCIA_ID),
            'OFERTA_DJ', 'OFERTA_SUSTENTO' => $this->exigirPermiso($user, 'ofertas.ver'),
            'PROVEEDOR_DOC' => $this->exigirPermiso($user, 'proveedores.ver_propio'),
            'RESULTADO_PDF' => null,
            default => throw new HttpException(404, 'El recurso solicitado no existe.'),
        };
    }

    private function autorizarRequerimiento(User $user, int $requerimientoId): void
    {
        $this->exigirPermiso($user, 'requerimientos.ver');

        Requerimiento::select(['REQU_ID'])->findOrFail($requerimientoId);
    }

    private function autorizarConvocatoria(User $user, int $convocatoriaId): void
    {
        $this->exigirPermiso($user, 'convocatorias.ver');

        Convocatoria::select(['CONV_ID'])->findOrFail($convocatoriaId);
    }

    private function exigirPermiso(User $user, string $permiso): void
    {
        if (! $user->can($permiso)) {
            throw new HttpException(403, 'No tiene permiso para descargar este archivo.');
        }
    }
}
