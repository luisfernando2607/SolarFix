@extends('layouts.app')

@section('title', 'Verificación en dos pasos')

@php $field = 'w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base sm:text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none'; @endphp

@section('content')
<div class="p-4 sm:p-6 max-w-lg mx-auto">
    <h2 class="text-2xl font-bold text-gray-800 mb-1">Verificación en dos pasos</h2>
    <p class="text-gray-500 mb-6">Además de la contraseña, al iniciar sesión se pide un código de la app del celular (Google Authenticator, Microsoft Authenticator o Authy). Si alguien obtiene tu contraseña, no puede entrar sin tu teléfono.</p>

    @if ($recoveryCodes)
        <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-5">
            <p class="font-semibold text-amber-900">Códigos de respaldo</p>
            <p class="text-sm text-amber-800 mt-1">Guárdalos en un lugar seguro (impresos o en tu gestor de contraseñas). Cada uno sirve una sola vez si pierdes el celular. No se volverán a mostrar.</p>
            <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm">
                @foreach ($recoveryCodes as $code)
                    <li class="bg-white border rounded px-2 py-1 text-center">{{ $code }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border p-6">
        @if ($user->hasTwoFactor())
            <p class="flex items-center gap-2 text-green-700 font-medium">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-green-600"></span>
                Activada desde el {{ $user->two_factor_confirmed_at->format('d/m/Y') }} · {{ count($user->two_factor_recovery_codes ?? []) }} códigos de respaldo disponibles
            </p>

            <form method="POST" action="{{ route('two-factor.recovery') }}" class="mt-6 space-y-2">
                @csrf
                <label for="regen_password" class="block text-sm font-medium text-gray-700">Generar nuevos códigos de respaldo</label>
                <input type="password" id="regen_password" name="current_password" required autocomplete="current-password" placeholder="Tu contraseña actual" class="{{ $field }}">
                <button type="submit" class="bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium">Generar códigos</button>
            </form>

            @unless ($required)
                <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-6 pt-6 border-t space-y-2" onsubmit="return confirm('¿Desactivar la verificación en dos pasos?')">
                    @csrf @method('DELETE')
                    <label for="disable_password" class="block text-sm font-medium text-gray-700">Desactivar</label>
                    <input type="password" id="disable_password" name="current_password" required autocomplete="current-password" placeholder="Tu contraseña actual" class="{{ $field }}">
                    <button type="submit" class="bg-white hover:bg-red-50 text-red-600 border border-red-200 px-4 py-2 rounded-lg text-sm font-medium">Desactivar</button>
                </form>
            @endunless
            @error('current_password') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
        @elseif ($secret)
            <ol class="list-decimal pl-5 space-y-2 text-sm text-gray-700">
                <li>Abre tu app de autenticación y elige <strong>Agregar cuenta → Escanear código QR</strong>.</li>
                <li>Escanea este código (o escribe la clave a mano).</li>
                <li>Escribe el código de 6 dígitos que aparece en la app.</li>
            </ol>
            <img src="{{ $qr }}" alt="Código QR para la app de autenticación" class="mx-auto my-4 w-48 h-48">
            <p class="text-center text-xs text-gray-500">Clave: <span class="font-mono break-all select-all">{{ trim(chunk_split($secret, 4, ' ')) }}</span></p>
            <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4">
                @csrf
                <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Código de la app</label>
                <input type="text" id="code" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="{{ $field }} text-center text-xl tracking-widest">
                @error('code') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                <button type="submit" class="mt-3 w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg font-medium">Activar</button>
            </form>
        @else
            <p class="text-sm text-gray-700">Estado: <strong>desactivada</strong>.</p>
            <form method="POST" action="{{ route('two-factor.start') }}" class="mt-4">
                @csrf
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg font-medium">Configurar con mi celular</button>
            </form>
        @endif
    </div>
</div>
@endsection
