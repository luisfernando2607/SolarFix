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
                    <p class="text-3xl font-bold text-emerald-600 mt-1">${{ number_format($stats['revenue'], 2) }}</p>
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
                        <td class="px-6 py-3">${{ number_format($order->total_amount, 2) }}</td>
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
