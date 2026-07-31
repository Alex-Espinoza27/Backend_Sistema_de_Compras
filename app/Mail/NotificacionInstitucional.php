<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Correo institucional genérico (02A §7.6): usado por NotificacionService
 * para avisos que no tienen destinatario en users (ej. resolución de una
 * solicitud de entidad, cuando aún no existe usuario).
 */
class NotificacionInstitucional extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $asuntoCorreo,
        public string $cuerpo,
    ) {}

    public function build(): self
    {
        return $this->subject($this->asuntoCorreo)
            ->view('emails.notificacion-institucional');
    }
}
