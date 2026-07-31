<?php

namespace App\Services\Captcha;

use Illuminate\Support\Facades\Http;

/**
 * Driver reCAPTCHA (02A §7.4), activo solo si la muni provee llaves en .env.
 */
class CaptchaRecaptchaService implements CaptchaService
{
    public function __construct(private readonly ?string $secret) {}

    public function verificar(?string $token): bool
    {
        if (! $token || ! $this->secret) {
            return false;
        }

        $respuesta = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $this->secret,
            'response' => $token,
        ]);

        return (bool) ($respuesta->json('success') ?? false);
    }
}
