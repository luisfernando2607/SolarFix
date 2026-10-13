<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Support\QrCode;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Verificación en dos pasos: activar con la app del celular, ingresar con el código y
 * códigos de respaldo por si se pierde el teléfono.
 */
class TwoFactorController extends Controller
{
    private const PENDING_SECRET = 'two_factor.pending_secret';
    private const CHALLENGE = 'two_factor.login';

    public function show(Request $request)
    {
        $user = Auth::user();
        $secret = $user->hasTwoFactor() ? null : $request->session()->get(self::PENDING_SECRET);

        return view('auth.two-factor', [
            'user' => $user,
            'secret' => $secret,
            'qr' => $secret ? QrCode::dataUri(Totp::uri($secret, $user->email, Setting::get('company_name', 'SolarFix')), 5) : null,
            'recoveryCodes' => $request->session()->get('two_factor.codes'),
            'required' => self::requiredFor($user),
        ]);
    }

    public function start(Request $request)
    {
        $request->session()->put(self::PENDING_SECRET, Totp::generateSecret());

        return redirect()->route('two-factor.show');
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => ['required', 'string']], [], ['code' => 'código']);
        $secret = $request->session()->get(self::PENDING_SECRET);
        $step = $secret ? Totp::verify($secret, $request->code) : null;
        if (! $step) {
            return back()->withErrors(['code' => 'El código no es correcto. Revisa la hora del celular y vuelve a intentar.']);
        }

        $codes = User::newRecoveryCodes();
        Auth::user()->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $step,
        ])->save();
        $request->session()->forget(self::PENDING_SECRET);

        return redirect()->route('two-factor.show')->with('two_factor.codes', $codes)
            ->with('success', 'Verificación en dos pasos activada. Guarda los códigos de respaldo.');
    }

    public function regenerate(Request $request)
    {
        $request->validate(['current_password' => ['required', 'current_password']], [], ['current_password' => 'contraseña actual']);
        $codes = User::newRecoveryCodes();
        Auth::user()->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return redirect()->route('two-factor.show')->with('two_factor.codes', $codes)->with('success', 'Nuevos códigos de respaldo generados; los anteriores ya no sirven.');
    }

    public function disable(Request $request)
    {
        $request->validate(['current_password' => ['required', 'current_password']], [], ['current_password' => 'contraseña actual']);
        if (self::requiredFor(Auth::user())) {
            return back()->with('error', 'La configuración exige la verificación en dos pasos para administradores.');
        }

        Auth::user()->forceFill([
            'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null,
        ])->save();

        return redirect()->route('two-factor.show')->with('success', 'Verificación en dos pasos desactivada.');
    }

    // --- Ingreso ---

    /**
     * Después de la contraseña correcta, guarda quién está entrando y pide el código.
     */
    public static function beginChallenge(Request $request, User $user, bool $remember)
    {
        $request->session()->regenerate();
        $request->session()->put(self::CHALLENGE, ['id' => $user->id, 'remember' => $remember, 'at' => now()->timestamp]);

        return redirect()->route('two-factor.challenge');
    }

    public function challenge(Request $request)
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'El tiempo para ingresar el código venció. Vuelve a iniciar sesión.']);
        }

        $request->validate(['code' => ['required', 'string', 'max:20']], [], ['code' => 'código']);
        if (! $user->verifyTwoFactor($request->code)) {
            return back()->withErrors(['code' => 'Código incorrecto.']);
        }

        $remember = (bool) $request->session()->get(self::CHALLENGE . '.remember');
        $request->session()->forget(self::CHALLENGE);
        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Usuario a mitad del ingreso (contraseña ya validada), por 10 minutos.
     */
    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::CHALLENGE);
        if (! $pending || now()->timestamp - $pending['at'] > 600) {
            return null;
        }

        return User::where('is_active', true)->find($pending['id']);
    }

    public static function requiredFor(User $user): bool
    {
        return ($user->isSuperAdmin() || $user->isAdmin()) && Setting::get('require_admin_2fa', '0') === '1';
    }
}
