@extends('layouts.app')

@section('title', 'Órdenes')

@section('content')
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Órdenes de Servicio</h2>
            <p class="text-gray-500">Gestión de órdenes de reparación</p>
        </div>
        <a href="{{ route('orders.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nueva Orden
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-6 py-3 border-b bg-gray-50/50 flex flex-wrap items-center gap-2">
            <a href="{{ route('orders.index') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ !$currentStatus ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50' }}">
                Todas
            </a>
            @foreach ($statuses as $key => $label)
                <a href="{{ route('orders.index', ['status' => $key]) }}"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $currentStatus === $key ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @if ($orders->count())
            <div class="overflow-x-auto">
                    <table data-table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider"># Orden</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Cliente</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Dispositivo</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Días</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Pago</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider no-sort">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($orders as $order)
                            @php
                                $days = $order->entry_date ? $order->entry_date->diffInDays(now()) : 0;
                            @endphp
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <span class="font-mono text-sm font-medium text-gray-800">#{{ $order->order_number }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-800">{{ $order->client->name ?? '—' }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <span class="font-medium">{{ $order->brand?->name ?? $order->brand_text }}</span>
                                    {{ $order->deviceModel?->name ?? $order->model_text }}
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ml-1 align-middle
                                        {{ $order->device_type === 'celular' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                        {{ $order->device_type === 'tablet' ? 'bg-orange-100 text-orange-800' : '' }}
                                        {{ $order->device_type === 'pc' ? 'bg-gray-100 text-gray-800' : '' }}
                                        {{ $order->device_type === 'aire_split' ? 'bg-cyan-100 text-cyan-800' : '' }}
                                        {{ $order->device_type === 'aire_central' ? 'bg-cyan-100 text-cyan-800' : '' }}
                                        {{ $order->device_type === 'televisor' ? 'bg-rose-100 text-rose-800' : '' }}
                                        {{ $order->device_type === 'lavador' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                        {{ $order->device_type === 'otro' ? 'bg-slate-100 text-slate-800' : '' }}">
                                        {{ \App\Models\Order::deviceTypes()[$order->device_type] ?? $order->device_type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $order->status === 'received' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $order->status === 'diagnosing' ? 'bg-amber-100 text-amber-800' : '' }}
                                        {{ $order->status === 'waiting_approval' ? 'bg-orange-100 text-orange-800' : '' }}
                                        {{ $order->status === 'repairing' ? 'bg-purple-100 text-purple-800' : '' }}
                                        {{ $order->status === 'ready' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $order->status === 'delivered' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                        {{ $order->status === 'closed_no_repair' ? 'bg-red-100 text-red-800' : '' }}
                                        {{ $order->status === 'warranty' ? 'bg-cyan-100 text-cyan-800' : '' }}">
                                        {{ $statuses[$order->status] ?? $order->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-sm">
                                    @if (!in_array($order->status, ['delivered', 'closed_no_repair']))
                                        <span class="font-mono font-medium {{ $days > 7 ? 'text-red-600' : ($days > 3 ? 'text-amber-600' : 'text-gray-600') }}">
                                            {{ $days }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-gray-800">
                                    ${{ number_format($order->total_amount, 2) }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if ($order->total_amount > 0)
                                        @if ($order->balance_due <= 0)
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-green-600">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                Pagado
                                            </span>
                                        @else
                                            <span class="text-xs font-medium text-red-600">
                                                Debe ${{ number_format($order->balance_due, 2) }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('orders.show', $order) }}" class="text-gray-400 hover:text-gray-600 p-1" title="Ver">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        <a href="{{ route('orders.edit', $order) }}" class="text-blue-400 hover:text-blue-600 p-1" title="Editar">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <form method="POST" action="{{ route('orders.destroy', $order) }}" onsubmit="return confirm('¿Anular esta orden?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-400 hover:text-red-600 p-1" title="Anular">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t bg-gray-50">
                {{ $orders->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <p class="text-gray-500 font-medium">No hay órdenes registradas</p>
                <a href="{{ route('orders.create') }}" class="mt-2 inline-block text-sm text-blue-600 hover:text-blue-800">Crear primera orden</a>
            </div>
        @endif
    </div>
</div>
@endsection
