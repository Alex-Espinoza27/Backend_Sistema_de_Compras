<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>{{ $asuntoCorreo }}</title></head>
<body style="font-family: sans-serif; font-size: 14px; color: #222;">
    <p><strong>{{ config('app.name') }}</strong></p>
    <p>{!! nl2br(e($cuerpo)) !!}</p>
    @if ($accionTexto && $accionUrl)
        <p style="margin: 24px 0;">
            <a href="{{ $accionUrl }}" style="display: inline-block; padding: 10px 22px; border-radius: 6px; background: #0f3d6b; color: #ffffff; font-weight: 600; text-decoration: none;">{{ $accionTexto }}</a>
        </p>
        <p style="color: #888; font-size: 12px;">Si el botón no funciona, copie este enlace en su navegador:<br>{{ $accionUrl }}</p>
    @endif
    <hr>
    <p style="color: #888; font-size: 12px;">Este es un mensaje automático de la plataforma Compras La Perla. No responda a este correo.</p>
</body>
</html>
