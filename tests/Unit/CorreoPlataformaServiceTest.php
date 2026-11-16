<?php

namespace Tests\Unit;

use App\Mail\NotificacionInstitucional;
use App\Services\CorreoPlataformaService;
use App\Services\NotificacionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CorreoPlataformaServiceTest extends TestCase
{
    private CorreoPlataformaService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['app.frontend_url' => 'https://compras.test/']);
        $this->servicio = new CorreoPlataformaService(new NotificacionService);
    }

    public function test_bienvenida_al_proveedor_con_usuario_y_sin_contrasena(): void
    {
        $this->servicio->bienvenidaProveedor('prov@correo.pe', 'Servicios SAC', 'RUC', '20123456789');

        Mail::assertQueued(NotificacionInstitucional::class, function (NotificacionInstitucional $m) {
            return $m->hasTo('prov@correo.pe')
                && $m->asuntoCorreo === 'Bienvenido a Compras La Perla'
                && str_contains($m->cuerpo, 'Hola Servicios SAC')
                && str_contains($m->cuerpo, 'Usuario: prov@correo.pe')
                && str_contains($m->cuerpo, 'RUC: 20123456789')
                && str_contains($m->cuerpo, 'no se envía por correo')
                && $m->accionUrl === 'https://compras.test/login';
        });
    }

    public function test_postulacion_registrada_indica_servicio_y_fechas_en_hora_de_lima(): void
    {
        $this->servicio->postulacionRegistrada(
            'prov@correo.pe',
            'Servicios SAC',
            'CONV-2026-0007',
            'Mantenimiento de equipos',
            Carbon::parse('2026-11-20 15:00:00', 'UTC'),
            Carbon::parse('2026-11-25 23:30:00', 'UTC'),
            'OFE-2026-000123',
        );

        Mail::assertQueued(NotificacionInstitucional::class, function (NotificacionInstitucional $m) {
            return $m->hasTo('prov@correo.pe')
                && str_contains($m->asuntoCorreo, 'CONV-2026-0007')
                && str_contains($m->cuerpo, 'convocatoria CONV-2026-0007, del servicio "Mantenimiento de equipos"')
                && str_contains($m->cuerpo, 'OFE-2026-000123')
                && str_contains($m->cuerpo, 'inicia el 20/11/2026 10:00')
                && str_contains($m->cuerpo, 'culmina el 25/11/2026 18:30')
                && $m->accionUrl === 'https://compras.test/app/mis-postulaciones';
        });
    }

    public function test_ampliacion_de_plazo_encola_un_correo_por_postulante(): void
    {
        $enviados = $this->servicio->plazoAmpliado(
            [
                ['correo' => 'a@correo.pe', 'razon_social' => 'Proveedor A'],
                ['correo' => 'b@correo.pe', 'razon_social' => 'Proveedor B'],
            ],
            'CONV-2026-0007',
            'Mantenimiento de equipos',
            Carbon::parse('2026-11-25 23:30:00', 'UTC'),
            Carbon::parse('2026-11-30 23:30:00', 'UTC'),
        );

        $this->assertSame(2, $enviados);
        Mail::assertQueuedCount(2);
        Mail::assertQueued(NotificacionInstitucional::class, fn ($m) => $m->hasTo('a@correo.pe')
            && str_contains($m->cuerpo, 'Hola Proveedor A')
            && str_contains($m->cuerpo, 'Fecha de fin anterior: 25/11/2026 18:30')
            && str_contains($m->cuerpo, 'Nueva fecha de fin: 30/11/2026 18:30'));
        Mail::assertQueued(NotificacionInstitucional::class, fn ($m) => $m->hasTo('b@correo.pe'));
    }

    public function test_sin_postulantes_no_se_envia_nada(): void
    {
        $this->assertSame(0, $this->servicio->plazoAmpliado([], 'C-1', 'Servicio', now(), now()->addDay()));
        Mail::assertNothingQueued();
    }

    public function test_el_correo_dibuja_el_boton_de_accion(): void
    {
        $html = (new NotificacionInstitucional('Asunto', "Línea 1\nLínea 2 <b>", 'Ver mis postulaciones', 'https://compras.test/app/x?a=1&b=2'))->render();

        $this->assertStringContainsString('Ver mis postulaciones', $html);
        $this->assertStringContainsString('href="https://compras.test/app/x?a=1&amp;b=2"', $html);
        $this->assertStringContainsString('Línea 1<br', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function test_sin_accion_el_correo_no_muestra_boton(): void
    {
        $html = (new NotificacionInstitucional('Asunto', 'Texto'))->render();

        $this->assertStringNotContainsString('<a href', $html);
    }

    public function test_un_fallo_al_encolar_no_interrumpe_la_operacion(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('cola caída'));

        (new NotificacionService)->enviarCorreoSinFallar('x@correo.pe', 'Asunto', 'Cuerpo');

        $this->assertTrue(true);
    }
}
