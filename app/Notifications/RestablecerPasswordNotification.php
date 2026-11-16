<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Correo del enlace para definir/restablecer la contraseña, en español y con
 * la vigencia real del enlace (la notificación base siempre dice la del
 * broker por defecto). La URL la sigue armando
 * ResetPassword::createUrlUsing (AppServiceProvider).
 */
class RestablecerPasswordNotification extends ResetPassword
{
    public function __construct(
        string $token,
        private int $minutosVigencia,
        private bool $solicitudPropia = true,
    ) {
        parent::__construct($token);
    }

    public function toMail($notifiable): MailMessage
    {
        $app = config('app.name');
        $mensaje = (new MailMessage)
            ->subject($this->solicitudPropia ? "Restablecer tu contraseña — {$app}" : "Define tu contraseña — {$app}")
            ->greeting('Hola,');

        if ($this->solicitudPropia) {
            $mensaje->line("Recibimos una solicitud para restablecer la contraseña de tu cuenta en {$app}.")
                ->action('Restablecer contraseña', $this->resetUrl($notifiable));
        } else {
            $mensaje->line("Se habilitó el acceso a tu cuenta en {$app} o se solicitó restablecer tu contraseña. Usa el siguiente botón para definirla.")
                ->action('Definir mi contraseña', $this->resetUrl($notifiable));
        }

        return $mensaje
            ->line("Por seguridad, este enlace es de un solo uso y vence en {$this->minutosVigencia} minutos.")
            ->line('Si no esperabas este correo, ignóralo: tu contraseña no se modificará.')
            ->salutation("Saludos, {$app}");
    }
}
