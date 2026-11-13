<?php
use Illuminate\Support\Facades\Route;

// API-only: sin rutas web. El frontend es una SPA desacoplada (ver 02A).
Route::fallback(function () {
    if (request()->is('api/*') || request()->is('sanctum/*') || request()->is('up')) {
        abort(404);
    }

    $spa = public_path('index.html');
    abort_unless(is_file($spa), 404);

    return response()->file($spa);
});
