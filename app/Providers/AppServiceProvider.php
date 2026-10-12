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
