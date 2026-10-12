@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="p-6">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Dashboard</h2>
        <p class="text-gray-500">Bienvenido, {{ Auth::user()->name }}</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Órdenes Totales</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1">{{ $stats['orders_total'] }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
            </div>
            <a href="{{ route('orders.index') }}" class="mt-4 inline-block text-sm text-blue-600 hover:text-blue-800 font-medium">Ver órdenes →</a>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">En Proceso</p>
                    <p class="text-3xl font-bold text-amber-600 mt-1">{{ $stats['orders_pending'] }}</p>
                </div>
                <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Listas para Entregar</p>
                    <p class="text-3xl font-bold text-green-600 mt-1">{{ $stats['orders_ready'] }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Entregadas</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1">{{ $stats['orders_delivered'] }}</p>
                </div>
                <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <p class="text-sm font-medium text-gray-500">Recibidas hoy</p>
            <p class="text-3xl font-bold text-blue-600 mt-1">{{ $stats['orders_today'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <p class="text-sm font-medium text-gray-500">Entregadas hoy</p>
            <p class="text-3xl font-bold text-gray-800 mt-1">{{ $stats['delivered_today'] }}</p>
        </div>
        @can('manage-invoices')
        <div class="bg-white rounded-xl shadow-sm border p-6 flex flex-col justify-between">
            <p class="text-sm font-medium text-gray-500">Caja del día</p>
            <a href="{{ route('cash.index') }}" class="mt-2 inline-block text-sm text-blue-600 hover:text-blue-800 font-medium">Ver ingresos y cerrar caja →</a>
        </div>
        @endcan
    </div>

    @if ($staleOrders->count() || $awaitingApproval->count())
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        @if ($awaitingApproval->count())
        <div class="bg-white rounded-xl shadow-sm border border-orange-200">
            <div class="px-6 py-4 border-b bg-orange-50">
                <h3 class="text-base font-semibold text-orange-800">Presupuestos sin respuesta (más de {{ $approvalHours }} h)</h3>
                <p class="text-xs text-orange-700 mt-0.5">Contactar por teléfono al cliente.</p>
            </div>
            <ul class="divide-y">
                @foreach ($awaitingApproval as $order)
                <li class="px-6 py-3 flex items-center justify-between text-sm">
                    <div>
                        <a href="{{ route('orders.show', $order) }}" class="text-blue-600 hover:underline font-medium">{{ $order->order_number }}</a>
                        <span class="text-gray-600">· {{ $order->client?->name ?? '—' }}</span>
                        @if ($order->client?->phone)
                            <span class="text-gray-400">· {{ $order->client->phone }}</span>
                        @endif
                    </div>
                    <span class="text-xs text-orange-700 whitespace-nowrap">{{ $order->waiting_since->diffForHumans() }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        @if ($staleOrders->count())
        <div class="bg-white rounded-xl shadow-sm border border-red-200">
            <div class="px-6 py-4 border-b bg-red-50">
                <h3 class="text-base font-semibold text-red-800">Equipos sin movimiento (más de {{ $staleDays }} días)</h3>
                <p class="text-xs text-red-700 mt-0.5">Posible demora o abandono.</p>
            </div>
            <ul class="divide-y">
                @foreach ($staleOrders as $order)
                <li class="px-6 py-3 flex items-center justify-between text-sm">
                    <div>
                        <a href="{{ route('orders.show', $order) }}" class="text-blue-600 hover:underline font-medium">{{ $order->order_number }}</a>
                        <span class="text-gray-600">· {{ $order->client?->name ?? '—' }}</span>
                        <span class="text-gray-400">· {{ $statuses[$order->status] ?? $order->status }}</span>
                    </div>
                    <span class="text-xs text-red-700 whitespace-nowrap">{{ $order->updated_at->diffForHumans() }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
    @endif

    @if ($duePlans)
    <a href="{{ route('maintenance-plans.index') }}" class="mt-6 block bg-white rounded-xl shadow-sm border border-amber-200 px-6 py-4 hover:bg-amber-50">
        <span class="text-base font-semibold text-amber-800">{{ $duePlans }} {{ $duePlans === 1 ? 'mantenimiento preventivo vencido o por vencer' : 'mantenimientos preventivos vencidos o por vencer' }}</span>
        <span class="block text-xs text-amber-700 mt-0.5">Avisa a los clientes y agenda las visitas →</span>
    </a>
    @endif

    @if ($todayAppointments->count() || $unassignedAppointments)
    <div class="mt-6 bg-white rounded-xl shadow-sm border border-blue-200">
        <div class="px-6 py-4 border-b bg-blue-50 flex items-center justify-between">
            <h3 class="text-base font-semibold text-blue-900">Citas de hoy ({{ $todayAppointments->count() }})</h3>
            <a href="{{ route('appointments.index') }}" class="text-sm text-blue-800 hover:underline font-medium">Ver agenda →</a>
        </div>
        @if ($unassignedAppointments)
            <p class="px-6 py-2 text-sm text-red-700 bg-red-50 border-b">{{ $unassignedAppointments }} citas próximas sin técnico asignado.</p>
        @endif
        <ul class="divide-y">
            @foreach ($todayAppointments as $appointment)
            <li>
                <a href="{{ route('appointments.show', $appointment) }}" class="px-6 py-3 flex flex-wrap items-center justify-between gap-2 text-sm hover:bg-gray-50">
                    <span><span class="font-semibold">{{ $appointment->scheduled_at->format('H:i') }}</span> · {{ $appointment->title }} · {{ $appointment->client?->name }}</span>
                    <span class="text-xs text-gray-500">{{ $appointment->address ?? '' }}{{ $appointment->user ? ' · ' . $appointment->user->name : '' }}</span>
                </a>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @if ($lowStockParts->count())
    <div class="mt-6 bg-white rounded-xl shadow-sm border border-red-200">
        <div class="px-6 py-4 border-b bg-red-50 flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold text-red-800">Repuestos con stock bajo</h3>
                <p class="text-xs text-red-700 mt-0.5">En o por debajo del stock mínimo.</p>
            </div>
            <a href="{{ route('spare-parts.index', ['low' => 1]) }}" class="text-sm text-red-800 hover:underline font-medium">Ver todos →</a>
        </div>
        <ul class="divide-y">
            @foreach ($lowStockParts as $part)
            <li class="px-6 py-3 flex items-center justify-between text-sm">
                <a href="{{ route('spare-parts.show', $part) }}" class="text-blue-600 hover:underline font-medium">{{ $part->name }}</a>
                <span class="text-xs text-red-700 whitespace-nowrap">stock {{ $part->stock_quantity }} / mín. {{ $part->min_stock }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @if ($expiringWarranties->count())
    <div class="mt-6 bg-white rounded-xl shadow-sm border border-amber-200">
        <div class="px-6 py-4 border-b bg-amber-50 flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold text-amber-800">Garantías por vencer (próximos {{ $warrantyAlertDays }} días)</h3>
                <p class="text-xs text-amber-700 mt-0.5">Revisa si estos clientes tuvieron problemas o regresos.</p>
            </div>
            <a href="{{ route('warranties.index', ['status' => 'expiring']) }}" class="text-sm text-amber-800 hover:underline font-medium">Ver todas →</a>
        </div>
        <ul class="divide-y">
            @foreach ($expiringWarranties as $warranty)
            <li class="px-6 py-3 flex items-center justify-between text-sm">
                <div>
                    <a href="{{ route('orders.show', $warranty->order_id) }}" class="text-blue-600 hover:underline font-medium">{{ $warranty->order?->order_number }}</a>
                    <span class="text-gray-600">· {{ $warranty->order?->client?->name ?? '—' }}</span>
                    @if ($warranty->order?->client?->phone)
                        <span class="text-gray-400">· {{ $warranty->order->client->phone }}</span>
                    @endif
                </div>
                <span class="text-xs text-amber-700 whitespace-nowrap">vence {{ $warranty->end_date->format('d/m/Y') }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @isset($stats['users_count'])
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Usuarios del Sistema</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1">{{ $stats['users_count'] }}</p>
                </div>
                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>
                </div>
            </div>
            <a href="{{ route('users.index') }}" class="mt-4 inline-block text-sm text-indigo-600 hover:text-indigo-800 font-medium">Gestionar usuarios →</a>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Ingresos Totales</p>
                    <p class="text-3xl font-bold text-emerald-600 mt-1">{{ money($stats['revenue']) }}</p>
                </div>
                <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
    </div>
    @endisset

    @if($recentOrders->count())
    <div class="mt-6 bg-white rounded-xl shadow-sm border">
        <div class="px-6 py-4 border-b bg-gray-50/50">
            <h3 class="text-lg font-semibold text-gray-800">Órdenes Recientes</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50/50 text-left">
                        <th class="px-6 py-3 font-semibold text-gray-600">#</th>
                        <th class="px-6 py-3 font-semibold text-gray-600">Cliente</th>
                        <th class="px-6 py-3 font-semibold text-gray-600">Dispositivo</th>
                        <th class="px-6 py-3 font-semibold text-gray-600">Estado</th>
                        <th class="px-6 py-3 font-semibold text-gray-600">Total</th>
                        <th class="px-6 py-3 font-semibold text-gray-600">Creada</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentOrders as $order)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-6 py-3">
                            <a href="{{ route('orders.show', $order) }}" class="text-blue-600 hover:underline font-medium">{{ $order->order_number }}</a>
                        </td>
                        <td class="px-6 py-3">{{ $order->client?->name ?? '—' }}</td>
                        <td class="px-6 py-3">{{ $order->brand?->name ?? $order->brand_text ?: '—' }}</td>
                        <td class="px-6 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $order->status === 'received' ? 'bg-blue-100 text-blue-800' : '' }}
                                {{ $order->status === 'diagnosing' ? 'bg-purple-100 text-purple-800' : '' }}
                                {{ $order->status === 'waiting_approval' ? 'bg-orange-100 text-orange-800' : '' }}
                                {{ $order->status === 'repairing' ? 'bg-amber-100 text-amber-800' : '' }}
                                {{ $order->status === 'ready' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $order->status === 'delivered' ? 'bg-gray-100 text-gray-800' : '' }}
                                {{ $order->status === 'closed_no_repair' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $order->status === 'warranty' ? 'bg-teal-100 text-teal-800' : '' }}">
                                {{ $statuses[$order->status] ?? $order->status }}
                            </span>
                        </td>
                        <td class="px-6 py-3">{{ money($order->total_amount) }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ $order->created_at->format('d/m/Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endSection
