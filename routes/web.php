<?php
use Illuminate\Support\Facades\Route;

// API-only: sin rutas web. El frontend es una SPA desacoplada (ver 02A), el
// auth es Bearer token (sin sesión) — se quita el middleware del grupo `web`
// (sesión/CSRF) porque esta ruta solo sirve un archivo estático, no hay
// formulario ni sesión que proteger.
Route::fallback(function () {
    if (request()->is('api/*') || request()->is('sanctum/*') || request()->is('up')) {
        abort(404);
    }

    $spa = public_path('index.html');
    abort_unless(is_file($spa), 404);

    return response()->file($spa);
})->withoutMiddleware([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
]);
