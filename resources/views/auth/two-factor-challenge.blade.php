@extends('layouts.auth')

@section('title', 'Verificación')

@section('content')
<div class="w-full max-w-md">
    <div class="flex items-center justify-center gap-3 mb-8">
        @include('partials.logo', ['class' => 'w-12 h-12 drop-shadow-[0_8px_24px_rgba(0,163,255,0.35)]', 'id' => 'tfa'])
        <span class="text-3xl font-bold tracking-tight">Solar<span class="text-brand-500">Fix</span></span>
    </div>

    <div class="bg-white text-gray-900 rounded-2xl shadow-2xl shadow-black/40 ring-1 ring-white/10 p-6 sm:p-8">
        <div class="mx-auto mb-4 w-12 h-12 rounded-xl bg-brand-500/10 text-brand-600 flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/></svg>
        </div>
        <h2 class="text-xl font-bold text-navy-900 text-center">Verificación en dos pasos</h2>
        <p class="text-sm text-gray-500 text-center mt-2 mb-6">Escribe el código de 6 dígitos de tu app de autenticación. Si perdiste el celular, usa un código de respaldo.</p>

        <form method="POST" action="{{ url('/two-factor-challenge') }}">
            @csrf
            <label for="code" class="block text-sm font-medium text-gray-700 mb-1.5">Código</label>
            <input type="text" id="code" name="code" required autofocus autocomplete="one-time-code" inputmode="numeric" maxlength="20"
                class="w-full px-4 py-3 rounded-xl border bg-gray-50 text-center text-2xl tracking-[0.4em] font-mono outline-none transition focus:bg-white focus:ring-4 focus:ring-brand-500/20 focus:border-brand-500 {{ $errors->has('code') ? 'border-red-400' : 'border-gray-200' }}">
            @error('code') <p class="text-sm text-red-600 mt-1.5">{{ $message }}</p> @enderror
            <button type="submit" class="mt-6 w-full rounded-xl bg-brand-500 hover:bg-brand-600 active:bg-brand-700 text-white font-semibold py-3 px-4 shadow-lg shadow-brand-500/30 transition focus:outline-none focus:ring-4 focus:ring-brand-500/30">Verificar</button>
        </form>
        <a href="{{ route('login') }}" class="block text-center text-sm text-gray-500 hover:text-gray-700 mt-5">Volver al inicio de sesión</a>
    </div>
</div>
@endsection
