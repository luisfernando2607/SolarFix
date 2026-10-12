<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function edit()
    {
        return view('auth.password', ['forced' => Auth::user()->must_change_password]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ], [], ['current_password' => 'contraseña actual', 'password' => 'nueva contraseña']);

        $user->forceFill([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        // Las sesiones de otros dispositivos se cierran solas (AuthenticateSession compara la contraseña).
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Contraseña actualizada.');
    }
}
