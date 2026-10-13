<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de usuarios desactivados y obliga a cambiar la contraseña
 * asignada por otro antes de usar la app.
 */
class EnsureAccountIsUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (! $user) {
            return $next($request);
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Tu usuario está desactivado.']);
        }

        if ($user->must_change_password && ! $request->routeIs('password.edit', 'password.update', 'logout')) {
            return redirect()->route('password.edit')->with('info', 'Por seguridad, cambia tu contraseña antes de continuar.');
        }

        // La configuración puede exigir la verificación en dos pasos a los administradores.
        if (! $user->hasTwoFactor() && \App\Http\Controllers\TwoFactorController::requiredFor($user)
            && ! $request->routeIs('two-factor.*', 'password.*', 'logout')) {
            return redirect()->route('two-factor.show')->with('info', 'Activa la verificación en dos pasos para continuar.');
        }

        return $next($request);
    }
}
