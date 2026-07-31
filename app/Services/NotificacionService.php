<?php

namespace App\Services;

use App\Mail\NotificacionInstitucional;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Notificaciones de dominio (02A §7.6): NOTIFICACION in-app (requiere un
 * usuario existente) + correo institucional por colas (para destinatarios
 * sin cuenta todavía, ej. contacto de una entidad en solicitud).
 */
class NotificacionService
{
    public function crearInApp(
        int $userId,
        string $tipo,
        string $titulo,
        string $mensaje,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
    ): Notificacion {
        return Notificacion::create([
            'NOTI_USER_ID' => $userId,
            'NOTI_TIPO' => $tipo,
            'NOTI_TITULO' => $titulo,
            'NOTI_MENSAJE' => $mensaje,
            'NOTI_LEIDA' => false,
            'NOTI_REFERENCIA_TIPO' => $referenciaTipo,
            'NOTI_REFERENCIA_ID' => $referenciaId,
        ]);
    }

    public function enviarCorreo(string $destinatario, string $asunto, string $cuerpo): void
    {
        Mail::to($destinatario)->queue(new NotificacionInstitucional($asunto, $cuerpo));
    }

    public function notificarAdministradores(string $asunto, string $cuerpo): void
    {
        User::role('ADMINISTRADOR')->pluck('email')->each(
            fn (string $correo) => $this->enviarCorreo($correo, $asunto, $cuerpo),
        );
    }
}
