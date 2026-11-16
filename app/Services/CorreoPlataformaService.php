<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Correos de la plataforma al proveedor: bienvenida al registrarse,
 * constancia de postulación y aviso de ampliación de plazo. Arma el texto y
 * lo encola por NotificacionService (necesita el worker de colas); un fallo
 * al encolar nunca interrumpe la operación que lo originó.
 *
 * La contraseña nunca se incluye en un correo: el proveedor la eligió al
 * registrarse y, si la olvida, usa "recuperar contraseña".
 */
class CorreoPlataformaService
{
    public function __construct(private NotificacionService $notificacion) {}

    public function bienvenidaProveedor(string $correo, string $razonSocial, string $tipoDocumento, string $numeroDocumento): void
    {
        $cuerpo = "Hola {$razonSocial},\n\n"
            ."Tu cuenta de proveedor fue creada correctamente en la plataforma Compras La Perla de la Municipalidad Distrital de La Perla. ¡Te damos la bienvenida!\n\n"
            ."Tus datos de acceso:\n"
            ."- Usuario: {$correo}\n"
            ."- {$tipoDocumento}: {$numeroDocumento}\n"
            ."- Contraseña: la que elegiste al registrarte. Por seguridad no se envía por correo; si la olvidas, usa \"¿Olvidó su contraseña?\" en el inicio de sesión.\n\n"
            .'Ya puedes revisar las convocatorias abiertas y presentar tus ofertas.';

        $this->notificacion->enviarCorreoSinFallar(
            $correo,
            'Bienvenido a Compras La Perla',
            $cuerpo,
            'Ingresar a la plataforma',
            $this->url('/login'),
        );
    }

    public function postulacionRegistrada(
        string $correo,
        string $razonSocial,
        string $numeroConvocatoria,
        string $servicio,
        CarbonInterface $inicio,
        CarbonInterface $fin,
        string $constancia,
    ): void {
        $cuerpo = "Hola {$razonSocial},\n\n"
            ."Registramos tu postulación a la convocatoria {$numeroConvocatoria}, del servicio \"{$servicio}\".\n\n"
            ."- Código de constancia: {$constancia}\n"
            ."- La convocatoria inicia el {$this->fecha($inicio)} y culmina el {$this->fecha($fin)} (hora de Lima).\n\n"
            .'Puedes revisar el detalle y descargar tu constancia desde tus postulaciones en la plataforma.';

        $this->notificacion->enviarCorreoSinFallar(
            $correo,
            "Postulación registrada — Convocatoria {$numeroConvocatoria}",
            $cuerpo,
            'Ver mis postulaciones',
            $this->url('/app/mis-postulaciones'),
        );
    }

    /**
     * @param  iterable<array{correo: string, razon_social: string}>  $destinatarios  postulantes de la convocatoria
     * @return int cantidad de correos encolados
     */
    public function plazoAmpliado(
        iterable $destinatarios,
        string $numeroConvocatoria,
        string $servicio,
        CarbonInterface $finAnterior,
        CarbonInterface $finNuevo,
    ): int {
        $enviados = 0;

        foreach ($destinatarios as $destinatario) {
            $cuerpo = "Hola {$destinatario['razon_social']},\n\n"
                ."Se amplió el plazo de la convocatoria {$numeroConvocatoria}, del servicio \"{$servicio}\", a la que presentaste tu oferta.\n\n"
                ."- Fecha de fin anterior: {$this->fecha($finAnterior)}\n"
                ."- Nueva fecha de fin: {$this->fecha($finNuevo)}\n"
                ."(hora de Lima)\n\n"
                .'Tu postulación se mantiene registrada; no necesitas hacer nada más.';

            $this->notificacion->enviarCorreoSinFallar(
                $destinatario['correo'],
                "Se amplió el plazo de la convocatoria {$numeroConvocatoria}",
                $cuerpo,
                'Ver mis postulaciones',
                $this->url('/app/mis-postulaciones'),
            );
            $enviados++;
        }

        return $enviados;
    }

    private function fecha(CarbonInterface $fecha): string
    {
        return $fecha->copy()->timezone('America/Lima')->format('d/m/Y H:i');
    }

    private function url(string $ruta): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$ruta;
    }
}
