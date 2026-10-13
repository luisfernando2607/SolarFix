@extends('layouts.app')

@section('title', 'Nueva Factura')

@section('content')
<div class="p-4 sm:p-6 max-w-4xl mx-auto">
    <a href="{{ route('invoices.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1 mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Volver a facturas
    </a>

    <h2 class="text-2xl font-bold text-gray-800 mb-6">Nueva Factura</h2>

    @if ($order)
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-blue-800 font-medium">Facturando desde Orden #{{ $order->order_number }}</p>
                <p class="text-xs text-blue-600 mt-0.5">{{ $order->client->name ?? '—' }} — {{ \App\Models\Order::deviceTypes()[$order->device_type] ?? $order->device_type }} {{ $order->brand?->name ?? $order->brand_text ?? '' }}</p>
            </div>
            <a href="{{ route('orders.show', $order) }}" class="text-xs text-blue-600 hover:text-blue-800">Ver orden</a>
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('invoices.store') }}" class="space-y-6">
        @csrf
        @if ($order)
            <input type="hidden" name="order_id" value="{{ $order->id }}">
        @endif

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Cliente</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2 relative">
                    <label for="invoiceClientSearch" class="block text-sm font-medium text-gray-600 mb-1">Buscar cliente existente</label>
                    <input type="hidden" name="client_id" id="invoiceClientId" value="{{ old('client_id', $client?->id) }}">
                    <input type="search" id="invoiceClientSearch" autocomplete="off" placeholder="Nombre, cédula o teléfono…" value="{{ $client?->name }}"
                        class="w-full px-3 py-2 text-base sm:text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    <div id="invoiceClientResults" class="hidden absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-64 overflow-y-auto"></div>
                    <p class="text-xs text-gray-500 mt-1">O escribe los datos de un cliente ocasional abajo.</p>
                </div>
                <div>
                    <label for="inv_client_name" class="block text-sm font-medium text-gray-600 mb-1">Nombre *</label>
                    <input type="text" id="inv_client_name" name="client_name" required value="{{ old('client_name', $client->name ?? '') }}"
                        class="w-full px-3 py-2 text-base sm:text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label for="inv_client_document" class="block text-sm font-medium text-gray-600 mb-1">Documento</label>
                    <input type="text" id="inv_client_document" name="client_document" value="{{ old('client_document', $client->id_document ?? '') }}"
                        class="w-full px-3 py-2 text-base sm:text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label for="inv_client_phone" class="block text-sm font-medium text-gray-600 mb-1">Teléfono</label>
                    <input type="text" id="inv_client_phone" name="client_phone" value="{{ old('client_phone', $client->phone ?? '') }}"
                        class="w-full px-3 py-2 text-base sm:text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label for="inv_client_email" class="block text-sm font-medium text-gray-600 mb-1">Email</label>
                    <input type="email" id="inv_client_email" name="client_email" value="{{ old('client_email', $client->email ?? '') }}"
                        class="w-full px-3 py-2 text-base sm:text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div class="col-span-2">
                    <label for="inv_client_address" class="block text-sm font-medium text-gray-600 mb-1">Dirección</label>
                    <input type="text" id="inv_client_address" name="client_address" value="{{ old('client_address', $client->address ?? '') }}"
                        class="w-full px-3 py-2 text-base sm:text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Dispositivo</h3>
            <div class="grid grid-cols-4 gap-4">
                <div>
                    <label for="inv_device_type" class="block text-sm font-medium text-gray-600 mb-1">Tipo</label>
                    <input type="text" id="inv_device_type" name="device_type" value="{{ old('device_type', $order ? (\App\Models\Order::deviceTypes()[$order->device_type] ?? $order->device_type) : '') }}"
                        class="w-full px-3 py-2 text-base sm:text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label for="inv_device_brand" class="block text-sm font-medium text-gray-600 mb-1">Marca</label>
                    <input type="text" id="inv_device_brand" name="device_brand" value="{{ old('device_brand', $order->brand?->name ?? $order->brand_text ?? '') }}"
                        class="w-full px-3 py-2 text-base sm:text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label for="inv_device_model" class="block text-sm font-medium text-gray-600 mb-1">Modelo</label>
                    <input type="text" id="inv_device_model" name="device_model" value="{{ old('device_model', $order->deviceModel?->name ?? $order->model_text ?? '') }}"
                        class="w-full px-3 py-2 text-base sm:text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label for="inv_device_serial" class="block text-sm font-medium text-gray-600 mb-1">Serial</label>
                    <input type="text" id="inv_device_serial" name="device_serial" value="{{ old('device_serial', $order->serial_imei ?? '') }}"
                        class="w-full px-3 py-2 text-base sm:text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between border-b pb-2 mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Items</h3>
                <button type="button" id="add_item" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Agregar item</button>
            </div>
            <div id="items_container" class="space-y-2">
                @php
                    $defaultItems = [];
                    if ($order) {
                        if ($order->diagnosis_cost > 0) $defaultItems[] = ['Diagnóstico', 1, $order->diagnosis_cost];
                        if ($order->labor_cost > 0) $defaultItems[] = ['Mano de obra', 1, $order->labor_cost];
                        if ($order->parts_cost > 0) $defaultItems[] = ['Repuestos', 1, $order->parts_cost];
                        if (($order->surcharge_amount ?? 0) > 0) $defaultItems[] = ['Recargo (' . $order->surcharge_percent . '%)', 1, $order->surcharge_amount];
                    } else {
                        $defaultItems[] = ['', 1, 0];
                    }
                @endphp
                @foreach ($defaultItems as $i => $item)
                <div class="item-row flex flex-wrap sm:flex-nowrap items-center gap-2">
                    <input type="text" name="items[{{ $i }}][description]" placeholder="Descripción" value="{{ $item[0] }}" required
                        class="basis-full sm:basis-auto flex-1 min-w-0 px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    <input type="number" name="items[{{ $i }}][quantity]" step="0.01" min="0.01" value="{{ $item[1] }}" required
                        class="w-20 px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-center">
                    <input type="number" name="items[{{ $i }}][unit_price]" step="0.01" min="0" value="{{ $item[2] }}" required
                        class="item-price w-28 px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-right">
                    <span class="item-subtotal text-sm font-medium text-gray-700 w-20 text-right">{{ money($item[1] * $item[2]) }}</span>
                    <button type="button" class="remove-item p-1.5 text-red-400 hover:text-red-600 {{ count($defaultItems) <= 1 ? 'hidden' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                @endforeach
            </div>
            <input type="hidden" name="subtotal" id="subtotal_input" value="0">
            <div class="border-t pt-3 mt-3 space-y-1 text-sm">
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal</span>
                    <span id="subtotal_display">{{ money(0) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">IVA</span>
                    <div class="flex items-center gap-1">
                        <input type="number" name="iva_percent" step="0.01" min="0" max="100" value="{{ old('iva_percent', \App\Models\Setting::get('iva_percent', 0)) }}"
                            class="w-16 px-2 py-1 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-right" id="iva_percent">
                        <span>%</span>
                        <span id="iva_amount_display" class="ml-2 w-20 text-right">{{ money(0) }}</span>
                    </div>
                </div>
                <div class="flex justify-between text-base font-bold text-gray-800 border-t pt-2">
                    <span>Total</span>
                    <span id="total_display">{{ money(0) }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="inv_issued_at" class="block text-sm font-medium text-gray-600 mb-1">Fecha de Emisión *</label>
                    <input type="date" id="inv_issued_at" name="issued_at" required value="{{ old('issued_at', date('Y-m-d')) }}"
                        class="w-full px-3 py-2 text-base sm:text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label for="inv_notes" class="block text-sm font-medium text-gray-600 mb-1">Notas</label>
                    <input type="text" id="inv_notes" name="notes" value="{{ old('notes') }}"
                        class="w-full px-3 py-2 text-base sm:text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                Emitir Factura
            </button>
            <a href="{{ route('invoices.index') }}" class="text-sm text-gray-600 hover:text-gray-800">Cancelar</a>
        </div>
    </form>
</div>

<script>
    var currency = @json(\App\Models\Setting::get('currency_symbol', '$'));
document.addEventListener('DOMContentLoaded', function () {
    let itemIndex = {{ count($defaultItems) }};

    function recalc() {
        let subtotal = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('[name$="[quantity]"]').value) || 0;
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            const st = qty * price;
            row.querySelector('.item-subtotal').textContent = currency + st.toFixed(2);
            subtotal += st;
        });
        document.getElementById('subtotal_display').textContent = currency + subtotal.toFixed(2);
        document.getElementById('subtotal_input').value = subtotal.toFixed(2);

        const ivaPct = parseFloat(document.getElementById('iva_percent').value) || 0;
        const ivaAmt = subtotal * ivaPct / 100;
        document.getElementById('iva_amount_display').textContent = currency + ivaAmt.toFixed(2);
        document.getElementById('total_display').textContent = currency + (subtotal + ivaAmt).toFixed(2);
    }

    document.getElementById('add_item').addEventListener('click', function () {
        const container = document.getElementById('items_container');
        const row = document.createElement('div');
        row.className = 'item-row flex flex-wrap sm:flex-nowrap items-center gap-2';
        row.innerHTML = `
            <input type="text" name="items[${itemIndex}][description]" placeholder="Descripción" required
                class="basis-full sm:basis-auto flex-1 min-w-0 px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            <input type="number" name="items[${itemIndex}][quantity]" step="0.01" min="0.01" value="1" required
                class="w-20 px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-center">
            <input type="number" name="items[${itemIndex}][unit_price]" step="0.01" min="0" value="0" required
                class="item-price w-28 px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-right">
            <span class="item-subtotal text-sm font-medium text-gray-700 w-20 text-right">{{ money(0) }}</span>
            <button type="button" class="remove-item p-1.5 text-red-400 hover:text-red-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        `;
        container.appendChild(row);
        row.querySelector('.remove-item').addEventListener('click', function () {
            row.remove();
            recalc();
        });
        row.querySelectorAll('input').forEach(inp => inp.addEventListener('input', recalc));
        itemIndex++;
        recalc();
    });

    document.querySelectorAll('.remove-item').forEach(btn => {
        btn.addEventListener('click', function () {
            this.closest('.item-row').remove();
            recalc();
        });
    });

    document.querySelectorAll('.item-row input').forEach(inp => inp.addEventListener('input', recalc));
    document.getElementById('iva_percent').addEventListener('input', recalc);
    recalc();

    // Cliente existente: búsqueda en el servidor (no se cargan todos los clientes en la página).
    (function () {
        var input = document.getElementById('invoiceClientSearch');
        var hidden = document.getElementById('invoiceClientId');
        var results = document.getElementById('invoiceClientResults');
        var url = @json(route('clients.search'));
        var fields = { name: 'client_name', id_document: 'client_document', phone: 'client_phone', email: 'client_email', address: 'client_address' };
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
                        if (input.value.trim() !== q) return;
                        results.innerHTML = '';
                        if (!items.length) {
                            results.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500">Sin resultados</div>';
                        }
                        items.forEach(function (item) {
                            var div = document.createElement('div');
                            div.className = 'px-3 py-2 text-sm cursor-pointer hover:bg-blue-50';
                            div.textContent = item.label + (item.phone ? ' · ' + item.phone : '');
                            div.addEventListener('mousedown', function (e) {
                                e.preventDefault();
                                input.value = item.name;
                                hidden.value = item.id;
                                Object.keys(fields).forEach(function (key) {
                                    document.querySelector('[name="' + fields[key] + '"]').value = item[key] || '';
                                });
                                results.classList.add('hidden');
                            });
                            results.appendChild(div);
                        });
                        results.classList.remove('hidden');
                    });
            }, 250);
        });
        input.addEventListener('blur', function () { setTimeout(function () { results.classList.add('hidden'); }, 200); });
    })();
});
</script>
@endsection
