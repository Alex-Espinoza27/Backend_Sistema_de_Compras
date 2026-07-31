<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Captcha\CaptchaInternoService;
use App\Services\Captcha\CaptchaRecaptchaService;
use App\Services\Captcha\CaptchaService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
    }
}
