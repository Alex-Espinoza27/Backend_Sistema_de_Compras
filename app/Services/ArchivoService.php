<?php

namespace App\Services;

use App\Models\Archivo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Gestión documental unificada (02A §7.3): storage + hash SHA-256 + registro
 * en ARCHIVO. Nunca expone rutas absolutas al llamador.
 */
class ArchivoService
{
    public function subir(UploadedFile $archivo, string $contexto, int $referenciaId): Archivo
    {
        $mimesPermitidos = config('archivo.mime_permitidos');
        $maxBytes = config('archivo.max_mb') * 1024 * 1024;

        if ($mimesPermitidos && ! in_array($archivo->getMimeType(), $mimesPermitidos, true)) {
            throw new HttpException(422, 'El tipo de archivo no está permitido.');
        }

        if ($archivo->getSize() > $maxBytes) {
            throw new HttpException(422, 'El archivo excede el tamaño máximo permitido.');
        }

        $hash = hash_file('sha256', $archivo->getRealPath());
        $nombreAlmacenado = Str::uuid().'.'.$archivo->getClientOriginalExtension();
        $ruta = $archivo->storeAs("archivos/{$contexto}", $nombreAlmacenado);

        return Archivo::create([
            'ARCH_CONTEXTO' => $contexto,
            'ARCH_REFERENCIA_ID' => $referenciaId,
            'ARCH_NOMBRE_ORIGINAL' => $archivo->getClientOriginalName(),
            'ARCH_RUTA' => $ruta,
            'ARCH_MIME' => $archivo->getMimeType(),
            'ARCH_TAMANO_BYTES' => $archivo->getSize(),
            'ARCH_HASH_SHA256' => $hash,
        ]);
    }

    /**
     * Igual que subir(), pero para contenido generado por la propia app
     * (ej. PDF de resultados) en vez de un UploadedFile del cliente.
     */
    public function guardarContenido(string $contenido, string $contexto, int $referenciaId, string $nombreOriginal, string $mime): Archivo
    {
        $nombreAlmacenado = Str::uuid().'.pdf';
        $ruta = "archivos/{$contexto}/{$nombreAlmacenado}";

        Storage::put($ruta, $contenido);

        return Archivo::create([
            'ARCH_CONTEXTO' => $contexto,
            'ARCH_REFERENCIA_ID' => $referenciaId,
            'ARCH_NOMBRE_ORIGINAL' => $nombreOriginal,
            'ARCH_RUTA' => $ruta,
            'ARCH_MIME' => $mime,
            'ARCH_TAMANO_BYTES' => strlen($contenido),
            'ARCH_HASH_SHA256' => hash('sha256', $contenido),
        ]);
    }

    public function eliminar(Archivo $archivo): void
    {
        Storage::delete($archivo->ARCH_RUTA);
        $archivo->delete();
    }
}
