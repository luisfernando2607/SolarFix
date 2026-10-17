<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ingreso: 5 intentos por minuto por cuenta (correo + IP) y 30 por IP. Así un taller donde
        // todos salen a internet con la misma IP no se bloquea al empezar el día, y adivinar
        // contraseñas sigue limitado.
        // Al superar el límite se muestra el propio login con el aviso (y el código 429, no una página en blanco).
        $loginLockout = fn (\Illuminate\Http\Request $request, array $headers) => response()
            ->view('auth.login', ['lockoutSeconds' => (int) ($headers['Retry-After'] ?? 60)], 429)
            ->withHeaders($headers);
        \Illuminate\Support\Facades\RateLimiter::for('login', fn (\Illuminate\Http\Request $request) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')) . '|' . $request->ip())->response($loginLockout),
            \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by($request->ip())->response($loginLockout),
        ]);
        // Código de verificación en dos pasos: 5 intentos por minuto por sesión.
        \Illuminate\Support\Facades\RateLimiter::for('two-factor', fn (\Illuminate\Http\Request $request) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->session()->getId()),
            \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by($request->ip()),
        ]);

        // Con APP_URL en https, los enlaces (QR, WhatsApp, correos) se generan siempre con https.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Accesos al sistema en la auditoría (los fallidos ayudan a detectar intentos de acceso).
        Event::listen(Login::class, fn (Login $e) => AuditLog::record('login', userId: $e->user->getAuthIdentifier()));
        Event::listen(Logout::class, fn (Logout $e) => $e->user && AuditLog::record('logout', userId: $e->user->getAuthIdentifier()));
        Event::listen(Failed::class, function (Failed $e) {
            $email = (string) ($e->credentials['email'] ?? '');
            AuditLog::record(
                'login_failed',
                description: 'Intento con ' . mb_substr($email, 0, 180),
                userId: $e->user?->getAuthIdentifier() ?? User::where('email', $email)->value('id'),
            );
        });
    }
}
