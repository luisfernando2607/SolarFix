@extends('layouts.app')

@section('title', 'Orden de compra ' . $order->number)

@php
    $field = 'px-2 py-1.5 border border-gray-300 rounded-lg text-base sm:text-sm focus:ring-2 focus:ring-blue-500 outline-none';
    $badge = ['draft' => 'bg-gray-100 text-gray-700', 'sent' => 'bg-blue-100 text-blue-800', 'received' => 'bg-green-100 text-green-800', 'cancelled' => 'bg-red-100 text-red-700'];
@endphp

@section('content')
<div class="p-4 sm:p-6 max-w-5xl mx-auto">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('purchase-orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Órdenes de compra</a>
            <h2 class="text-2xl font-bold text-gray-800 mt-1 flex flex-wrap items-center gap-3">
                {{ $order->number }}
                <span class="inline-flex px-2.5 py-0.5 rounded-full text-sm font-medium {{ $badge[$order->status] }}">{{ $statuses[$order->status] }}</span>
            </h2>
            <p class="text-sm text-gray-500">
                Creada el {{ $order->created_at->format('d/m/Y') }} por {{ $order->createdBy?->name ?? '—' }}{{ \App\Models\Branch::isMultiple() ? ' · ' . $order->branch?->name : '' }}
                @if ($order->sent_at) · enviada el {{ $order->sent_at->format('d/m/Y') }} @endif
                @if ($order->received_at) · recibida el {{ $order->received_at->format('d/m/Y') }} por {{ $order->receivedBy?->name }} @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('purchase-orders.pdf', $order) }}" target="_blank" class="bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium">PDF</a>
            @if ($order->supplier?->phone && $order->items->isNotEmpty())
                <form method="POST" action="{{ route('purchase-orders.whatsapp', $order) }}" target="_blank">
                    @csrf
                    <button type="submit" class="bg-white hover:bg-green-50 text-green-700 border border-green-300 px-4 py-2 rounded-lg text-sm font-medium">WhatsApp al proveedor</button>
                </form>
            @endif
            @if ($order->status === 'draft')
                <form method="POST" action="{{ route('purchase-orders.send', $order) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Marcar como enviada</button>
                </form>
            @endif
            @if ($order->isOpen())
                <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}" onsubmit="return confirm('¿Anular esta orden de compra?')">
                    @csrf @method('PATCH')
                    <button type="submit" class="bg-white hover:bg-red-50 text-red-600 border border-red-200 px-4 py-2 rounded-lg text-sm font-medium">Anular</button>
                </form>
            @endif
        </div>
    </div>

    @if ($order->status === 'draft')
        {{-- Borrador: proveedor, notas, cantidades y costos --}}
        <form method="POST" action="{{ route('purchase-orders.update', $order) }}" class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-1">Proveedor</label>
                    <select id="supplier_id" name="supplier_id" class="{{ $field }} w-full bg-white">
                        <option value="">Sin proveedor</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ (int) $order->supplier_id === $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notas para el proveedor</label>
                    <input type="text" id="notes" name="notes" value="{{ old('notes', $order->notes) }}" maxlength="1000" class="{{ $field }} w-full">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b text-left text-gray-600"><th class="py-2 font-semibold">Repuesto</th><th class="py-2 font-semibold w-28">Cantidad</th><th class="py-2 font-semibold w-32">Costo unit.</th><th class="py-2 font-semibold text-right">Subtotal</th></tr></thead>
                    <tbody>
                        @forelse ($order->items as $item)
                            <tr class="border-b">
                                <td class="py-2">{{ $item->sparePart?->name }} @if ($item->sparePart?->code)<span class="text-xs text-gray-400">· {{ $item->sparePart->code }}</span>@endif</td>
                                <td class="py-2"><input type="number" name="items[{{ $item->id }}][quantity]" value="{{ $item->quantity }}" min="0" aria-label="Cantidad de {{ $item->sparePart?->name }}" class="{{ $field }} w-24"></td>
                                <td class="py-2"><input type="number" name="items[{{ $item->id }}][unit_cost]" value="{{ $item->unit_cost }}" min="0" step="0.01" aria-label="Costo de {{ $item->sparePart?->name }}" class="{{ $field }} w-28"></td>
                                <td class="py-2 text-right">{{ money($item->quantity * $item->unit_cost) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-center text-gray-500">Agrega repuestos abajo.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr><td colspan="3" class="py-3 text-right font-semibold">Total</td><td class="py-3 text-right font-bold">{{ money($order->total()) }}</td></tr></tfoot>
                </table>
            </div>
            <p class="text-xs text-gray-500">Para quitar un repuesto, deja su cantidad en 0.</p>

            <div class="relative flex flex-wrap items-end gap-2 border-t pt-4">
                <div class="flex-1 min-w-56 relative">
                    <label for="poPartSearch" class="block text-sm font-medium text-gray-700 mb-1">Agregar repuesto</label>
                    <input type="hidden" name="add_part_id" id="poPartId">
                    <input type="search" id="poPartSearch" autocomplete="off" placeholder="Nombre o código…" class="{{ $field }} w-full">
                    <div id="poPartResults" class="hidden absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-64 overflow-y-auto"></div>
                </div>
                <div>
                    <label for="add_quantity" class="block text-sm font-medium text-gray-700 mb-1">Cantidad</label>
                    <input type="number" id="add_quantity" name="add_quantity" value="1" min="1" class="{{ $field }} w-24">
                </div>
            </div>

            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Guardar borrador</button>
        </form>
    @endif

    @if ($order->isOpen() && $order->items->isNotEmpty())
        {{-- Recepción: lo que llegó entra al stock --}}
        <form method="POST" action="{{ route('purchase-orders.receive', $order) }}" class="mt-6 bg-white rounded-xl shadow-sm border border-green-200 p-6"
            onsubmit="return confirm('¿Registrar la recepción? Las cantidades se suman al stock.')">
            @csrf
            <h3 class="text-base font-semibold text-gray-800">Recibir mercadería</h3>
            <p class="text-xs text-gray-500 mb-3">Corrige lo que llegó realmente y el costo de la factura del proveedor. Lo recibido se suma al stock{{ \App\Models\Branch::isMultiple() ? ' de ' . $order->branch?->name : '' }} y actualiza el costo promedio.</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b text-left text-gray-600"><th class="py-2 font-semibold">Repuesto</th><th class="py-2 font-semibold text-right">Pedido</th><th class="py-2 font-semibold w-28">Recibido</th><th class="py-2 font-semibold w-32">Costo unit.</th></tr></thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr class="border-b">
                                <td class="py-2">{{ $item->sparePart?->name }}</td>
                                <td class="py-2 text-right">{{ $item->quantity }}</td>
                                <td class="py-2"><input type="number" name="received[{{ $item->id }}][quantity]" value="{{ $item->quantity }}" min="0" aria-label="Recibido de {{ $item->sparePart?->name }}" class="{{ $field }} w-24"></td>
                                <td class="py-2"><input type="number" name="received[{{ $item->id }}][unit_cost]" value="{{ $item->unit_cost }}" min="0" step="0.01" aria-label="Costo de {{ $item->sparePart?->name }}" class="{{ $field }} w-28"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="submit" class="mt-4 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Registrar recepción</button>
        </form>
    @elseif (! $order->isOpen())
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b bg-gray-50/50 text-left text-gray-600"><th class="px-6 py-2 font-semibold">Repuesto</th><th class="px-3 py-2 font-semibold text-right">Pedido</th><th class="px-3 py-2 font-semibold text-right">Recibido</th><th class="px-6 py-2 font-semibold text-right">Costo unit.</th></tr></thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr class="border-b">
                                <td class="px-6 py-2"><a href="{{ route('spare-parts.show', $item->spare_part_id) }}" class="hover:underline">{{ $item->sparePart?->name }}</a></td>
                                <td class="px-3 py-2 text-right">{{ $item->quantity }}</td>
                                <td class="px-3 py-2 text-right {{ $item->received_quantity !== null && $item->received_quantity < $item->quantity ? 'text-amber-700 font-medium' : '' }}">{{ $item->received_quantity ?? '—' }}</td>
                                <td class="px-6 py-2 text-right">{{ money($item->unit_cost) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

@if ($order->status === 'draft')
<script>
(function () {
    var input = document.getElementById('poPartSearch');
    var hidden = document.getElementById('poPartId');
    var results = document.getElementById('poPartResults');
    var url = @json(route('spare-parts.search'));
    var timer;
    input.addEventListener('input', function () {
        hidden.value = '';
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) { results.classList.add('hidden'); return; }
        timer = setTimeout(function () {
            fetch(url + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (items) {
                    results.innerHTML = '';
                    if (!items.length) results.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500">Sin resultados</div>';
                    items.forEach(function (item) {
                        var div = document.createElement('div');
                        div.className = 'px-3 py-2 text-sm cursor-pointer hover:bg-blue-50';
                        div.textContent = item.label;
                        div.addEventListener('mousedown', function (e) {
                            e.preventDefault();
                            hidden.value = item.id;
                            input.value = item.name;
                            results.classList.add('hidden');
                        });
                        results.appendChild(div);
                    });
                    results.classList.remove('hidden');
                });
        }, 250);
    });
    input.addEventListener('blur', function () { setTimeout(function () { results.classList.add('hidden'); }, 200); });
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
})();
</script>
@endif
@endsection
