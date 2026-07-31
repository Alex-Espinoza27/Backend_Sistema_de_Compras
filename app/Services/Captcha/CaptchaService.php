<?php

namespace App\Services\Captcha;

/**
 * Interfaz de verificación de captcha para endpoints públicos de registro
 * (02A §7.4). Implementación configurable por CAPTCHA_DRIVER (interno|recaptcha).
 */
interface CaptchaService
{
    public function verificar(?string $token): bool;
}
