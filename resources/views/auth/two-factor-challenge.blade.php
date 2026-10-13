<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificación - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-gray-900 to-gray-800 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white">{{ config('app.name') }}</h1>
        </div>
        <div class="bg-white rounded-xl shadow-2xl p-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-2 text-center">Verificación en dos pasos</h2>
            <p class="text-sm text-gray-600 text-center mb-6">Escribe el código de 6 dígitos de tu app de autenticación. Si perdiste el celular, usa un código de respaldo.</p>
            <form method="POST" action="{{ url('/two-factor-challenge') }}">
                @csrf
                <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                <input type="text" id="code" name="code" required autofocus autocomplete="one-time-code" inputmode="numeric" maxlength="20"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg text-center text-2xl tracking-widest focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('code') border-red-500 @enderror">
                @error('code') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                <button type="submit" class="mt-6 w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-4 rounded-lg">Verificar</button>
            </form>
            <a href="{{ route('login') }}" class="block text-center text-sm text-gray-500 hover:text-gray-700 mt-4">Volver al inicio de sesión</a>
        </div>
    </div>
</body>
</html>
