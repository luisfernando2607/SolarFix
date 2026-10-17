@extends('layouts.auth')

@section('title', 'Iniciar sesión')

@section('content')
@php
    $businessName = \App\Models\Setting::get('company_name', config('app.name'));
    $showBusiness = $businessName && $businessName !== config('app.name');
    $lockoutSeconds = $lockoutSeconds ?? null;
@endphp
<div class="w-full max-w-5xl grid lg:grid-cols-[1.1fr_1fr] gap-8 lg:gap-12 items-center">

    {{-- Panel de marca --}}
    <section class="text-center lg:text-left">
        <div class="flex items-center justify-center lg:justify-start gap-4">
            @include('partials.logo', ['class' => 'w-16 h-16 sm:w-20 sm:h-20 drop-shadow-[0_10px_30px_rgba(0,163,255,0.35)]', 'id' => 'login'])
            <div>
                <h1 class="text-4xl sm:text-5xl font-bold tracking-tight leading-none">Solar<span class="text-brand-500">Fix</span></h1>
                <p class="text-xs sm:text-sm uppercase tracking-[0.2em] text-slate-400 mt-2">Gestión de talleres</p>
            </div>
        </div>

        <p class="mt-6 text-lg text-slate-300 max-w-md mx-auto lg:mx-0">
            Recepción, diagnóstico, reparación y entrega en un solo flujo trazable. Sin cuadernos ni chats perdidos.
        </p>

        <ul class="hidden sm:grid grid-cols-1 gap-3 mt-8 max-w-md mx-auto lg:mx-0 text-left">
            <li class="flex items-start gap-3">
                <span class="mt-0.5 flex-none w-8 h-8 rounded-lg bg-brand-500/15 text-brand-400 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M7 4h7l5 5v11a1 1 0 01-1 1H7a1 1 0 01-1-1V5a1 1 0 011-1z"/></svg>
                </span>
                <div><p class="font-medium text-white">Órdenes de servicio completas</p><p class="text-sm text-slate-400">Equipo, accesorios, fotos, PIN cifrado y firma del cliente.</p></div>
            </li>
            <li class="flex items-start gap-3">
                <span class="mt-0.5 flex-none w-8 h-8 rounded-lg bg-sun-500/15 text-sun-400 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </span>
                <div><p class="font-medium text-white">Presupuestos aprobados en línea</p><p class="text-sm text-slate-400">PDF con QR, WhatsApp y correo automáticos al cambiar de estado.</p></div>
            </li>
            <li class="flex items-start gap-3">
                <span class="mt-0.5 flex-none w-8 h-8 rounded-lg bg-emerald-500/15 text-emerald-400 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </span>
                <div><p class="font-medium text-white">Caja, garantías e inventario</p><p class="text-sm text-slate-400">Saldos pendientes, repuestos y comisiones bajo control.</p></div>
            </li>
        </ul>

        <div class="hidden sm:flex flex-wrap justify-center lg:justify-start gap-2 mt-8">
            @foreach (['Celulares', 'Tablets', 'PCs', 'Aires acondicionados', 'Televisores', 'Electrodomésticos'] as $device)
                <span class="px-3 py-1 rounded-full text-xs font-medium bg-white/5 border border-white/10 text-slate-300">{{ $device }}</span>
            @endforeach
        </div>
    </section>

    {{-- Formulario --}}
    <section class="w-full max-w-md mx-auto lg:mx-0 lg:justify-self-end">
        <div class="bg-white text-gray-900 rounded-2xl shadow-2xl shadow-black/40 ring-1 ring-white/10 p-6 sm:p-8">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-navy-900">Bienvenido</h2>
                <p class="text-sm text-gray-500 mt-1">
                    @if ($showBusiness)
                        Ingresa a <span class="font-medium text-gray-700">{{ $businessName }}</span> con tu cuenta.
                    @else
                        Ingresa con tu correo y contraseña para continuar.
                    @endif
                </p>
            </div>

            @if ($lockoutSeconds)
                <div role="alert" class="mb-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <svg class="w-5 h-5 flex-none mt-0.5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    <p>Demasiados intentos. Por seguridad, espera <strong>{{ $lockoutSeconds }} segundos</strong> antes de volver a intentarlo.</p>
                </div>
            @elseif ($errors->has('email') && ! $errors->has('password'))
                <div role="alert" class="mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <svg class="w-5 h-5 flex-none mt-0.5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p>{{ $errors->first('email') }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Correo electrónico</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </span>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" inputmode="email" spellcheck="false"
                            class="w-full pl-11 pr-4 py-3 rounded-xl border bg-gray-50 text-gray-900 placeholder-gray-400 outline-none transition focus:bg-white focus:ring-4 focus:ring-brand-500/20 focus:border-brand-500 {{ $errors->has('email') ? 'border-red-400' : 'border-gray-200' }}"
                            placeholder="tu@correo.com">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                    </div>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </span>
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                            class="w-full pl-11 pr-12 py-3 rounded-xl border bg-gray-50 text-gray-900 placeholder-gray-400 outline-none transition focus:bg-white focus:ring-4 focus:ring-brand-500/20 focus:border-brand-500 {{ $errors->has('password') ? 'border-red-400' : 'border-gray-200' }}"
                            placeholder="••••••••">
                        <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-gray-600" aria-label="Mostrar contraseña" aria-pressed="false">
                            <svg id="eyeOpen" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg id="eyeClosed" class="w-5 h-5 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="text-sm text-red-600 mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between">
                    <label for="remember" class="inline-flex items-center gap-2 text-sm text-gray-600 select-none cursor-pointer">
                        <input type="checkbox" id="remember" name="remember" class="w-4 h-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        Mantener sesión iniciada
                    </label>
                </div>

                <button type="submit" {{ $lockoutSeconds ? 'disabled' : '' }}
                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:bg-brand-700 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold py-3 px-4 shadow-lg shadow-brand-500/30 transition focus:outline-none focus:ring-4 focus:ring-brand-500/30">
                    Ingresar
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-gray-500">
                ¿Olvidaste tu contraseña? Pídele a la persona administradora del taller que te asigne una nueva.
            </p>
        </div>

        @if (Route::has('booking.create') && \App\Models\Setting::get('online_booking', '1') === '1')
            <p class="mt-5 text-center text-sm text-slate-400">
                ¿Eres cliente? <a href="{{ route('booking.create') }}" class="text-brand-400 hover:text-brand-300 font-medium underline-offset-4 hover:underline">Agenda una cita en línea</a>
            </p>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var button = document.getElementById('togglePassword');
        var input = document.getElementById('password');
        if (!button || !input) return;
        button.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            document.getElementById('eyeOpen').classList.toggle('hidden', show);
            document.getElementById('eyeClosed').classList.toggle('hidden', !show);
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    })();
</script>
@endpush
