<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Igual que `ValidateCsrfToken`, pero la cookie con el token CSRF no se llama
 * `XSRF-TOKEN`, sino `XSRF-TOKEN-COMPRAS`.
 *
 * Por qué: los navegadores comparten las cookies de un mismo servidor aunque el
 * puerto sea distinto, y Compras (:8090) vive junto a otras aplicaciones Laravel
 * (p. ej. Registro de Visitas) en 172.17.2.249. Todas escriben `XSRF-TOKEN`, y la
 * última en responder pisa a las demás: el token de Visitas, cifrado con otra
 * APP_KEY, llega a Compras y todo POST falla con 419 "CSRF token mismatch" aunque
 * los GET sigan funcionando. Con nombre propio ya no hay nada que pisar.
 *
 * El frontend lee esta cookie con `xsrfCookieName` (src/api/cliente.ts).
 */
class ValidarCsrfCompras extends ValidateCsrfToken
{
    public const COOKIE = 'XSRF-TOKEN-COMPRAS';

    protected function newCookie($request, $config)
    {
        return new Cookie(
            self::COOKIE,
            $request->session()->token(),
            $this->availableAt(60 * $config['lifetime']),
            $config['path'],
            $config['domain'],
            $config['secure'],
            false,
            false,
            $config['same_site'] ?? null,
            $config['partitioned'] ?? false
        );
    }
}
