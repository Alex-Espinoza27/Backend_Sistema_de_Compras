<?php

namespace Tests\Unit;

use App\Support\EnlacePassword;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * El correo con el enlace de contraseña nunca debe impedir un alta: si el
 * SMTP falla, EnlacePassword devuelve false (no lanza) y el mensaje lo avisa.
 */
class EnlacePasswordTest extends TestCase
{
    public function test_smtp_caido_no_lanza_y_devuelve_false(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->andThrow(new \RuntimeException('550 SMTP AUTH is required for message submission on port 465'));
        Log::spy();

        $this->assertFalse(EnlacePassword::enviar('nuevo@example.com'));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_envio_correcto_devuelve_true(): void
    {
        Password::shouldReceive('sendResetLink')->once()->andReturn(Password::RESET_LINK_SENT);

        $this->assertTrue(EnlacePassword::enviar('nuevo@example.com'));
    }

    public function test_mensaje_avisa_cuando_el_correo_no_salio(): void
    {
        $this->assertSame('Usuario creado.', EnlacePassword::mensaje('Usuario creado.', true));
        $this->assertStringStartsWith('Usuario creado, pero no se pudo enviar el correo', EnlacePassword::mensaje('Usuario creado.', false));
    }
}
