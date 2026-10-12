@extends('layouts.app')

@section('title', 'Buscar')

@section('content')
<div class="p-4 sm:p-6 max-w-4xl mx-auto">
    <h2 class="text-2xl font-bold text-gray-800 mb-1">Resultados</h2>
    <p class="text-gray-500 mb-6">
        @if (mb_strlen($q) < 2)
            Escribe al menos 2 caracteres: número de orden, nombre, teléfono, cédula o IMEI.
        @else
            Para «{{ $q }}»: {{ $orders->count() }} {{ $orders->count() === 1 ? 'orden' : 'órdenes' }}{{ $clients->count() ? ', ' . $clients->count() . ' ' . ($clients->count() === 1 ? 'cliente' : 'clientes') : '' }}.
        @endif
    </p>

    @if ($orders->count())
        <div class="bg-white rounded-xl shadow-sm border mb-6">
            <h3 class="px-5 py-3 border-b text-sm font-semibold text-gray-800">Órdenes</h3>
            <ul class="divide-y">
                @foreach ($orders as $order)
                    @php $closed = in_array($order->status, ['delivered', 'closed_no_repair']); @endphp
                    <li>
                        <a href="{{ route('orders.show', $order) }}" class="px-5 py-3 flex flex-wrap items-center justify-between gap-2 hover:bg-gray-50 {{ $closed ? 'opacity-70' : '' }}">
                            <span class="min-w-0">
                                <span class="font-medium text-blue-700">{{ $order->order_number }}</span>
                                <span class="text-sm text-gray-700">· {{ $order->client?->name }}</span>
                                <span class="block text-xs text-gray-500 truncate">
                                    {{ \App\Models\Order::deviceTypes()[$order->device_type] ?? $order->device_type }}
                                    {{ $order->brand?->name ?? $order->brand_text }} {{ $order->deviceModel?->name ?? $order->model_text }}
                                    @if ($order->serial_imei) · {{ $order->serial_imei }} @endif
                                    · {{ $order->entry_date?->format('d/m/Y') }}
                                </span>
                            </span>
                            <span class="flex items-center gap-2 text-xs">
                                @if ($order->balance_due > 0 && ! $closed)
                                    <span class="text-red-600 font-medium">Saldo {{ money($order->balance_due) }}</span>
                                @endif
                                <span class="px-2 py-0.5 rounded-full {{ $order->status === 'ready' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $statuses[$order->status] ?? $order->status }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($clients->count())
        <div class="bg-white rounded-xl shadow-sm border">
            <h3 class="px-5 py-3 border-b text-sm font-semibold text-gray-800">Clientes</h3>
            <ul class="divide-y">
                @foreach ($clients as $client)
                    <li>
                        <a href="{{ route('clients.show', $client) }}" class="px-5 py-3 flex justify-between gap-2 hover:bg-gray-50 text-sm">
                            <span><span class="font-medium text-gray-800">{{ $client->name }}</span> <span class="text-gray-500">· {{ $client->phone }}{{ $client->id_document ? ' · ' . $client->id_document : '' }}</span></span>
                            <span class="text-xs text-gray-500">{{ $client->orders_count }} {{ $client->orders_count === 1 ? 'orden' : 'órdenes' }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (mb_strlen($q) >= 2 && ! $orders->count() && ! $clients->count())
        <div class="bg-white rounded-xl shadow-sm border p-8 text-center text-sm text-gray-500">No se encontró nada.</div>
    @endif
</div>
@endsection
