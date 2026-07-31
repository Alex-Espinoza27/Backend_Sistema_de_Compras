<?php

namespace App\Services;

use App\Models\Auditoria;

/**
 * Escribe en AUDITORIA desde la capa de aplicación (02A §7.1): necesita IP,
 * user-agent y endpoint, que la BD no conoce. Solo inserción (inmutable).
 */
class AuditoriaService
{
    public function registrar(
        string $modulo,
        string $accion,
        ?string $tabla = null,
        ?int $registroId = null,
        ?array $valorAnterior = null,
        ?array $valorNuevo = null,
        ?int $userId = null,
    ): Auditoria {
        $request = request();

        return Auditoria::create([
            'AUDI_USER_ID' => $userId ?? auth()->id(),
            'AUDI_MODULO' => $modulo,
            'AUDI_ACCION' => $accion,
            'AUDI_TABLA' => $tabla,
            'AUDI_REGISTRO_ID' => $registroId,
            'AUDI_VALOR_ANTERIOR' => $valorAnterior,
            'AUDI_VALOR_NUEVO' => $valorNuevo,
            'AUDI_IP' => $request->ip(),
            'AUDI_USER_AGENT' => mb_substr((string) $request->userAgent(), 0, 255),
            'AUDI_ENDPOINT' => $request->path(),
            'AUDI_FECHA' => now(),
        ]);
    }
}
