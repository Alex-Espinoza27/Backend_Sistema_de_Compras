<?php

namespace App\Services\Captcha;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Captcha interno simple (sin dependencia externa, 02A §7.4): un reto
 * aritmético de un solo uso, cacheado por captcha_id con TTL corto. El
 * token esperado en verificar() tiene la forma "{captcha_id}:{respuesta}".
 */
class CaptchaInternoService implements CaptchaService
{
    private const TTL_SEGUNDOS = 300;

    public function generarReto(): array
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $id = (string) Str::uuid();

        Cache::put("captcha:{$id}", $a + $b, self::TTL_SEGUNDOS);

        return [
            'captcha_id' => $id,
            'pregunta' => "¿Cuánto es {$a} + {$b}?",
        ];
    }

    public function verificar(?string $token): bool
    {
        if (! $token || ! str_contains($token, ':')) {
            return false;
        }

        [$id, $respuesta] = explode(':', $token, 2);

        $clave = "captcha:{$id}";
        $esperado = Cache::get($clave);

        Cache::forget($clave);

        return $esperado !== null && ctype_digit($respuesta) && (int) $respuesta === $esperado;
    }
}
