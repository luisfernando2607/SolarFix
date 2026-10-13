<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) - {{ config('app.name') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#111827">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="SolarFix">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="min-h-screen flex">
        @auth
            <div id="sidebarOverlay" class="fixed inset-0 z-30 bg-black/50 hidden md:hidden"></div>
            <aside id="sidebar" class="fixed md:sticky inset-y-0 left-0 top-0 z-40 h-screen w-64 bg-gray-900 text-white flex flex-col -translate-x-full md:translate-x-0 transition-transform duration-200">
                @php $unreadCount = Auth::user()->unreadNotifications()->count(); @endphp
                <div class="px-6 py-5 border-b border-gray-700 flex items-start justify-between gap-2">
                    <div>
                        <h1 class="text-xl font-bold tracking-tight">{{ config('app.name') }}</h1>
                        <p class="text-xs text-gray-400 mt-0.5">Sistema de Reparaciones</p>
                    </div>
                    <a href="{{ route('notifications.index') }}" class="relative inline-flex items-center text-gray-300 hover:text-white hidden md:inline-flex" aria-label="Notificaciones{{ $unreadCount ? ' (' . $unreadCount . ' sin leer)' : '' }}">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            @if ($unreadCount)
                                <span class="absolute -top-1 -right-1 min-w-5 h-5 px-1 rounded-full bg-red-600 text-white text-[11px] font-bold flex items-center justify-center">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                            @endif
                        </a>
                </div>
                @php $activeBranches = \App\Models\Branch::activeList(); $currentBranch = \App\Support\BranchContext::current(); @endphp
                @if (\App\Support\BranchContext::seesAllBranches() && $activeBranches->count() > 1)
                    <form method="POST" action="{{ route('branch-context') }}" class="px-4 pt-4">
                        @csrf
                        <label for="branchSwitch" class="block text-[11px] uppercase tracking-wider text-gray-500 mb-1">Sucursal</label>
                        <select id="branchSwitch" name="branch_id" onchange="this.form.submit()"
                            class="w-full bg-gray-800 border border-gray-700 text-white text-sm rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="">Todas las sucursales</option>
                            @foreach ($activeBranches as $option)
                                <option value="{{ $option->id }}" {{ $currentBranch === $option->id ? 'selected' : '' }}>{{ $option->name }} ({{ $option->code }})</option>
                            @endforeach
                        </select>
                    </form>
                @elseif ($activeBranches->count() > 1)
                    <p class="px-6 pt-4 text-xs text-gray-400">Sucursal: <span class="text-gray-200">{{ Auth::user()->branch?->name }}</span></p>
                @endif
                <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Dashboard
                    </a>

                    @can('manage-users')
                    <div class="pt-3 pb-1">
                        <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Administración</p>
                    </div>
                    <a href="{{ route('users.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('users.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>
                        Usuarios
                    </a>
                    <a href="{{ route('settings.edit') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('settings.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Configuración
                    </a>
                    <a href="{{ route('system.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('system.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Estado del sistema
                    </a>
                    <a href="{{ route('import.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('import.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Importar datos
                    </a>
                    <a href="{{ route('branches.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('branches.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        Sucursales
                    </a>
                    <a href="{{ route('reports.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('reports.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Reportes
                    </a>
                    <a href="{{ route('audit.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('audit.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                        Auditoría
                    </a>
                    @endcan

                    @can('manage-clients')
                    <div class="pt-3 pb-1">
                        <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Catálogos</p>
                    </div>
                    <a href="{{ route('clients.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('clients.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Clientes
                    </a>
                    @endcan

                    @can('manage-catalogs')
                    <a href="{{ route('brands.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('brands.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Marcas
                    </a>
                    <a href="{{ route('models.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('models.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        Modelos
                    </a>
                    <a href="{{ route('accessories.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('accessories.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        Accesorios
                    </a>
                    <a href="{{ route('checklists.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('checklists.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        Checklists
                    </a>
                    <a href="{{ route('suppliers.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('suppliers.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                        Proveedores
                    </a>
                    @endcan

                    <div class="pt-3 pb-1">
                        <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Operaciones</p>
                    </div>
                    <a href="{{ route('orders.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('orders.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        Órdenes
                    </a>
                    <a href="{{ route('appointments.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('appointments.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Agenda
                    </a>
                    @can('manage-appointments')
                    <a href="{{ route('maintenance-plans.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('maintenance-plans.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Mantenimientos
                    </a>
                    @endcan
                    <a href="{{ route('spare-parts.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('spare-parts.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        Inventario
                    </a>
                    @if (Auth::user()->can('manage-commissions') || Auth::user()->isTechnician())
                    <a href="{{ route('commissions.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('commissions.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Comisiones
                    </a>
                    @endif
                    @can('manage-invoices')
                    <a href="{{ route('invoices.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('invoices.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Facturas
                    </a>
                    <a href="{{ route('cash.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('cash.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Caja del día
                    </a>
                    <a href="{{ route('sales.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('sales.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Ventas
                    </a>
                    <a href="{{ route('warranties.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs('warranties.*') ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Garantías
                    </a>
                    @endcan
                </nav>
                <div class="px-4 py-4 border-t border-gray-700">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-gray-600 flex items-center justify-center text-xs font-bold uppercase">
                            {{ substr(Auth::user()->name, 0, 2) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate">{{ Auth::user()->name }}</p>
                            <p class="text-xs text-gray-400 truncate capitalize">{{ Auth::user()->role }}</p>
                        </div>
                    </div>
                    <a href="{{ route('password.edit') }}" class="mt-3 flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-300 hover:text-white hover:bg-gray-800 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                        Cambiar contraseña
                    </a>
                    <a href="{{ route('two-factor.show') }}" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-300 hover:text-white hover:bg-gray-800 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        Verificación en dos pasos
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-300 hover:text-white hover:bg-gray-800 rounded-lg">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Cerrar Sesión
                        </button>
                    </form>
                </div>
            </aside>
        @endauth

        <div class="flex-1 flex flex-col min-w-0">
            @auth
                <header class="sticky top-0 z-20 bg-white shadow-sm border-b md:hidden">
                    <div class="px-4 py-3 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <button type="button" id="sidebarToggle" class="p-1 -ml-1 text-gray-700" aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                            </button>
                            <h1 class="text-lg font-bold">{{ config('app.name') }}</h1>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('notifications.index') }}" class="relative inline-flex items-center text-gray-600" aria-label="Notificaciones{{ $unreadCount ? ' (' . $unreadCount . ' sin leer)' : '' }}">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            @if ($unreadCount)
                                <span class="absolute -top-1 -right-1 min-w-5 h-5 px-1 rounded-full bg-red-600 text-white text-[11px] font-bold flex items-center justify-center">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                            @endif
                        </a>
                            <span class="text-sm text-gray-600 hidden sm:inline">{{ Auth::user()->name }}</span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="text-sm text-red-600 hover:text-red-800">Salir</button>
                            </form>
                        </div>
                    </div>
                    <div class="px-4 pb-3">
                        <form method="GET" action="{{ route('search') }}" role="search" class="relative">
                        <input type="search" name="q" id="searchMobile" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="Buscar orden, cliente, teléfono, cédula o IMEI…" autocomplete="off"
                            class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </form>
                    </div>
                </header>
                <div class="hidden md:block sticky top-0 z-20 bg-white border-b px-6 py-2.5">
                    <form method="GET" action="{{ route('search') }}" role="search" class="relative max-w-xl">
                        <input type="search" name="q" id="searchDesktop" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="Buscar orden, cliente, teléfono, cédula o IMEI…" autocomplete="off"
                            class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </form>
                </div>
            @endauth

            <main class="flex-1">
                @yield('content')
            </main>
        </div>
    </div>

    @if (session('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Operación exitosa',
                text: @json(session('success')),
                timer: 3000,
                showConfirmButton: true,
                confirmButtonColor: '#2563eb',
            });
        </script>
    @endif

    @if (session('error'))
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: @json(session('error')),
                confirmButtonColor: '#dc2626',
            });
        </script>
    @endif

    @if (session('info'))
        <script>
            Swal.fire({
                icon: 'info',
                title: 'Información',
                text: @json(session('info')),
                confirmButtonColor: '#2563eb',
            });
        </script>
    @endif
    <script>
        // App instalable (PWA): el service worker solo muestra un aviso sin conexión.
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
        }

        // Atajo: "/" enfoca el buscador (fuera de campos de texto).
        document.addEventListener('keydown', function (e) {
            var tag = (e.target.tagName || '').toLowerCase();
            if (e.key === '/' && !['input', 'textarea', 'select'].includes(tag) && !e.target.isContentEditable) {
                var box = document.getElementById(window.innerWidth >= 768 ? 'searchDesktop' : 'searchMobile');
                if (box) { e.preventDefault(); box.focus(); }
            }
        });

        // Menú lateral deslizable en pantallas pequeñas.
        (function () {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            var toggle = document.getElementById('sidebarToggle');
            if (!sidebar || !toggle) return;

            function setOpen(open) {
                sidebar.classList.toggle('-translate-x-full', !open);
                overlay.classList.toggle('hidden', !open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            toggle.addEventListener('click', function () { setOpen(true); });
            overlay.addEventListener('click', function () { setOpen(false); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
            sidebar.querySelectorAll('nav a').forEach(function (link) {
                link.addEventListener('click', function () { setOpen(false); });
            });
        })();

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-table]').forEach(function (table) {
                var wrapper = table.closest('.overflow-x-auto') || table.parentElement;
                var container = wrapper.closest('.bg-white') || wrapper;

                var filterDiv = document.createElement('div');
                filterDiv.className = 'px-6 py-3 border-b bg-gray-50/50 flex items-center gap-2';
                filterDiv.innerHTML = '<svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>' +
                    '<input type="text" placeholder="Filtrar por cualquier campo..." class="w-full max-w-xs px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">';
                container.insertBefore(filterDiv, wrapper);

                var filterInput = filterDiv.querySelector('input');
                var tbody = table.querySelector('tbody');
                var rows = tbody ? Array.from(tbody.querySelectorAll('tr')) : [];

                if (table.dataset.search === 'server') {
                    // Búsqueda en el servidor: recorre todos los registros, no solo la página visible.
                    var url = new URL(window.location.href);
                    filterInput.placeholder = table.dataset.searchPlaceholder || 'Buscar...';
                    filterInput.value = url.searchParams.get('q') || '';
                    if (filterInput.value) {
                        filterInput.focus();
                        filterInput.setSelectionRange(filterInput.value.length, filterInput.value.length);
                    }
                    var timer;
                    filterInput.addEventListener('input', function () {
                        clearTimeout(timer);
                        var value = this.value.trim();
                        timer = setTimeout(function () {
                            if (value === (url.searchParams.get('q') || '')) return;
                            value ? url.searchParams.set('q', value) : url.searchParams.delete('q');
                            url.searchParams.delete('page');
                            window.location.assign(url.toString());
                        }, 400);
                    });
                } else {
                    filterInput.addEventListener('input', function () {
                        var q = this.value.toLowerCase();
                        rows.forEach(function (row) {
                            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
                        });
                    });
                }

                var headers = table.querySelectorAll('thead th');
                headers.forEach(function (th, index) {
                    if (th.classList.contains('no-sort')) return;
                    th.style.cursor = 'pointer';
                    th.classList.add('select-none');
                    th.addEventListener('click', function () {
                        var isAsc = this.classList.contains('sort-asc');
                        headers.forEach(function (h) { h.classList.remove('sort-asc', 'sort-desc'); });
                        this.classList.add(isAsc ? 'sort-desc' : 'sort-asc');

                        var sorted = Array.from(rows).sort(function (a, b) {
                            var aText = (a.cells[index]?.textContent || '').trim();
                            var bText = (b.cells[index]?.textContent || '').trim();
                            return isAsc ? bText.localeCompare(aText, 'es') : aText.localeCompare(bText, 'es');
                        });

                        tbody.append.apply(tbody, sorted);
                    });
                });
            });
        });
    </script>
    <style>
        th.sort-asc::after { content: ' \\25B2'; font-size: 10px; opacity: 0.7; }
        th.sort-desc::after { content: ' \\25BC'; font-size: 10px; opacity: 0.7; }
    </style>
</body>
</html>
