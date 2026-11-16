<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tope absoluto de sesión: `session.lifetime` solo expira por inactividad (se
 * renueva con cada request), así que una sesión usada de forma continua nunca
 * vencería. Aquí se guarda la hora del login (`login_at`) y, pasado
 * `session.absolute_lifetime` minutos, se invalida la sesión: la request
 * sigue como anónima y las rutas protegidas responden 401.
 */
class CaducarSesionAbsoluta
{
    public function handle(Request $request, Closure $next): Response
    {
        $limite = (int) config('session.absolute_lifetime') * 60;

        if ($limite > 0 && $request->hasSession() && Auth::guard('web')->check()) {
            $sesion = $request->session();
            $inicio = $sesion->get('login_at');

            if ($inicio === null) {
                // Sesión iniciada antes de existir este tope: empieza a contar ahora.
                $sesion->put('login_at', now()->timestamp);
            } elseif (now()->timestamp - (int) $inicio >= $limite) {
                Auth::guard('web')->logout();
                $sesion->invalidate();
                $sesion->regenerateToken();
            }
        }

        return $next($request);
    }
}
