@extends('layouts.app')

@section('title', 'Nueva venta')

@php $field = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-base sm:text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white'; @endphp

@section('content')
<div class="p-4 sm:p-6 max-w-3xl mx-auto">
    <a href="{{ route('sales.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Ventas</a>
    <h2 class="text-2xl font-bold text-gray-800 mt-1 mb-6">Nueva venta de mostrador</h2>

    <form method="POST" action="{{ route('sales.store') }}" id="saleForm" class="space-y-6">
        @csrf

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-3">Productos</h3>
            <div class="relative">
                <label for="partSearch" class="block text-sm font-medium text-gray-700 mb-1">Buscar repuesto o accesorio</label>
                <input type="search" id="partSearch" autocomplete="off" placeholder="Nombre o código…" class="{{ $field }}">
                <div id="partResults" class="hidden absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-72 overflow-y-auto"></div>
            </div>
            @error('items') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror

            <div class="overflow-x-auto mt-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="py-2 font-semibold text-gray-600">Producto</th>
                            <th class="py-2 font-semibold text-gray-600 w-24">Cant.</th>
                            <th class="py-2 font-semibold text-gray-600 text-right">Precio</th>
                            <th class="py-2 font-semibold text-gray-600 text-right">Subtotal</th>
                            <th class="py-2 w-8"><span class="sr-only">Quitar</span></th>
                        </tr>
                    </thead>
                    <tbody id="saleItems"></tbody>
                    <tfoot>
                        <tr><td colspan="3" class="py-3 text-right font-semibold">Total</td><td class="py-3 text-right text-xl font-bold" id="saleTotal">{{ money(0) }}</td><td></td></tr>
                    </tfoot>
                </table>
            </div>
            <p id="emptyItems" class="text-sm text-gray-500 py-4 text-center">Busca un producto para agregarlo.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2 relative">
                <label for="clientSearch" class="block text-sm font-medium text-gray-700 mb-1">Cliente (opcional)</label>
                <input type="hidden" name="client_id" id="clientId" value="{{ old('client_id') }}">
                <input type="hidden" id="clientLabel">
                <input type="text" name="customer_name" id="clientSearch" value="{{ old('customer_name') }}" autocomplete="off" maxlength="150"
                    placeholder="Busca un cliente o escribe un nombre" class="{{ $field }}">
                <div id="clientResults" class="hidden absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-64 overflow-y-auto"></div>
            </div>
            <div>
                <label for="method" class="block text-sm font-medium text-gray-700 mb-1">Método de pago</label>
                <select id="method" name="method" required class="{{ $field }}">
                    @foreach ($methods as $key => $label)
                        <option value="{{ $key }}" {{ old('method', 'cash') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="reference" class="block text-sm font-medium text-gray-700 mb-1">Referencia (opcional)</label>
                <input type="text" id="reference" name="reference" value="{{ old('reference') }}" maxlength="100" placeholder="N° de transferencia, voucher…" class="{{ $field }}">
            </div>
            <div class="sm:col-span-2">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notas (opcional)</label>
                <input type="text" id="notes" name="notes" value="{{ old('notes') }}" maxlength="255" class="{{ $field }}">
            </div>
        </div>

        <button type="submit" id="saleSubmit" disabled
            class="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-300 text-white px-4 py-3 rounded-xl font-semibold">Cobrar y registrar venta</button>
    </form>
</div>

<script>
(function () {
    var searchUrl = @json(route('spare-parts.search'));
    var currency = @json(\App\Models\Setting::get('currency_symbol', '$'));
    var input = document.getElementById('partSearch');
    var results = document.getElementById('partResults');
    var body = document.getElementById('saleItems');
    var rows = {};
    var timer;

    function fmt(n) { return currency + n.toFixed(2); }

    function recalc() {
        var total = 0, count = 0;
        Object.keys(rows).forEach(function (id) {
            var row = rows[id];
            var qty = Math.max(1, Math.min(row.stock, parseInt(row.qty.value, 10) || 1));
            var subtotal = Math.round(row.price * qty * 100) / 100;
            row.subtotal.textContent = fmt(subtotal);
            total += subtotal;
            count++;
        });
        document.getElementById('saleTotal').textContent = fmt(Math.round(total * 100) / 100);
        document.getElementById('emptyItems').classList.toggle('hidden', count > 0);
        document.getElementById('saleSubmit').disabled = count === 0;
    }

    function addItem(item) {
        if (rows[item.id]) {
            rows[item.id].qty.value = Math.min(item.stock, (parseInt(rows[item.id].qty.value, 10) || 0) + 1);
            recalc();
            return;
        }
        var index = Object.keys(rows).length + Date.now();
        var tr = document.createElement('tr');
        tr.className = 'border-b';
        tr.innerHTML = '<td class="py-2 pr-2"></td>' +
            '<td class="py-2"><input type="hidden" name="items[' + index + '][spare_part_id]">' +
            '<input type="number" name="items[' + index + '][quantity]" min="1" value="1" class="w-20 px-2 py-1.5 border rounded-lg text-base sm:text-sm" aria-label="Cantidad"></td>' +
            '<td class="py-2 text-right"></td><td class="py-2 text-right font-medium"></td>' +
            '<td class="py-2 text-right"><button type="button" class="text-red-500 hover:text-red-700 px-2" aria-label="Quitar">✕</button></td>';
        tr.cells[0].textContent = item.name;
        tr.cells[0].insertAdjacentHTML('beforeend', '<span class="block text-xs text-gray-400">stock ' + item.stock + '</span>');
        tr.querySelector('input[type=hidden]').value = item.id;
        var qty = tr.querySelector('input[type=number]');
        qty.max = item.stock;
        tr.cells[2].textContent = fmt(item.sale_price);
        rows[item.id] = { price: item.sale_price, stock: item.stock, qty: qty, subtotal: tr.cells[3] };
        qty.addEventListener('input', recalc);
        tr.querySelector('button').addEventListener('click', function () { delete rows[item.id]; tr.remove(); recalc(); });
        body.appendChild(tr);
        recalc();
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) { results.classList.add('hidden'); return; }
        timer = setTimeout(function () {
            fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (items) {
                    if (input.value.trim() !== q) return;
                    results.innerHTML = '';
                    if (!items.length) results.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500">Sin resultados</div>';
                    items.forEach(function (item) {
                        var div = document.createElement('div');
                        div.className = 'px-3 py-2 text-sm ' + (item.stock > 0 ? 'cursor-pointer hover:bg-blue-50' : 'text-gray-400');
                        div.textContent = item.label + (item.stock > 0 ? '' : ' (sin stock)');
                        if (item.stock > 0) {
                            div.addEventListener('mousedown', function (e) {
                                e.preventDefault();
                                addItem(item);
                                input.value = '';
                                results.classList.add('hidden');
                            });
                        }
                        results.appendChild(div);
                    });
                    results.classList.remove('hidden');
                });
        }, 250);
    });
    input.addEventListener('blur', function () { setTimeout(function () { results.classList.add('hidden'); }, 200); });
    // Enter en el buscador no envía el formulario.
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
})();
</script>
@include('partials.client-search')
@endsection
