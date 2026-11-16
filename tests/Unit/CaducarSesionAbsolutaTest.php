<?php

namespace Tests\Unit;

use App\Http\Middleware\CaducarSesionAbsoluta;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CaducarSesionAbsolutaTest extends TestCase
{
    private function request(?int $loginAt): Request
    {
        $sesion = new Store('test', new ArraySessionHandler(120));
        $sesion->start();
        if ($loginAt !== null) {
            $sesion->put('login_at', $loginAt);
        }

        $request = Request::create('/api/v1/auth/me');
        $request->setLaravelSession($sesion);

        return $request;
    }

    private function guard(bool $autenticado, int $vecesLogout): void
    {
        $guard = Mockery::mock();
        $guard->shouldReceive('check')->andReturn($autenticado);
        $guard->shouldReceive('logout')->times($vecesLogout);
        Auth::shouldReceive('guard')->with('web')->andReturn($guard);
    }

    private function pasar(Request $request): void
    {
        (new CaducarSesionAbsoluta)->handle($request, fn () => new Response);
    }

    public function test_sesion_vigente_no_se_toca(): void
    {
        config(['session.absolute_lifetime' => 1440]);
        $this->guard(true, 0);
        $request = $this->request(now()->subHours(23)->timestamp);

        $this->pasar($request);

        $this->assertNotNull($request->session()->get('login_at'));
    }

    public function test_sesion_pasado_el_tope_se_invalida(): void
    {
        config(['session.absolute_lifetime' => 1440]);
        $this->guard(true, 1);
        $request = $this->request(now()->subHours(25)->timestamp);

        $this->pasar($request);

        $this->assertNull($request->session()->get('login_at'));
    }

    public function test_sesion_previa_sin_login_at_empieza_a_contar(): void
    {
        config(['session.absolute_lifetime' => 1440]);
        $this->guard(true, 0);
        $request = $this->request(null);

        $this->pasar($request);

        $this->assertEqualsWithDelta(now()->timestamp, $request->session()->get('login_at'), 2);
    }

    public function test_usuario_anonimo_no_se_toca(): void
    {
        config(['session.absolute_lifetime' => 1440]);
        $this->guard(false, 0);
        $request = $this->request(null);

        $this->pasar($request);

        $this->assertNull($request->session()->get('login_at'));
    }

    public function test_tope_en_cero_lo_desactiva(): void
    {
        config(['session.absolute_lifetime' => 0]);
        Auth::shouldReceive('guard')->never();
        $request = $this->request(now()->subYears(1)->timestamp);

        $this->pasar($request);

        $this->assertNotNull($request->session()->get('login_at'));
    }

    public function test_request_sin_sesion_pasa(): void
    {
        config(['session.absolute_lifetime' => 1440]);
        Auth::shouldReceive('guard')->never();

        $respuesta = (new CaducarSesionAbsoluta)->handle(Request::create('/api/v1/publico/x'), fn () => new Response('ok'));

        $this->assertSame('ok', $respuesta->getContent());
    }
}
