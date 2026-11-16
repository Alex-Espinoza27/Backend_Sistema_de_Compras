<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\RestablecerPasswordNotification;
use App\Services\Captcha\CaptchaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * "Olvidé mi contraseña": enlace de un solo uso que vence a los 10 minutos,
 * sin afectar al enlace de alta de un administrador (60 minutos).
 */
class RecuperarPasswordTest extends TestCase
{
    private const CORREO = 'proveedor@correo.pe';
    private const CLAVE_NUEVA = 'NuevaClave#2026';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('password');
            $t->boolean('activo')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });
        foreach (['password_reset_tokens', 'password_recovery_tokens'] as $tabla) {
            Schema::create($tabla, function ($t) {
                $t->string('email')->primary();
                $t->string('token');
                $t->timestamp('created_at')->nullable();
            });
        }
        DB::table('users')->insert(['id' => 1, 'name' => 'Prov', 'email' => self::CORREO, 'password' => Hash::make('Original#123')]);

        $this->app->bind(CaptchaService::class, fn () => new class implements CaptchaService
        {
            public function verificar(?string $token): bool
            {
                return true;
            }
        });
    }

    /** Pide el enlace por la API y devuelve el token que llegaría en el correo. */
    private function pedirEnlace(): string
    {
        Notification::fake();
        $this->postJson('/api/v1/auth/recuperar-password', ['email' => self::CORREO, 'captcha' => 'x'])->assertOk();

        $token = '';
        Notification::assertSentTo(User::find(1), RestablecerPasswordNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        return $token;
    }

    private function validar(string $token, string $correo = self::CORREO)
    {
        return $this->getJson('/api/v1/auth/restablecer-password/validar?'.http_build_query(['token' => $token, 'email' => $correo]));
    }

    private function restablecer(string $token)
    {
        return $this->postJson('/api/v1/auth/restablecer-password', [
            'token' => $token,
            'email' => self::CORREO,
            'password' => self::CLAVE_NUEVA,
            'password_confirmation' => self::CLAVE_NUEVA,
        ]);
    }

    public function test_la_respuesta_informa_la_vigencia_y_no_revela_si_el_correo_existe(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/recuperar-password', ['email' => 'no-existe@correo.pe', 'captcha' => 'x'])
            ->assertOk()
            ->assertJsonPath('data.vigencia_minutos', 10);

        Notification::assertNothingSent();
    }

    public function test_el_enlace_vale_para_validar_y_expira_a_los_10_minutos(): void
    {
        $token = $this->pedirEnlace();

        $this->validar($token)->assertOk()->assertJsonPath('data.valido', true);
        $segundos = $this->validar($token)->json('data.expira_en_segundos');
        $this->assertGreaterThan(590, $segundos);
        $this->assertLessThanOrEqual(600, $segundos);

        $this->travel(9)->minutes();
        $this->validar($token)->assertJsonPath('data.valido', true);

        $this->travel(2)->minutes();
        $this->validar($token)->assertJsonPath('data.valido', false)->assertJsonPath('data.expira_en_segundos', null);
        $this->restablecer($token)->assertStatus(422);
        $this->assertTrue(Hash::check('Original#123', User::find(1)->password));
    }

    public function test_el_enlace_es_de_un_solo_uso(): void
    {
        $token = $this->pedirEnlace();

        $this->restablecer($token)->assertOk();
        $this->assertTrue(Hash::check(self::CLAVE_NUEVA, User::find(1)->password));

        $this->restablecer($token)->assertStatus(422);
        $this->validar($token)->assertJsonPath('data.valido', false);
    }

    public function test_un_token_distinto_o_de_otro_correo_no_sirve(): void
    {
        $token = $this->pedirEnlace();

        $this->validar('token-inventado')->assertJsonPath('data.valido', false);
        $this->validar($token, 'otro@correo.pe')->assertJsonPath('data.valido', false);
        $this->validar('')->assertJsonPath('data.valido', false);
        $this->restablecer('token-inventado')->assertStatus(422);
    }

    public function test_el_token_se_guarda_cifrado_y_no_en_texto_plano(): void
    {
        $token = $this->pedirEnlace();

        $guardado = DB::table('password_recovery_tokens')->where('email', self::CORREO)->value('token');

        $this->assertNotSame($token, $guardado);
        $this->assertTrue(Hash::check($token, $guardado));
        $this->assertGreaterThanOrEqual(64, strlen($token));
    }

    public function test_el_enlace_de_alta_de_un_administrador_sigue_durando_60_minutos(): void
    {
        $usuario = User::find(1);
        $token = Password::broker('users')->createToken($usuario);

        $this->travel(11)->minutes();
        $this->validar($token)->assertJsonPath('data.valido', true);
        $this->assertLessThan(50 * 60, $this->validar($token)->json('data.expira_en_segundos'));

        $this->travel(50)->minutes();
        $this->validar($token)->assertJsonPath('data.valido', false);
    }

    public function test_un_enlace_de_alta_se_puede_usar_para_definir_la_contrasena(): void
    {
        $token = Password::broker('users')->createToken(User::find(1));

        $this->travel(30)->minutes();
        $this->restablecer($token)->assertOk();
        $this->assertTrue(Hash::check(self::CLAVE_NUEVA, User::find(1)->password));
    }

    public function test_al_cambiar_la_clave_se_anulan_los_demas_enlaces_pendientes(): void
    {
        $tokenAlta = Password::broker('users')->createToken(User::find(1));
        $tokenRecuperacion = $this->pedirEnlace();

        $this->restablecer($tokenRecuperacion)->assertOk();

        $this->validar($tokenAlta)->assertJsonPath('data.valido', false);
    }

    public function test_el_correo_esta_en_espanol_e_indica_la_vigencia_real(): void
    {
        app()->setLocale('es');
        $usuario = User::find(1);

        $recuperacion = (new RestablecerPasswordNotification('tok', 10))->toMail($usuario);
        $alta = (new RestablecerPasswordNotification('tok', 60, solicitudPropia: false))->toMail($usuario);

        $this->assertStringContainsString('vence en 10 minutos', implode(' ', $recuperacion->outroLines));
        $this->assertStringContainsString('vence en 60 minutos', implode(' ', $alta->outroLines));
        $this->assertSame('Restablecer contraseña', $recuperacion->actionText);
        $this->assertSame('Definir mi contraseña', $alta->actionText);

        $html = (string) $recuperacion->render();
        $this->assertStringContainsString('Restablecer contraseña', $html);
        $this->assertStringContainsString('Todos los derechos reservados', $html);
        $this->assertStringNotContainsString('Regards', $html);
        $this->assertStringNotContainsString('All rights reserved', $html);
    }
}
