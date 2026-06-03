@extends('layouts.app')

@section('title', 'Orden #' . $order->order_number)

@section('content')
<div class="p-6 max-w-5xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="{{ route('orders.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Volver a órdenes
            </a>
            <h2 class="text-2xl font-bold text-gray-800 mt-2">Orden #{{ $order->order_number }}</h2>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('orders.edit', $order) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                Editar
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-6">

            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Información del Dispositivo</h3>
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
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Estado</h3>
                <div class="text-center">
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium
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
                <dl class="mt-4 space-y-2 text-sm">
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
            </div>

            @if ($order->payments->count())
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Pagos</h3>
                <div class="space-y-2">
                    @foreach ($order->payments as $payment)
                        <div class="flex items-center justify-between text-sm py-1 border-b last:border-0">
                            <div>
                                <span class="font-medium text-gray-800">${{ number_format($payment->amount, 2) }}</span>
                                <span class="text-gray-500 text-xs ml-1">{{ $payment->method }}</span>
                            </div>
                            <span class="text-xs text-gray-400">{{ $payment->paid_at ? $payment->paid_at->format('d/m/Y H:i') : '' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

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
