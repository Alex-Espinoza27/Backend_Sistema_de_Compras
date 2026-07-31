<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>{{ $asuntoCorreo }}</title></head>
<body style="font-family: sans-serif; font-size: 14px; color: #222;">
    <p><strong>{{ config('app.name') }}</strong></p>
    <p>{!! nl2br(e($cuerpo)) !!}</p>
    <hr>
    <p style="color: #888; font-size: 12px;">Este es un mensaje automático de la plataforma Compras La Perla. No responda a este correo.</p>
</body>
</html>
