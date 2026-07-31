<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Envelope uniforme de la API (02A §3.1):
 *  Éxito:  { ok: true,  data: ..., mensaje: null }
 *  Error:  { ok: false, data: null, mensaje: "...", errores?: {...} }
 */
class ApiResponse
{
    public static function success(mixed $data = null, ?string $mensaje = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $data,
            'mensaje' => $mensaje,
        ], $status);
    }

    public static function paginado(LengthAwarePaginator $paginator, ?string $mensaje = null, int $status = 200): JsonResponse
    {
        return self::success([
            'items' => $paginator->items(),
            'paginacion' => [
                'pagina' => $paginator->currentPage(),
                'por_pagina' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ultima_pagina' => $paginator->lastPage(),
            ],
        ], $mensaje, $status);
    }

    public static function error(?string $mensaje, ?array $errores = null, int $status = 422): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'data' => null,
            'mensaje' => $mensaje,
            'errores' => $errores,
        ], $status);
    }
}
