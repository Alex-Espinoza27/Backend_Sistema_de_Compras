<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Recorre el stack HTTP real (Sanctum stateful + StartSession + auth:sanctum)
 * con el driver `cookie` de producción, adelantando el reloj.
 */
class SesionAbsolutaHttpTest extends TestCase
{
    private array $cookies = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.driver' => 'cookie',
            'session.lifetime' => 1440,
            'session.absolute_lifetime' => 2,
            'sanctum.stateful' => ['localhost'],
        ]);

        Schema::create('users', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('password');
            $t->boolean('activo')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });
        DB::table('users')->insert(['id' => 1, 'name' => 'T', 'email' => 't@t.pe', 'password' => Hash::make('x')]);

        Route::middleware('api')->prefix('api')->group(function () {
            Route::post('t-login', function (\Illuminate\Http\Request $r) {
                Auth::login(User::find(1));
                $r->session()->regenerate();
                $r->session()->put('login_at', now()->timestamp);

                return response()->json(['ok' => true]);
            });
            Route::get('t-me', fn (\Illuminate\Http\Request $r) => response()->json(['id' => $r->user()?->id]))
                ->middleware('auth:sanctum');
        });
    }

    private function llamar(string $metodo, string $uri): TestResponse
    {
        // En producción cada petición es un proceso nuevo. Aquí la app se
        // reutiliza: `auth:sanctum` deja `sanctum` como guard por defecto y
        // los guards conservarían al usuario de la petición anterior.
        $this->app['auth']->forgetGuards();
        config(['auth.defaults.guard' => 'web']);

        $cliente = $this->withUnencryptedCookies($this->cookies)
            ->withHeaders(['Origin' => 'http://localhost', 'Referer' => 'http://localhost/x', 'Accept' => 'application/json']);
        $respuesta = $metodo === 'POST' ? $cliente->post($uri) : $cliente->get($uri);

        foreach ($respuesta->headers->getCookies() as $c) {
            $c->getValue() === null || $c->isCleared()
                ? $this->cookies = array_diff_key($this->cookies, [$c->getName() => 1])
                : $this->cookies[$c->getName()] = $c->getValue();
        }

        return $respuesta;
    }

    public function test_dentro_del_tope_sigue_autenticado_y_pasado_el_tope_responde_401(): void
    {
        $this->llamar('POST', '/api/t-login')->assertOk();

        $this->travel(1)->minutes();
        $this->llamar('GET', '/api/t-me')->assertOk()->assertJson(['id' => 1]);

        $this->travel(3)->minutes();
        $this->llamar('GET', '/api/t-me')->assertStatus(401);
    }
}
