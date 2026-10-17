<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Iniciar sesión') - {{ config('app.name') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#0B1528">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-navy-950 text-white font-sans antialiased relative overflow-x-hidden">
    {{-- Fondo: resplandores de marca y una retícula sutil --}}
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-32 w-[34rem] h-[34rem] rounded-full bg-brand-500/20 blur-3xl"></div>
        <div class="absolute -bottom-48 -left-32 w-[30rem] h-[30rem] rounded-full bg-sun-500/15 blur-3xl"></div>
        <div class="absolute inset-0 opacity-[0.07]" style="background-image: linear-gradient(#8FB4FF 1px, transparent 1px), linear-gradient(90deg, #8FB4FF 1px, transparent 1px); background-size: 44px 44px;"></div>
    </div>

    <div class="relative min-h-screen flex flex-col">
        <main class="flex-1 flex items-center justify-center px-4 py-8 sm:py-12">
            @yield('content')
        </main>
        <footer class="relative text-center text-xs text-slate-500 pb-6 px-4">
            &copy; {{ date('Y') }} {{ config('app.name') }} · Sistema de gestión para talleres de reparación
        </footer>
    </div>
    @stack('scripts')
</body>
</html>
