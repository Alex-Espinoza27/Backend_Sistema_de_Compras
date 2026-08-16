<?php

namespace App\Providers;

use App\Models\Configuracion;
use App\Models\User;
use App\Services\Captcha\CaptchaInternoService;
use App\Services\Captcha\CaptchaRecaptchaService;
use App\Services\Captcha\CaptchaService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CaptchaService::class, function () {
            return match (config('services.captcha.driver')) {
                'recaptcha' => new CaptchaRecaptchaService(config('services.captcha.recaptcha_secret')),
                default => new CaptchaInternoService(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // El ADMINISTRADOR puede todo (02A §5).
        Gate::before(fn ($user) => $user->hasRole('ADMINISTRADOR') ? true : null);

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('publico', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Throttle general para toda la API autenticada (02C Fase 11: rate limits finos).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Más estricto para exportaciones/reportes pesados (Excel/PDF).
        RateLimiter::for('exportar', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip());
        });

        // El SPA (no Laravel) resuelve la pantalla de restablecer contraseña.
        ResetPassword::createUrlUsing(function (User $user, string $token) {
            $frontendUrl = rtrim(config('app.frontend_url'), '/');

            return "{$frontendUrl}/restablecer-password?token={$token}&email=".urlencode($user->email);
        });

        $this->aplicarConfiguracionCorreo();
    }

    /**
     * Credenciales SMTP configurables desde Configuración (05C hardening,
     * pedido explícitamente por el usuario) en vez de solo por .env: si hay
     * un SMTP_HOST guardado en CONFIGURACION, sobreescribe en caliente los
     * mailers de Laravel para esta request. Si está vacío, no se toca nada
     * y sigue rigiendo lo que ya haya en .env (ej. Mailpit en desarrollo).
     * Se protege con try/catch + hasTable porque boot() corre también
     * cuando la tabla todavía no existe (antes de migrar).
     */
    private function aplicarConfiguracionCorreo(): void
    {
        try {
            if (! Schema::hasTable('CONFIGURACION')) {
                return;
            }

            $valores = Configuracion::whereIn('CONF_CLAVE', [
                'SMTP_HOST', 'SMTP_PUERTO', 'SMTP_USUARIO', 'SMTP_PASSWORD', 'SMTP_CIFRADO',
                'CORREO_REMITENTE', 'CORREO_REMITENTE_NOMBRE',
            ])->pluck('CONF_VALOR', 'CONF_CLAVE');

            $host = trim((string) ($valores['SMTP_HOST'] ?? ''));

            if ($host === '') {
                return;
            }

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => (int) ($valores['SMTP_PUERTO'] ?? 587),
                'mail.mailers.smtp.username' => $valores['SMTP_USUARIO'] ?: null,
                'mail.mailers.smtp.password' => filled($valores['SMTP_PASSWORD'] ?? null) ? Crypt::decryptString($valores['SMTP_PASSWORD']) : null,
                'mail.mailers.smtp.encryption' => $valores['SMTP_CIFRADO'] ?: null,
            ]);

            if (filled($valores['CORREO_REMITENTE'] ?? null)) {
                config(['mail.from.address' => $valores['CORREO_REMITENTE']]);
            }
            if (filled($valores['CORREO_REMITENTE_NOMBRE'] ?? null)) {
                config(['mail.from.name' => $valores['CORREO_REMITENTE_NOMBRE']]);
            }
        } catch (\Throwable) {
            // Nunca debe romper el arranque de la app por un problema de
            // config de correo (DB caída, valor cifrado corrupto, etc.).
        }
    }
}
