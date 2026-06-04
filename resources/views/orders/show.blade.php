@extends('layouts.app')

@section('title', 'Orden #' . $order->order_number)

@section('content')
@php
    $days = $order->entry_date ? $order->entry_date->diffInDays(now()) : 0;
@endphp
<div class="p-6 max-w-5xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="{{ route('orders.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Volver a órdenes
            </a>
            <div class="flex items-center gap-3 mt-2">
                <h2 class="text-2xl font-bold text-gray-800">Orden #{{ $order->order_number }}</h2>
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
            <a href="{{ route('orders.edit', $order) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                Editar
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-6">

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
                    @if ($order->diagnosis)
                    <div class="col-span-2">
                        <dt class="text-gray-500">Diagnóstico</dt>
                        <dd class="font-medium text-gray-800 mt-1">{{ $order->diagnosis }}</dd>
                    </div>
                    @endif
                    @if ($order->work_done)
                    <div class="col-span-2">
                        <dt class="text-gray-500">Trabajo Realizado</dt>
                        <dd class="font-medium text-gray-800 mt-1">{{ $order->work_done }}</dd>
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

            @if ($order->photos->count())
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <div class="flex items-center justify-between border-b pb-2 mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Fotos</h3>
                    <a href="{{ route('orders.edit', $order) }}#photos" class="text-xs text-blue-600 hover:text-blue-800">Agregar</a>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach ($order->photos as $photo)
                        <a href="{{ Storage::url($photo->file_path) }}" target="_blank" class="block aspect-square rounded-lg overflow-hidden border bg-gray-100 hover:opacity-90 transition">
                            <img src="{{ Storage::url($photo->file_path) }}" class="w-full h-full object-cover" alt="{{ $photo->caption ?? 'Foto' }}" loading="lazy">
                        </a>
                    @endforeach
                </div>
            </div>
            @endif

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
                                    @if ($history->from_status)
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
                        <p class="text-2xl font-bold text-gray-800">${{ number_format($order->total_amount, 2) }}</p>
                        <p class="text-xs text-gray-500">total</p>
                        @if ($order->balance_due > 0)
                            <span class="inline-flex items-center gap-1 mt-1 text-xs font-medium text-red-600">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                Debe ${{ number_format($order->balance_due, 2) }}
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
                        <dd class="font-medium">${{ number_format($order->diagnosis_cost, 2) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Mano de Obra</dt>
                        <dd class="font-medium">${{ number_format($order->labor_cost, 2) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Repuestos</dt>
                        <dd class="font-medium">${{ number_format($order->parts_cost, 2) }}</dd>
                    </div>
                    @if ($order->surcharge_amount > 0)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Recargo ({{ $order->surcharge_percent }}%)</dt>
                        <dd class="font-medium">${{ number_format($order->surcharge_amount, 2) }}</dd>
                    </div>
                    @endif
                    <div class="flex justify-between border-t pt-2 font-semibold">
                        <dt class="text-gray-700">Total</dt>
                        <dd class="text-gray-800">${{ number_format($order->total_amount, 2) }}</dd>
                    </div>
                    <div class="flex justify-between text-green-700">
                        <dt>Pagado</dt>
                        <dd>${{ number_format($order->amount_paid, 2) }}</dd>
                    </div>
                    <div class="flex justify-between border-t pt-2 font-semibold {{ $order->balance_due > 0 ? 'text-red-600' : 'text-green-600' }}">
                        <dt>Saldo</dt>
                        <dd>${{ number_format($order->balance_due, 2) }}</dd>
                    </div>
                </dl>
                @if ($order->total_amount > 0)
                    <a href="{{ route('invoices.create', ['order_id' => $order->id]) }}" class="mt-4 w-full inline-flex items-center justify-center gap-1.5 bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Facturar
                    </a>
                @endif
            </div>

            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">
                    Pagos
                    @if ($order->payments->count())
                        <span class="text-sm font-normal text-gray-500">({{ $order->payments->count() }})</span>
                    @endif
                </h3>

                @can('manage-payments')
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
                                <option value="cash">Efectivo</option>
                                <option value="transfer">Transferencia</option>
                                <option value="card">Tarjeta</option>
                                <option value="other">Otro</option>
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
                @endcan

                @if ($order->payments->count())
                <div class="space-y-2">
                    @foreach ($order->payments as $payment)
                        <div class="flex items-center justify-between text-sm py-1.5 border-b last:border-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-800">${{ number_format($payment->amount, 2) }}</span>
                                <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">
                                    {{ $payment->method === 'cash' ? 'Efectivo' : ($payment->method === 'transfer' ? 'Transferencia' : ($payment->method === 'card' ? 'Tarjeta' : 'Otro')) }}
                                </span>
                                @if ($payment->reference)
                                    <span class="text-xs text-gray-400">· {{ $payment->reference }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs text-gray-400">{{ $payment->paid_at ? $payment->paid_at->format('d/m/Y H:i') : '' }}</span>
                                <a href="{{ route('orders.payments.receipt', [$order, $payment]) }}" class="text-gray-400 hover:text-gray-600" title="Imprimir comprobante">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                </a>
                                @can('manage-payments')
                                <form method="POST" action="{{ route('orders.payments.destroy', [$order, $payment]) }}" class="inline"
                                    onsubmit="return confirm('¿Eliminar este pago de ${{ number_format($payment->amount, 2) }}?')">
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
