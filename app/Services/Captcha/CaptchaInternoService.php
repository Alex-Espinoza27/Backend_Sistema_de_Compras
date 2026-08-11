<?php

namespace App\Services\Captcha;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Captcha interno simple (sin dependencia externa, 02A §7.4): imagen de texto
 * distorsionado (GD, ya instalado en el contenedor — sin paquete Composer
 * nuevo) de un solo uso, cacheada por captcha_id con TTL corto. El token
 * esperado en verificar() tiene la forma "{captcha_id}:{respuesta}".
 *
 * 05C hardening: antes era un reto aritmético de texto ("¿Cuánto es 3 + 4?");
 * se reemplaza por una imagen con caracteres alfanuméricos porque es más
 * difícil de resolver por un bot simple sin OCR.
 */
class CaptchaInternoService implements CaptchaService
{
    private const TTL_SEGUNDOS = 300;

    // Sin caracteres ambiguos (0/O, 1/I/l).
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const LARGO_CODIGO = 5;

    public function generarReto(): array
    {
        $codigo = '';
        for ($i = 0; $i < self::LARGO_CODIGO; $i++) {
            $codigo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        $id = (string) Str::uuid();
        Cache::put("captcha:{$id}", $codigo, self::TTL_SEGUNDOS);

        return [
            'captcha_id' => $id,
            'imagen' => $this->dibujarImagen($codigo),
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

        return $esperado !== null && strcasecmp((string) $respuesta, (string) $esperado) === 0;
    }

    /** Imagen PNG con el código, ruido de fondo y jitter por carácter — sin
     * depender de ninguna fuente TTF (no hay garantía de que el contenedor
     * tenga una instalada), solo las fuentes de mapa de bits que GD trae
     * incorporadas, escaladas 2x para que se vean más grandes y "rugosas". */
    private function dibujarImagen(string $codigo): string
    {
        $anchoBase = 90;
        $altoBase = 28;
        $imagen = imagecreatetruecolor($anchoBase, $altoBase);

        $fondo = imagecolorallocate($imagen, 245, 248, 251);
        imagefill($imagen, 0, 0, $fondo);

        for ($i = 0; $i < 4; $i++) {
            $color = imagecolorallocate($imagen, random_int(160, 200), random_int(160, 200), random_int(160, 200));
            imageline($imagen, random_int(0, $anchoBase), random_int(0, $altoBase), random_int(0, $anchoBase), random_int(0, $altoBase), $color);
        }
        for ($i = 0; $i < 40; $i++) {
            $color = imagecolorallocate($imagen, random_int(180, 220), random_int(180, 220), random_int(180, 220));
            imagesetpixel($imagen, random_int(0, $anchoBase - 1), random_int(0, $altoBase - 1), $color);
        }

        $x = 6;
        foreach (str_split($codigo) as $caracter) {
            $color = imagecolorallocate($imagen, random_int(10, 60), random_int(30, 90), random_int(90, 150));
            $y = random_int(2, 8);
            imagechar($imagen, 5, $x, $y, $caracter, $color);
            $x += 16;
        }

        $escalada = imagescale($imagen, $anchoBase * 2, $altoBase * 2);
        imagedestroy($imagen);

        ob_start();
        imagepng($escalada);
        $contenido = ob_get_clean();
        imagedestroy($escalada);

        return 'data:image/png;base64,'.base64_encode($contenido);
    }
}
