@extends('layouts.app')

@section('title', 'Cambiar contraseña')

@section('content')
<div class="p-4 sm:p-6 max-w-lg mx-auto">
    <h2 class="text-2xl font-bold text-gray-800 mb-1">Cambiar contraseña</h2>
    @if ($forced)
        <p class="mb-6 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
            Tu contraseña fue asignada por otra persona. Elige una nueva para continuar.
        </p>
    @else
        <p class="mb-6 text-gray-500">Al cambiarla se cierran tus sesiones en otros dispositivos.</p>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Contraseña actual</label>
            <input type="password" id="current_password" name="current_password" required autocomplete="current-password"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('current_password') border-red-500 @enderror">
            @error('current_password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Nueva contraseña</label>
            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('password') border-red-500 @enderror">
            <p class="text-xs text-gray-500 mt-1">Mínimo 8 caracteres.</p>
            @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Repite la nueva contraseña</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
        </div>

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg font-medium">Guardar contraseña</button>
    </form>
</div>
@endsection
