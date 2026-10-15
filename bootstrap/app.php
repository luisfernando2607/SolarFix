<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Webhooks de servicios externos: sin sesión, cookies ni CSRF (se validan con su firma).
        then: function () {
            \Illuminate\Support\Facades\Route::middleware(\App\Http\Middleware\SecurityHeaders::class)
                ->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
        // Cambiar la contraseña cierra las demás sesiones; usuarios desactivados quedan fuera.
        $middleware->web(append: [
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\EnsureAccountIsUsable::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
        // Detrás de un proxy (Nginx, Cloudflare, el panel del hosting) para detectar HTTPS e IP real.
        // TRUSTED_PROXIES: "*" o la lista de IPs del proxy.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
