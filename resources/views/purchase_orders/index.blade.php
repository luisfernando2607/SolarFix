@extends('layouts.app')

@section('title', 'Órdenes de compra')

@php $badge = ['draft' => 'bg-gray-100 text-gray-700', 'sent' => 'bg-blue-100 text-blue-800', 'received' => 'bg-green-100 text-green-800', 'cancelled' => 'bg-red-100 text-red-700']; @endphp

@section('content')
<div class="p-4 sm:p-6">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('spare-parts.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Inventario</a>
            <h2 class="text-2xl font-bold text-gray-800 mt-1">Órdenes de compra</h2>
            <p class="text-gray-500 text-sm">Pedidos a proveedores. Al recibirlos, el stock y el costo se actualizan solos.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" class="flex items-center gap-2">
                <label for="status" class="sr-only">Estado</label>
                <select id="status" name="status" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-lg text-base sm:text-sm bg-white">
                    <option value="">Todas</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('spare-parts.reorder') }}" class="bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium">Compras sugeridas</a>
            <form method="POST" action="{{ route('purchase-orders.store') }}">
                @csrf
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Nueva orden</button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        @if ($orders->isEmpty())
            <p class="px-6 py-10 text-center text-gray-500 text-sm">Todavía no hay órdenes de compra. Créalas desde <a href="{{ route('spare-parts.reorder') }}" class="text-blue-600 hover:underline">Compras sugeridas</a>.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50/50 text-left text-gray-600">
                            <th class="px-6 py-3 font-semibold">Orden</th>
                            <th class="px-3 py-3 font-semibold">Proveedor</th>
                            <th class="px-3 py-3 font-semibold">Estado</th>
                            <th class="px-3 py-3 font-semibold text-right">Repuestos</th>
                            <th class="px-6 py-3 font-semibold text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr class="border-b">
                                <td class="px-6 py-3">
                                    <a href="{{ route('purchase-orders.show', $order) }}" class="text-blue-600 hover:underline font-medium">{{ $order->number }}</a>
                                    <span class="block text-xs text-gray-400">{{ $order->created_at->format('d/m/Y') }}{{ \App\Models\Branch::isMultiple() ? ' · ' . $order->branch?->name : '' }}</span>
                                </td>
                                <td class="px-3 py-3">{{ $order->supplier?->name ?? 'Sin proveedor' }}</td>
                                <td class="px-3 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $badge[$order->status] }}">{{ $statuses[$order->status] }}</span></td>
                                <td class="px-3 py-3 text-right">{{ $order->items->count() }}</td>
                                <td class="px-6 py-3 text-right font-medium">{{ money($order->total()) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-3">{{ $orders->links() }}</div>
        @endif
    </div>
</div>
@endsection
