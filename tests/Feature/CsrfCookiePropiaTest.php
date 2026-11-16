<?php

namespace Tests\Feature;

use App\Http\Middleware\ValidarCsrfCompras;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * La cookie CSRF de Compras no se llama `XSRF-TOKEN` (la comparte con otras
 * apps Laravel del mismo servidor y se pisan) y el POST sigue exigiendo el
 * token de SU sesión.
 */
class CsrfCookiePropiaTest extends TestCase
{
    private array $cookies = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.driver' => 'cookie',
            'sanctum.stateful' => ['localhost'],
        ]);

        // En pruebas Laravel se salta la verificación CSRF; aquí se quiere real.
        $this->app->bind(ValidarCsrfCompras::class, fn ($app) => new class($app, $app['encrypter']) extends ValidarCsrfCompras
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        Route::middleware('api')->prefix('api')->post('t-accion', fn () => response()->json(['ok' => true]));
    }

    private function llamar(string $metodo, string $uri, array $cabeceras = []): TestResponse
    {
        $cliente = $this->withUnencryptedCookies($this->cookies)
            ->withHeaders($cabeceras + ['Origin' => 'http://localhost', 'Referer' => 'http://localhost/x', 'Accept' => 'application/json']);
        $respuesta = $metodo === 'POST' ? $cliente->post($uri) : $cliente->get($uri);

        foreach ($respuesta->headers->getCookies() as $c) {
            $this->cookies[$c->getName()] = $c->getValue();
        }

        return $respuesta;
    }

    public function test_csrf_cookie_entrega_la_cookie_propia_y_no_xsrf_token(): void
    {
        $this->llamar('GET', '/sanctum/csrf-cookie')->assertNoContent();

        $this->assertArrayHasKey('XSRF-TOKEN-COMPRAS', $this->cookies);
        $this->assertArrayNotHasKey('XSRF-TOKEN', $this->cookies);
    }

    public function test_post_sin_token_es_419_y_con_el_token_de_su_cookie_pasa(): void
    {
        $this->llamar('GET', '/sanctum/csrf-cookie');

        $this->llamar('POST', '/api/t-accion')->assertStatus(419);

        $this->llamar('POST', '/api/t-accion', ['X-XSRF-TOKEN' => $this->cookies['XSRF-TOKEN-COMPRAS']])
            ->assertOk();
    }

    public function test_una_cookie_xsrf_token_de_otra_app_ya_no_afecta(): void
    {
        $this->llamar('GET', '/sanctum/csrf-cookie');
        $propio = $this->cookies['XSRF-TOKEN-COMPRAS'];

        // Otra app Laravel del mismo servidor escribe SU `XSRF-TOKEN` (otra APP_KEY).
        $this->cookies['XSRF-TOKEN'] = 'eyJpdiI6ImFqZW5vIiwidmFsdWUiOiJ4In0=';

        $this->llamar('POST', '/api/t-accion', ['X-XSRF-TOKEN' => $propio])->assertOk();
    }
}
