<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

/**
 * Envío del enlace para definir/restablecer la contraseña tras un alta o a
 * pedido de un administrador.
 *
 * El correo NUNCA debe impedir el registro (pedido explícito del usuario): si
 * el SMTP falla (credenciales, puerto, servidor caído), el alta ya confirmada
 * se mantiene, el error queda en el log y el administrador recibe un aviso
 * para reenviar el enlace con "Restablecer contraseña".
 */
final class EnlacePassword
{
    public const AVISO_NO_ENVIADO = 'pero no se pudo enviar el correo con el enlace para definir la contraseña. '
        .'Revise la configuración de correo y reenvíelo con "Restablecer contraseña".';

    /** @return bool true si el correo salió. */
    public static function enviar(string $email): bool
    {
        try {
            return Password::sendResetLink(['email' => $email]) === Password::RESET_LINK_SENT;
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar el enlace para definir la contraseña.', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** "Usuario creado." o "Usuario creado, pero no se pudo enviar el correo…". */
    public static function mensaje(string $exito, bool $enviado): string
    {
        return $enviado ? $exito : rtrim($exito, '.').', '.self::AVISO_NO_ENVIADO;
    }
}
