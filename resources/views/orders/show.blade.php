@extends('layouts.app')

@section('title', 'Orden #' . $order->order_number)

@section('content')
@php
    $days = $order->entry_date ? (int) $order->entry_date->diffInDays(today()) : 0;
@endphp
<div class="p-4 sm:p-6 max-w-5xl mx-auto">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('orders.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Volver a órdenes
            </a>
            <div class="flex items-center gap-3 mt-2">
                <h2 class="text-2xl font-bold text-gray-800">Orden #{{ $order->order_number }}</h2>
                @if (\App\Models\Branch::count() > 1 && $order->branch)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">{{ $order->branch->name }}</span>
                @endif
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
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('orders.pdf', $order) }}" class="bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                PDF
            </a>
            <a href="{{ route('orders.ticket', $order) }}" target="_blank" class="bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium transition">
                Ticket
            </a>
            <a href="{{ route('orders.edit', $order) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                Editar
            </a>
        </div>
    </div>

    @if ($order->parentOrder)
        <div class="mb-6 rounded-xl border border-cyan-200 bg-cyan-50 px-5 py-3 text-sm text-cyan-900">
            <strong>Orden de garantía</strong> de la orden
            <a href="{{ route('orders.show', $order->parentOrder) }}" class="font-medium underline">#{{ $order->parentOrder->order_number }}</a>.
            @php $coverage = $order->warrantyCoverage(); @endphp
            Cobertura: <strong>{{ \App\Models\Warranty::types()[$coverage] ?? $coverage }}</strong>.
            {{ match ($coverage) {
                'labor' => 'La mano de obra no se cobra; los repuestos sí.',
                'parts' => 'Los repuestos no se cobran; la mano de obra puede cobrarse.',
                default => 'Sin costo para el cliente salvo que el daño no esté cubierto.',
            } }}
        </div>
    @endif
    @if ($order->childOrders->count())
        <div class="mb-6 rounded-xl border border-cyan-200 bg-cyan-50 px-5 py-3 text-sm text-cyan-900">
            <strong>Reclamos de garantía:</strong>
            @foreach ($order->childOrders as $child)
                <a href="{{ route('orders.show', $child) }}" class="font-medium underline">#{{ $child->order_number }}</a>
                <span class="text-cyan-700">({{ $statuses[$child->status] ?? $child->status }}, {{ $child->entry_date?->format('d/m/Y') }})</span>{{ $loop->last ? '' : ',' }}
            @endforeach
        </div>
    @endif

    @php
        $nextActions = app(\App\Services\OrderWorkflow::class)->nextActions($order);
        $canTake = Auth::user()->isTechnician() && ! $order->user_id;
        $workLocked = in_array($order->status, ['delivered', 'closed_no_repair']);
    @endphp
    @if ($canTake || $nextActions || $order->status === 'ready')
        <div class="mb-6 bg-white rounded-xl shadow-sm border border-blue-200 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h3 class="text-sm font-semibold text-gray-800">Acciones rápidas</h3>
                <span class="text-xs text-gray-500">Técnico: {{ $order->user?->name ?? 'sin asignar' }}</span>
            </div>
            @if ($canTake)
                <form method="POST" action="{{ route('orders.take', $order) }}" class="mb-3">
                    @csrf
                    <button type="submit" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium">Tomar esta orden</button>
                </form>
            @endif
            @if ($nextActions)
                <form method="POST" action="{{ route('orders.quick-status', $order) }}" class="space-y-2">
                    @csrf @method('PATCH')
                    <input type="text" name="note" maxlength="255" placeholder="Nota para el historial (opcional)"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach ($nextActions as $status => $label)
                            <button type="submit" name="status" value="{{ $status }}"
                                @if ($status === 'closed_no_repair') onclick="return confirm('¿Cerrar la orden sin reparación? Los repuestos vuelven al stock.')" @endif
                                class="px-4 py-2.5 rounded-lg text-sm font-medium {{ $loop->first ? 'bg-blue-600 hover:bg-blue-700 text-white' : ($status === 'closed_no_repair' ? 'bg-white border border-red-200 text-red-600 hover:bg-red-50' : 'bg-white border text-gray-700 hover:bg-gray-50') }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </form>
            @endif
            @if ($order->status === 'ready')
                <a href="{{ route('orders.signatures.create', [$order, 'delivery']) }}" class="mt-2 block text-center w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium">
                    {{ $order->balance_due > 0 ? 'Cobrar ' . money($order->balance_due) . ' y entregar' : 'Entregar (firma del cliente)' }}
                </a>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Trabajo técnico</h3>
                <p class="text-sm text-gray-600 mb-3"><span class="text-gray-500">Falla declarada:</span> {{ $order->declared_fault }}</p>
                @if ($workLocked)
                    <dl class="space-y-2 text-sm">
                        <div><dt class="text-gray-500">Diagnóstico</dt><dd class="text-gray-800 whitespace-pre-line">{{ $order->diagnosis ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500">Trabajo realizado</dt><dd class="text-gray-800 whitespace-pre-line">{{ $order->work_done ?: '—' }}</dd></div>
                    </dl>
                @else
                    <form method="POST" action="{{ route('orders.work', $order) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        <div>
                            <label for="work_diagnosis" class="block text-sm font-medium text-gray-700 mb-1">Diagnóstico</label>
                            <textarea id="work_diagnosis" name="diagnosis" rows="3" maxlength="5000" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">{{ old('diagnosis', $order->diagnosis) }}</textarea>
                        </div>
                        <div>
                            <label for="work_done" class="block text-sm font-medium text-gray-700 mb-1">Trabajo realizado</label>
                            <textarea id="work_done" name="work_done" rows="3" maxlength="5000" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">{{ old('work_done', $order->work_done) }}</textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="work_diagnosis_cost" class="block text-sm font-medium text-gray-700 mb-1">Costo diagnóstico</label>
                                <input type="number" id="work_diagnosis_cost" name="diagnosis_cost" step="0.01" min="0" inputmode="decimal" value="{{ old('diagnosis_cost', $order->diagnosis_cost) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                            <div>
                                <label for="work_labor_cost" class="block text-sm font-medium text-gray-700 mb-1">Mano de obra</label>
                                <input type="number" id="work_labor_cost" name="labor_cost" step="0.01" min="0" inputmode="decimal" value="{{ old('labor_cost', $order->labor_cost) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                        </div>
                        <button type="submit" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium">Guardar trabajo</button>
                    </form>
                @endif
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Bitácora técnica</h3>
                <form method="POST" action="{{ route('orders.notes.store', $order) }}" class="flex flex-col sm:flex-row gap-2 mb-4">
                    @csrf
                    <textarea name="body" rows="2" maxlength="2000" required placeholder="Ej. Se pidió el repuesto al proveedor; llega el jueves."
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"></textarea>
                    <button type="submit" class="bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium sm:self-end">Agregar nota</button>
                </form>
                @forelse ($order->workNotes as $note)
                    <div class="text-sm border-l-2 border-gray-200 pl-3 py-1 mb-2">
                        <p class="text-gray-800 whitespace-pre-line">{{ $note->body }}</p>
                        <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-2">
                            {{ $note->user?->name ?? '—' }} · {{ $note->created_at->format('d/m/Y H:i') }}
                            @if ($note->user_id === Auth::id() || Auth::user()->isAdmin() || Auth::user()->isSuperAdmin())
                                <form method="POST" action="{{ route('orders.notes.destroy', [$order, $note]) }}" onsubmit="return confirm('¿Borrar esta nota?')" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-400 hover:text-red-600">borrar</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Sin notas todavía.</p>
                @endforelse
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-6">
                <div class="flex items-center justify-between border-b pb-2 mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Información del Dispositivo</h3>
                    @if (!in_array($order->status, ['delivered', 'closed_no_repair']))
                        <span class="inline-flex items-center gap-1 text-xs font-medium {{ $days > 7 ? 'text-red-600' : ($days > 3 ? 'text-amber-600' : 'text-gray-500') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $days }} día{{ $days !== 1 ? 's' : '' }} en taller
                        </span>
                    @endif
                </div>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Tipo</dt>
                        <dd class="font-medium text-gray-800">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium mt-1
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
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Marca</dt>
                        <dd class="font-medium text-gray-800">{{ $order->brand?->name ?? $order->brand_text ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Modelo</dt>
                        <dd class="font-medium text-gray-800">{{ $order->deviceModel?->name ?? $order->model_text ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Serial / IMEI</dt>
                        <dd class="font-mono text-gray-800">{{ $order->serial_imei ?? '—' }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-gray-500">Falla Declarada</dt>
                        <dd class="font-medium text-gray-800 mt-1">{{ $order->declared_fault ?? '—' }}</dd>
                    </div>
                    @if ($order->physical_condition)
                    <div class="col-span-2">
                        <dt class="text-gray-500">Condición Física</dt>
                        <dd class="font-medium text-gray-800 mt-1">{{ $order->physical_condition }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Cliente</h3>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Nombre</dt>
                        <dd class="font-medium text-gray-800">{{ $order->client->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Teléfono</dt>
                        <dd class="text-gray-800">{{ $order->client->phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Email</dt>
                        <dd class="text-gray-800">{{ $order->client->email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Documento</dt>
                        <dd class="text-gray-800">{{ $order->client->id_document ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            @if ($order->accessories->count())
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Accesorios Entregados</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach ($order->accessories as $acc)
                        <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 rounded-full text-sm text-gray-700">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            {{ $acc->name }}
                        </span>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Fotos</h3>
                @if ($order->photos->count())
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
                        @foreach ($order->photos as $photo)
                            <a href="{{ route('orders.photo.show', [$order, $photo]) }}" target="_blank" class="relative block aspect-square rounded-lg overflow-hidden border bg-gray-100 hover:opacity-90 transition">
                                <img src="{{ route('orders.photo.show', [$order, $photo]) }}" class="w-full h-full object-cover" alt="{{ $photo->caption ?? 'Foto' }}" loading="lazy">
                                <span class="absolute bottom-1 left-1 rounded bg-black/60 px-1.5 py-0.5 text-[10px] text-white">{{ ['reception' => 'Recepción', 'process' => 'Proceso', 'delivery' => 'Entrega'][$photo->stage] ?? $photo->stage }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
                @unless ($workLocked)
                    <form method="POST" action="{{ route('orders.photo.upload', $order) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <label class="flex-1 min-w-48 cursor-pointer rounded-lg border-2 border-dashed border-gray-300 px-3 py-2.5 text-sm text-gray-600 hover:border-blue-400">
                            <span id="photoLabel">📷 Tomar o elegir foto</span>
                            <input type="file" name="photo" accept="image/*" capture="environment" required class="sr-only"
                                onchange="document.getElementById('photoLabel').textContent = this.files[0] ? this.files[0].name : '📷 Tomar o elegir foto'">
                        </label>
                        <select name="stage" class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-white">
                            <option value="process" {{ in_array($order->status, ['diagnosing', 'waiting_approval', 'repairing', 'warranty']) ? 'selected' : '' }}>Proceso</option>
                            <option value="reception" {{ $order->status === 'received' ? 'selected' : '' }}>Recepción</option>
                            <option value="delivery" {{ $order->status === 'ready' ? 'selected' : '' }}>Entrega</option>
                        </select>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium">Subir</button>
                    </form>
                    @error('photo') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                @endunless
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-6">
                <div class="flex items-center justify-between border-b pb-2 mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Repuestos</h3>
                    @if ($order->isWarrantyOrder())
                        <span class="text-xs text-cyan-700">{{ $order->warrantyCoversParts() ? 'Garantía: repuestos sin costo' : 'Garantía de mano de obra: los repuestos se cobran' }}</span>
                    @endif
                </div>
                @php $partsLocked = in_array($order->status, ['delivered', 'closed_no_repair']); @endphp
                @if ($order->parts->count())
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm mb-3">
                            <thead>
                                <tr class="text-left text-gray-500 border-b">
                                    <th class="py-2 font-medium">Repuesto</th>
                                    <th class="py-2 font-medium text-right">Cant.</th>
                                    <th class="py-2 font-medium text-right">P. unit.</th>
                                    <th class="py-2 font-medium text-right">Subtotal</th>
                                    <th class="py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($order->parts as $orderPart)
                                    <tr class="border-b">
                                        <td class="py-2">
                                            <a href="{{ route('spare-parts.show', $orderPart->spare_part_id) }}" class="text-blue-600 hover:underline">{{ $orderPart->sparePart?->name }}</a>
                                        </td>
                                        <td class="py-2 text-right">{{ $orderPart->quantity }}</td>
                                        <td class="py-2 text-right">{{ money($orderPart->unit_price) }}</td>
                                        <td class="py-2 text-right font-medium">{{ money($orderPart->subtotal) }}</td>
                                        <td class="py-2 text-right">
                                            @unless ($partsLocked)
                                                <form method="POST" action="{{ route('orders.parts.destroy', [$order, $orderPart]) }}" onsubmit="return confirm('¿Quitar este repuesto? Las unidades vuelven al stock.')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Quitar</button>
                                                </form>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-400 mb-3">Sin repuestos del inventario.</p>
                @endif

                @unless ($partsLocked)
                    <form method="POST" action="{{ route('orders.parts.store', $order) }}" class="flex flex-wrap items-end gap-2" id="addPartForm">
                        @csrf
                        <div class="flex-1 min-w-48 relative">
                            <label class="block text-xs text-gray-600 mb-1">Agregar repuesto</label>
                            <input type="text" id="partSearch" autocomplete="off" placeholder="Buscar por nombre, código o modelo..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            <input type="hidden" name="spare_part_id" id="partId">
                            <div id="partResults" class="hidden absolute z-50 w-full bg-white border border-gray-300 rounded-lg mt-1 shadow-lg max-h-56 overflow-y-auto"></div>
                        </div>
                        <div class="w-20">
                            <label class="block text-xs text-gray-600 mb-1">Cant.</label>
                            <input type="number" name="quantity" value="1" min="1" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Agregar</button>
                    </form>
                    <script>
                    (function () {
                        var input = document.getElementById('partSearch');
                        var hidden = document.getElementById('partId');
                        var results = document.getElementById('partResults');
                        var url = @json(route('spare-parts.search'));
                        var timer;

                        input.addEventListener('input', function () {
                            hidden.value = '';
                            clearTimeout(timer);
                            var q = input.value.trim();
                            if (q.length < 2) { results.classList.add('hidden'); return; }
                            timer = setTimeout(function () {
                                fetch(url + '?branch_id={{ $order->branch_id }}&q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                                    .then(function (r) { return r.ok ? r.json() : []; })
                                    .then(function (items) {
                                        if (input.value.trim() !== q) return;
                                        results.innerHTML = '';
                                        if (!items.length) {
                                            results.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500">Sin resultados</div>';
                                        }
                                        items.forEach(function (item) {
                                            var div = document.createElement('div');
                                            div.className = 'px-3 py-2 text-sm cursor-pointer hover:bg-blue-50' + (item.stock < 1 ? ' text-gray-400' : '');
                                            div.textContent = item.label;
                                            div.addEventListener('mousedown', function (e) {
                                                e.preventDefault();
                                                input.value = item.name;
                                                hidden.value = item.id;
                                                results.classList.add('hidden');
                                            });
                                            results.appendChild(div);
                                        });
                                        results.classList.remove('hidden');
                                    });
                            }, 250);
                        });
                        input.addEventListener('blur', function () { setTimeout(function () { results.classList.add('hidden'); }, 200); });
                        document.getElementById('addPartForm').addEventListener('submit', function (e) {
                            if (!hidden.value) { e.preventDefault(); input.focus(); input.classList.add('border-red-500'); }
                        });
                    })();
                    </script>
                @endunless
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Historial de Estados</h3>
                <div class="space-y-3">
                    @forelse ($order->statusHistory->sortByDesc('changed_at') as $history)
                        <div class="flex items-start gap-3 text-sm">
                            <div class="w-2 h-2 mt-1.5 rounded-full bg-blue-500 shrink-0"></div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-800">
                                        {{ $statuses[$history->to_status] ?? $history->to_status }}
                                    </span>
                                    @if ($history->from_status && $history->from_status !== $history->to_status)
                                        <span class="text-gray-400 text-xs">(desde {{ $statuses[$history->from_status] ?? $history->from_status }})</span>
                                    @endif
                                </div>
                                <p class="text-gray-500 text-xs mt-0.5">
                                    {{ $history->changed_at ? $history->changed_at->format('d/m/Y H:i') : '' }}
                                    por {{ $history->changedBy->name ?? '—' }}
                                </p>
                                @if ($history->notes)
                                    <p class="text-gray-600 mt-1">{{ $history->notes }}</p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-400 text-sm">Sin cambios registrados.</p>
                    @endforelse
                </div>
            </div>

            @if ($activity->count())
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <div class="flex items-center justify-between border-b pb-2 mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Actividad</h3>
                    <span class="text-xs text-gray-400">Visible solo para administradores</span>
                </div>
                <ul class="space-y-3 text-sm">
                    @foreach ($activity as $log)
                        <li class="border-l-2 pl-3 {{ $log->event === 'deleted' ? 'border-red-300' : ($log->event === 'created' ? 'border-green-300' : 'border-blue-300') }}">
                            <p class="text-gray-800">
                                <span class="font-medium">{{ $log->user?->name ?? 'Sistema' }}</span>
                                {{ strtolower(\App\Models\AuditLog::events()[$log->event] ?? $log->event) }}
                                {{ $log->description }}
                            </p>
                            <p class="text-xs text-gray-400">{{ $log->created_at->format('d/m/Y H:i') }}</p>
                            <div class="text-xs mt-1">@include('audit.partials.changes', ['log' => $log])</div>
                        </li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Tiempo</h3>
                @if (!in_array($order->status, ['delivered', 'closed_no_repair']))
                    <div class="text-center mb-4">
                        <p class="text-3xl font-bold {{ $days > 7 ? 'text-red-600' : ($days > 3 ? 'text-amber-600' : 'text-gray-800') }}">{{ $days }}</p>
                        <p class="text-xs text-gray-500">días en taller</p>
                    </div>
                @endif
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Ingreso</dt>
                        <dd class="font-medium">{{ $order->entry_date ? $order->entry_date->format('d/m/Y') : '—' }}</dd>
                    </div>
                    @if ($order->estimated_delivery)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Entrega Estimada</dt>
                        <dd class="font-medium">{{ $order->estimated_delivery->format('d/m/Y') }}</dd>
                    </div>
                    @endif
                    @if ($order->delivery_date)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Entregado</dt>
                        <dd class="font-medium">{{ $order->delivery_date->format('d/m/Y') }}</dd>
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Técnico</dt>
                        <dd class="font-medium">{{ $order->user->name ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Costos</h3>
                @if ($order->total_amount > 0)
                    <div class="text-center mb-4">
                        <p class="text-2xl font-bold text-gray-800">{{ money($order->total_amount) }}</p>
                        <p class="text-xs text-gray-500">total</p>
                        @if ($order->balance_due > 0)
                            <span class="inline-flex items-center gap-1 mt-1 text-xs font-medium text-red-600">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                Debe {{ money($order->balance_due) }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 mt-1 text-xs font-medium text-green-600">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Pagado
                            </span>
                        @endif
                    </div>
                @endif
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Diagnóstico</dt>
                        <dd class="font-medium">{{ money($order->diagnosis_cost) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Mano de Obra</dt>
                        <dd class="font-medium">{{ money($order->labor_cost) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Repuestos</dt>
                        <dd class="font-medium">{{ money($order->parts_cost) }}</dd>
                    </div>
                    @if ($order->surcharge_amount > 0)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Recargo ({{ $order->surcharge_percent }}%)</dt>
                        <dd class="font-medium">{{ money($order->surcharge_amount) }}</dd>
                    </div>
                    @endif
                    @if ($order->tax_amount > 0)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">IVA (facturado)</dt>
                        <dd class="font-medium">{{ money($order->tax_amount) }}</dd>
                    </div>
                    @endif
                    <div class="flex justify-between border-t pt-2 font-semibold">
                        <dt class="text-gray-700">Total</dt>
                        <dd class="text-gray-800">{{ money($order->total_amount) }}</dd>
                    </div>
                    <div class="flex justify-between text-green-700">
                        <dt>Pagado</dt>
                        <dd>{{ money($order->amount_paid) }}</dd>
                    </div>
                    <div class="flex justify-between border-t pt-2 font-semibold {{ $order->balance_due > 0 ? 'text-red-600' : 'text-green-600' }}">
                        <dt>Saldo</dt>
                        <dd>{{ money($order->balance_due) }}</dd>
                    </div>
                    @if ($order->creditDue() > 0)
                    <div class="flex justify-between font-semibold text-amber-700">
                        <dt>Saldo a favor del cliente</dt>
                        <dd>{{ money($order->creditDue()) }}</dd>
                    </div>
                    @endif
                </dl>
                @if ($order->total_amount > 0 && Gate::allows('manage-invoices'))
                    <form method="POST" action="{{ route('invoices.generate-from-order', $order) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Facturar
                        </button>
                    </form>
                @endif
            </div>

            @if ($checklists->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Checklists</h3>
                @if ($order->status === 'ready' && isset($checklists['delivery']) && ! $checklists['delivery']['complete'])
                    <div class="mb-3 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                        La orden está lista pero falta el control de calidad.
                    </div>
                @endif
                <ul class="space-y-3">
                    @foreach ($checklists as $stageKey => $progress)
                        <li class="flex items-center justify-between gap-2 text-sm">
                            <div>
                                <p class="font-medium text-gray-800">{{ \App\Models\Checklist::stages()[$stageKey] }}</p>
                                <p class="text-xs {{ $progress['complete'] ? 'text-green-700' : 'text-gray-500' }}">
                                    {{ $progress['answered'] }}/{{ $progress['total'] }}{{ $progress['complete'] ? ' · completo' : '' }}
                                    @if ($progress['damaged'])
                                        · <span class="text-red-600 font-medium">{{ $progress['damaged'] }} con daño</span>
                                    @endif
                                </p>
                            </div>
                            <a href="{{ route('orders.checklists.edit', [$order, $stageKey]) }}"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium border {{ $progress['complete'] ? 'bg-white text-gray-700 hover:bg-gray-50' : 'bg-blue-600 text-white border-blue-600 hover:bg-blue-700' }}">
                                {{ $progress['answered'] ? 'Ver / editar' : 'Completar' }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            @endif

            @if ($order->appointments->count() || Gate::allows('manage-appointments'))
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Visitas</h3>
                @forelse ($order->appointments as $visit)
                    <a href="{{ route('appointments.show', $visit) }}" class="block text-sm py-1.5 hover:text-blue-700">
                        <span class="font-medium">{{ $visit->scheduled_at->format('d/m/Y H:i') }}</span>
                        · {{ \App\Models\Appointment::types()[$visit->type] ?? $visit->type }}
                        <span class="text-xs text-gray-500">({{ strtolower(\App\Models\Appointment::statuses()[$visit->status]) }}{{ $visit->user ? ', ' . $visit->user->name : '' }})</span>
                    </a>
                @empty
                    <p class="text-sm text-gray-400">Sin visitas agendadas.</p>
                @endforelse
                @can('manage-appointments')
                    @unless (in_array($order->status, ['delivered', 'closed_no_repair']))
                        <a href="{{ route('appointments.create', ['order_id' => $order->id]) }}" class="mt-3 w-full inline-flex justify-center px-4 py-2 rounded-lg text-sm font-medium bg-white hover:bg-gray-50 text-gray-700 border">Agendar visita</a>
                    @endunless
                @endcan
            </div>
            @endif

            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Firmas</h3>
                @php $isAdmin = Auth::user()->isAdmin() || Auth::user()->isSuperAdmin(); @endphp
                <div class="space-y-4">
                    @foreach (\App\Models\OrderSignature::stages() as $stageKey => $stageLabel)
                        @php $signature = $order->signatures->firstWhere('stage', $stageKey); @endphp
                        <div>
                            <p class="text-sm font-medium text-gray-700">{{ $stageLabel }}</p>
                            @if ($signature)
                                <img src="{{ route('orders.signatures.image', [$order, $stageKey]) }}" alt="Firma de {{ strtolower($stageLabel) }}"
                                    class="mt-1 h-20 w-full object-contain border rounded-lg bg-white">
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $signature->signed_at->format('d/m/Y H:i') }}
                                    @if ($isAdmin)
                                        · <a href="{{ route('orders.signatures.create', [$order, $stageKey]) }}" class="text-blue-600 hover:underline">Reemplazar</a>
                                    @endif
                                </p>
                            @elseif ($stageKey === 'reception' || in_array($order->status, ['ready', 'delivered', 'closed_no_repair']))
                                <a href="{{ route('orders.signatures.create', [$order, $stageKey]) }}"
                                    class="mt-1 w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition
                                    {{ $stageKey === 'delivery' && $order->status === 'ready' ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-white hover:bg-gray-50 text-gray-700 border' }}">
                                    {{ $stageKey === 'reception' ? 'Firmar recepción' : ($order->isReturnWithoutRepair() ? 'Firmar devolución' : 'Firmar entrega') }}
                                </a>
                            @else
                                <p class="text-xs text-gray-400 mt-1">Disponible cuando la orden esté lista.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($order->warranty)
                @php
                    $warranty = $order->warranty;
                    $warrantyStatus = $warranty->currentStatus();
                    $warrantyBadge = [
                        'active' => 'bg-green-100 text-green-800',
                        'expiring' => 'bg-amber-100 text-amber-800',
                        'expired' => 'bg-gray-100 text-gray-600',
                        'claimed' => 'bg-cyan-100 text-cyan-800',
                    ][$warrantyStatus];
                @endphp
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <div class="flex items-center justify-between border-b pb-2 mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Garantía</h3>
                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $warrantyBadge }}">{{ \App\Models\Warranty::statuses()[$warrantyStatus] }}</span>
                    </div>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Tipo</dt><dd class="text-gray-800">{{ \App\Models\Warranty::types()[$warranty->warranty_type] ?? $warranty->warranty_type }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Desde</dt><dd class="text-gray-800">{{ $warranty->start_date->format('d/m/Y') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Hasta</dt><dd class="font-medium text-gray-800">{{ $warranty->end_date->format('d/m/Y') }}</dd></div>
                    </dl>
                    @if ($warranty->conditions)
                        <p class="text-sm text-gray-600 mt-2">{{ $warranty->conditions }}</p>
                    @endif

                    @can('manage-warranties')
                        @if ($warranty->isValid())
                            <a href="{{ route('warranties.claim.create', $order) }}" class="mt-4 w-full inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-medium bg-cyan-600 hover:bg-cyan-700 text-white transition">
                                Abrir reclamo de garantía
                            </a>
                        @endif
                        <details class="mt-3">
                            <summary class="text-xs text-blue-600 cursor-pointer">Editar tipo y condiciones</summary>
                            <form method="POST" action="{{ route('warranties.update', $warranty) }}" class="mt-2 space-y-2">
                                @csrf @method('PATCH')
                                <select name="warranty_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                                    @foreach (\App\Models\Warranty::types() as $typeKey => $typeLabel)
                                        <option value="{{ $typeKey }}" {{ $warranty->warranty_type === $typeKey ? 'selected' : '' }}>{{ $typeLabel }}</option>
                                    @endforeach
                                </select>
                                <textarea name="conditions" rows="2" placeholder="Condiciones (ej. no cubre golpes ni humedad)"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ $warranty->conditions }}</textarea>
                                <button type="submit" class="w-full bg-white hover:bg-gray-50 text-gray-700 border px-3 py-1.5 rounded-lg text-sm font-medium">Guardar</button>
                            </form>
                        </details>
                    @endcan
                </div>
            @endif

            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Seguimiento del cliente</h3>
                <p class="text-xs text-gray-500 mb-2">Enlace privado para que el cliente vea el estado de su orden.</p>
                <div class="flex gap-2">
                    <input type="text" id="trackingUrl" readonly value="{{ $order->trackingUrl() }}"
                        class="flex-1 min-w-0 px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-xs text-gray-600">
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('trackingUrl').value).then(() => { this.textContent = 'Copiado'; })"
                        class="px-3 py-2 rounded-lg text-xs font-medium border bg-white hover:bg-gray-50">Copiar</button>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3">
                    <img src="{{ \App\Support\QrCode::dataUri($order->trackingUrl(), 3) }}" alt="Código QR de seguimiento" class="w-24 h-24">
                    <a href="{{ $order->trackingUrl() }}" target="_blank" rel="noopener" class="text-sm text-blue-600 hover:underline">Ver como cliente →</a>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">WhatsApp</h3>
                @php
                    $waSuggested = match ($order->status) {
                        'received', 'diagnosing', 'waiting_approval' => 'budget',
                        'repairing', 'ready' => 'ready',
                        'delivered' => 'delivery',
                        default => null,
                    };
                @endphp
                @if (\App\Services\WhatsAppMessage::normalizePhone($order->client?->phone))
                    <div class="space-y-2">
                        @foreach (\App\Services\WhatsAppMessage::TYPES as $waType => $waLabel)
                            <form method="POST" action="{{ route('orders.whatsapp', [$order, $waType]) }}" target="_blank"
                                onsubmit="setTimeout(function () { window.location.reload(); }, 1500)">
                                @csrf
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition
                                    {{ $waType === $waSuggested ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-white hover:bg-gray-50 text-gray-700 border' }}">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                    {{ $waLabel }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                    @if ($order->status === 'diagnosing' || $order->status === 'received')
                        <p class="text-xs text-gray-500 mt-2">Al enviar el presupuesto la orden pasa a "Esperando Aprobación".</p>
                    @endif
                @else
                    <p class="text-sm text-gray-500">El cliente no tiene un teléfono válido para WhatsApp.</p>
                @endif

                @if ($order->whatsappLogs->count())
                    <ul class="mt-4 border-t pt-3 space-y-1 text-xs text-gray-500">
                        @foreach ($order->whatsappLogs->take(5) as $log)
                            <li>{{ \App\Services\WhatsAppMessage::TYPES[$log->trigger_event] ?? $log->trigger_event }} · {{ $log->sent_at->format('d/m/Y H:i') }} · {{ $log->sentBy?->name ?? '—' }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">
                    Pagos
                    @if ($order->payments->count())
                        <span class="text-sm font-normal text-gray-500">({{ $order->payments->count() }})</span>
                    @endif
                </h3>

                @can('register-payments')
                <form method="POST" action="{{ route('orders.payments.store', $order) }}" class="mb-4 p-3 bg-gray-50 rounded-lg border">
                    @csrf
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Monto</label>
                            <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"
                                class="w-full px-2 py-1.5 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Método</label>
                            <select name="method" required
                                class="w-full px-2 py-1.5 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                @foreach (\App\Support\PaymentMethods::all() as $methodKey => $methodLabel)
                                    <option value="{{ $methodKey }}">{{ $methodLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Referencia (opcional)</label>
                            <input type="text" name="reference" placeholder="N° de transferencia, voucher..."
                                class="w-full px-2 py-1.5 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                    <button type="submit" class="mt-2 w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg transition">
                        Registrar Pago
                    </button>
                </form>

                @if ($order->creditDue() > 0)
                <form method="POST" action="{{ route('orders.refunds.store', $order) }}" class="mb-4 p-3 bg-amber-50 rounded-lg border border-amber-200"
                    onsubmit="return confirm('¿Registrar la devolución? Sale de la caja del día.')">
                    @csrf
                    <p class="text-sm text-amber-800 mb-2">Se cobró {{ money($order->creditDue()) }} de más. Devuélvelo al cliente:</p>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="refund_amount" class="block text-xs font-medium text-gray-600 mb-1">Monto</label>
                            <input type="number" step="0.01" min="0.01" max="{{ $order->creditDue() }}" id="refund_amount" name="amount" required value="{{ $order->creditDue() }}"
                                class="w-full px-2 py-1.5 text-base sm:text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label for="refund_method" class="block text-xs font-medium text-gray-600 mb-1">Método</label>
                            <select id="refund_method" name="method" required
                                class="w-full px-2 py-1.5 text-base sm:text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                @foreach (\App\Support\PaymentMethods::all() as $methodKey => $methodLabel)
                                    <option value="{{ $methodKey }}">{{ $methodLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="mt-2 w-full bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg transition">
                        Registrar devolución
                    </button>
                </form>
                @endif
                @endcan

                @if ($order->payments->count())
                <div class="space-y-2">
                    @foreach ($order->payments as $payment)
                        <div class="flex items-center justify-between text-sm py-1.5 border-b last:border-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium {{ $payment->isRefund() ? 'text-amber-700' : 'text-gray-800' }}">{{ money($payment->amount) }}</span>
                                @if ($payment->isRefund())
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-800">Devolución</span>
                                @endif
                                <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">
                                    {{ $payment->method_label }}
                                </span>
                                @if ($payment->reference)
                                    <span class="text-xs text-gray-400">· {{ $payment->reference }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs text-gray-400">{{ $payment->paid_at ? $payment->paid_at->format('d/m/Y H:i') : '' }}</span>
@can('register-payments')
                                <a href="{{ route('orders.payments.receipt', [$order, $payment]) }}" class="text-gray-400 hover:text-gray-600" title="Imprimir comprobante">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                </a>
                                @endcan
                                @can('manage-payments')
                                <form method="POST" action="{{ route('orders.payments.destroy', [$order, $payment]) }}" class="inline"
                                    onsubmit="return confirm('¿Eliminar este pago de {{ money($payment->amount) }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
                @else
                    <p class="text-sm text-gray-400 text-center py-2">Sin pagos registrados.</p>
                @endif
            </div>

            @if ($order->notes)
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Notas</h3>
                <p class="text-sm text-gray-700">{{ $order->notes }}</p>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
