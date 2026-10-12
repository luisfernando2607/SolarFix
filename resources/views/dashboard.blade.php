@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="p-6">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Dashboard</h2>
        <p class="text-gray-500">Bienvenido, {{ Auth::user()->name }}</p>
    </div>

    @if ($metrics)
    @php
        $cur = $metrics['current'];
        $prev = $metrics['previous'];
        // [etiqueta, clave, formato, ¿subir es bueno?]
        $tiles = [
            ['Ingresos cobrados', 'revenue', 'money', true],
            ['Órdenes recibidas', 'received', 'int', true],
            ['Órdenes entregadas', 'delivered', 'int', true],
            ['Ticket promedio', 'avg_ticket', 'money', true],
            ['Días de reparación', 'avg_days', 'dec', false],
            ['Presupuestos aprobados', 'approval_rate', 'pct', true],
            ['Retrabajos (garantías)', 'rework_rate', 'pct', false],
        ];
        $fmt = fn ($v, $f) => $v === null ? '—' : match ($f) {
            'money' => money($v), 'pct' => $v . '%', 'dec' => number_format($v, 1), default => (string) $v,
        };
    @endphp
    <div class="mb-6">
        <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
            <h3 class="text-lg font-semibold text-gray-800">Indicadores de {{ now()->translatedFormat('F') }}</h3>
            <a href="{{ route('reports.index') }}" class="text-sm text-blue-600 hover:underline">Ver reportes →</a>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach ($tiles as [$label, $key, $format, $upIsGood])
                @php
                    $value = $cur[$key]; $before = $prev[$key];
                    $delta = ($value !== null && $before !== null) ? $value - $before : null;
                    $good = $delta === null || $delta == 0 ? null : (($delta > 0) === $upIsGood);
                @endphp
                <div class="bg-white rounded-xl shadow-sm border p-4">
                    <p class="text-xs text-gray-500">{{ $label }}</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ $fmt($value, $format) }}</p>
                    <p class="text-xs mt-1 {{ $good === null ? 'text-gray-400' : ($good ? 'text-green-700' : 'text-red-600') }}">
                        @if ($delta === null)
                            sin datos del mes anterior
                        @elseif ($delta == 0)
                            igual que el mes anterior
                        @else
                            {{ $delta > 0 ? '▲' : '▼' }} {{ $format === 'pct' ? abs($delta) . ' pts' : $fmt(abs($delta), $format) }} vs. mes anterior
                        @endif
                    </p>
                    @if ($key === 'approval_rate' && ($cur['approved'] + $cur['rejected']))
                        <p class="text-[11px] text-gray-400">{{ $cur['approved'] }} aprobados · {{ $cur['rejected'] }} rechazados</p>
                    @endif
                </div>
            @endforeach
            <div class="bg-white rounded-xl shadow-sm border p-4">
                <p class="text-xs text-gray-500">Valor del inventario</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ money($metrics['inventory_value']) }}</p>
                <p class="text-xs text-gray-400 mt-1">a costo promedio</p>
            </div>
        </div>

        @if ($metrics['receivables']['count'])
            <div class="mt-4 bg-white rounded-xl shadow-sm border border-red-200">
                <div class="px-4 py-3 border-b bg-red-50 flex flex-wrap justify-between gap-2">
                    <span class="text-sm font-semibold text-red-900">Cuentas por cobrar: equipos entregados con saldo</span>
                    <span class="text-sm font-bold text-red-700">{{ money($metrics['receivables']['total']) }} · {{ $metrics['receivables']['count'] }} {{ $metrics['receivables']['count'] === 1 ? 'orden' : 'órdenes' }}</span>
                </div>
                <ul class="divide-y">
                    @foreach ($metrics['receivables']['orders'] as $order)
                        <li>
                            <a href="{{ route('orders.show', $order) }}" class="px-4 py-2.5 flex flex-wrap justify-between gap-2 text-sm hover:bg-gray-50">
                                <span><span class="font-medium text-blue-700">{{ $order->order_number }}</span> · {{ $order->client?->name }} <span class="text-gray-400">{{ $order->client?->phone }}</span></span>
                                <span class="text-gray-500">entregada {{ $order->delivery_date?->format('d/m/Y') }} · <span class="font-semibold text-red-600">{{ money($order->balance_due) }}</span></span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
    @endif

    @if ($counter)
    <div class="mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-3">Mostrador</h3>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-green-200">
                <div class="px-4 py-3 border-b bg-green-50 flex justify-between text-sm font-semibold text-green-900">
                    <span>Listos para entregar</span><span>{{ $counter['ready_count'] }}</span>
                </div>
                <ul class="divide-y">
                    @forelse ($counter['ready'] as $order)
                        <li class="px-4 py-2.5">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <a href="{{ route('orders.show', $order) }}" class="min-w-0 text-sm">
                                        <span class="font-medium text-blue-700">{{ $order->order_number }}</span>
                                        <span class="text-gray-700">· {{ $order->client?->name }}</span>
                                        <span class="block text-xs text-gray-500 truncate">{{ $order->brand?->name ?? $order->brand_text }} {{ $order->deviceModel?->name ?? $order->model_text }}{{ $order->client?->phone ? ' · ' . $order->client->phone : '' }}</span>
                                    </a>
                                    <div class="text-right text-xs space-y-1">
                                        <span class="block {{ $order->status_since->lt(now()->subDays(7)) ? 'text-red-600 font-semibold' : 'text-gray-500' }}">lista {{ $order->status_since->diffForHumans() }}</span>
                                        @if ($order->balance_due > 0) <span class="block text-red-600 font-medium">Saldo {{ money($order->balance_due) }}</span> @endif
                                        <span class="flex gap-1 justify-end">
                                            <form method="POST" action="{{ route('orders.whatsapp', [$order, 'ready']) }}" target="_blank">@csrf
                                                <button type="submit" class="px-2 py-1 rounded border border-green-300 text-green-700 hover:bg-green-50">Avisar</button>
                                            </form>
                                            <a href="{{ route('orders.signatures.create', [$order, 'delivery']) }}" class="px-2 py-1 rounded bg-green-600 text-white hover:bg-green-700">Entregar</a>
                                        </span>
                                    </div>
                                </div>
                            </li>
                    @empty
                        <li class="px-4 py-3 text-xs text-gray-400">No hay equipos listos.</li>
                    @endforelse
                </ul>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-orange-200">
                <div class="px-4 py-3 border-b bg-orange-50 flex justify-between text-sm font-semibold text-orange-900">
                    <span>Esperando aprobación</span><span>{{ $counter['waiting_count'] }}</span>
                </div>
                <ul class="divide-y">
                    @forelse ($counter['waiting'] as $order)
                        <li class="px-4 py-2.5">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <a href="{{ route('orders.show', $order) }}" class="min-w-0 text-sm">
                                        <span class="font-medium text-blue-700">{{ $order->order_number }}</span>
                                        <span class="text-gray-700">· {{ $order->client?->name }}</span>
                                        <span class="block text-xs text-gray-500 truncate">{{ $order->brand?->name ?? $order->brand_text }} {{ $order->deviceModel?->name ?? $order->model_text }}{{ $order->client?->phone ? ' · ' . $order->client->phone : '' }}</span>
                                    </a>
                                    <div class="text-right text-xs space-y-1">
                                        <span class="block {{ $order->status_since->lt(now()->subHours(72)) ? 'text-red-600 font-semibold' : 'text-gray-500' }}">{{ $order->status_since->diffForHumans() }}</span>
                                        <span class="block text-gray-700">{{ money($order->total_amount) }}</span>
                                        <form method="POST" action="{{ route('orders.whatsapp', [$order, 'budget']) }}" target="_blank">@csrf
                                            <button type="submit" class="px-2 py-1 rounded border border-green-300 text-green-700 hover:bg-green-50">Reenviar presupuesto</button>
                                        </form>
                                    </div>
                                </div>
                            </li>
                    @empty
                        <li class="px-4 py-3 text-xs text-gray-400">Ningún presupuesto pendiente.</li>
                    @endforelse
                </ul>
            </div>

            <div class="bg-white rounded-xl shadow-sm border">
                <div class="px-4 py-3 border-b flex justify-between text-sm font-semibold text-gray-800">
                    <span>Recibidos hoy</span><span>{{ $counter['today']->count() }}</span>
                </div>
                <ul class="divide-y">
                    @forelse ($counter['today'] as $order)
                        <li class="px-4 py-2.5">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <a href="{{ route('orders.show', $order) }}" class="min-w-0 text-sm">
                                        <span class="font-medium text-blue-700">{{ $order->order_number }}</span>
                                        <span class="text-gray-700">· {{ $order->client?->name }}</span>
                                        <span class="block text-xs text-gray-500 truncate">{{ $order->brand?->name ?? $order->brand_text }} {{ $order->deviceModel?->name ?? $order->model_text }}{{ $order->client?->phone ? ' · ' . $order->client->phone : '' }}</span>
                                    </a>
                                    <span class="text-xs {{ $order->user ? 'text-gray-500' : 'text-red-600' }}">{{ $order->user?->name ?? 'sin técnico' }}</span>
                                </div>
                            </li>
                    @empty
                        <li class="px-4 py-3 text-xs text-gray-400">Todavía no se recibe nada hoy.</li>
                    @endforelse
                </ul>
                <a href="{{ route('orders.create') }}" class="block border-t px-4 py-2.5 text-center text-sm text-blue-600 hover:bg-gray-50">+ Recibir equipo</a>
            </div>
        </div>
    </div>
    @endif

    @if ($myWork)
    <div class="mb-6">
        <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
            <h3 class="text-lg font-semibold text-gray-800">Mi trabajo</h3>
            <a href="{{ route('commissions.index') }}" class="text-sm text-gray-600 hover:text-blue-600">Comisión del mes: <strong>{{ money($myWork['commission']) }}</strong></a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ($myWork['stages'] as $stage => $stageOrders)
                <div class="bg-white rounded-xl shadow-sm border">
                    <div class="px-4 py-3 border-b flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-800">{{ $stage }}</span>
                        <span class="text-xs font-bold rounded-full px-2 py-0.5 {{ $stageOrders->count() ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500' }}">{{ $stageOrders->count() }}</span>
                    </div>
                    <ul class="divide-y">
                        @forelse ($stageOrders as $order)
                            @php $days = $order->entry_date ? (int) $order->entry_date->diffInDays(today()) : 0; @endphp
                            <li>
                                <a href="{{ route('orders.show', $order) }}" class="block px-4 py-2.5 hover:bg-gray-50">
                                    <span class="flex justify-between text-sm">
                                        <span class="font-medium text-blue-700">{{ $order->order_number }}</span>
                                        <span class="text-xs {{ $days > 7 ? 'text-red-600 font-semibold' : ($days > 3 ? 'text-amber-600' : 'text-gray-400') }}">{{ $days }} d</span>
                                    </span>
                                    <span class="block text-xs text-gray-600 truncate">{{ $order->brand?->name ?? $order->brand_text }} {{ $order->deviceModel?->name ?? $order->model_text }} · {{ $order->client?->name }}</span>
                                    <span class="block text-xs text-gray-400 truncate">{{ $order->declared_fault }}</span>
                                </a>
                            </li>
                        @empty
                            <li class="px-4 py-3 text-xs text-gray-400">Nada aquí.</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>

        @if ($myWork['available']->count())
            <div class="mt-4 bg-white rounded-xl shadow-sm border border-indigo-200">
                <div class="px-4 py-3 border-b bg-indigo-50 text-sm font-semibold text-indigo-900">Órdenes sin técnico ({{ $myWork['available']->count() }})</div>
                <ul class="divide-y">
                    @foreach ($myWork['available'] as $order)
                        <li class="px-4 py-2.5 flex flex-wrap items-center justify-between gap-2">
                            <a href="{{ route('orders.show', $order) }}" class="text-sm">
                                <span class="font-medium text-blue-700">{{ $order->order_number }}</span>
                                <span class="text-gray-600">· {{ \App\Models\Order::deviceTypes()[$order->device_type] ?? $order->device_type }} {{ $order->brand?->name ?? $order->brand_text }} · {{ \Illuminate\Support\Str::limit($order->declared_fault, 40) }}</span>
                            </a>
                            <form method="POST" action="{{ route('orders.take', $order) }}">
                                @csrf
                                <button type="submit" class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg">Tomar</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
    @endif

    {{-- El técnico ya ve su cola en "Mi trabajo"; los totales generales son para recepción y administración. --}}
    @unless ($myWork)
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

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <p class="text-sm font-medium text-gray-500">Recibidas hoy</p>
            <p class="text-3xl font-bold text-blue-600 mt-1">{{ $stats['orders_today'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <p class="text-sm font-medium text-gray-500">Entregadas hoy</p>
            <p class="text-3xl font-bold text-gray-800 mt-1">{{ $stats['delivered_today'] }}</p>
        </div>
        @can('manage-invoices')
        <div class="bg-white rounded-xl shadow-sm border p-6 flex flex-col justify-between">
            <p class="text-sm font-medium text-gray-500">Caja del día</p>
            <a href="{{ route('cash.index') }}" class="mt-2 inline-block text-sm text-blue-600 hover:text-blue-800 font-medium">Ver ingresos y cerrar caja →</a>
        </div>
        @endcan
    </div>

    @endunless

    @if ($staleOrders->count() || $awaitingApproval->count())
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        @if ($awaitingApproval->count())
        <div class="bg-white rounded-xl shadow-sm border border-orange-200">
            <div class="px-6 py-4 border-b bg-orange-50">
                <h3 class="text-base font-semibold text-orange-800">Presupuestos sin respuesta (más de {{ $approvalHours }} h)</h3>
                <p class="text-xs text-orange-700 mt-0.5">Contactar por teléfono al cliente.</p>
            </div>
            <ul class="divide-y">
                @foreach ($awaitingApproval as $order)
                <li class="px-6 py-3 flex items-center justify-between text-sm">
                    <div>
                        <a href="{{ route('orders.show', $order) }}" class="text-blue-600 hover:underline font-medium">{{ $order->order_number }}</a>
                        <span class="text-gray-600">· {{ $order->client?->name ?? '—' }}</span>
                        @if ($order->client?->phone)
                            <span class="text-gray-400">· {{ $order->client->phone }}</span>
                        @endif
                    </div>
                    <span class="text-xs text-orange-700 whitespace-nowrap">{{ $order->waiting_since->diffForHumans() }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        @if ($staleOrders->count())
        <div class="bg-white rounded-xl shadow-sm border border-red-200">
            <div class="px-6 py-4 border-b bg-red-50">
                <h3 class="text-base font-semibold text-red-800">Equipos sin movimiento (más de {{ $staleDays }} días)</h3>
                <p class="text-xs text-red-700 mt-0.5">Posible demora o abandono.</p>
            </div>
            <ul class="divide-y">
                @foreach ($staleOrders as $order)
                <li class="px-6 py-3 flex items-center justify-between text-sm">
                    <div>
                        <a href="{{ route('orders.show', $order) }}" class="text-blue-600 hover:underline font-medium">{{ $order->order_number }}</a>
                        <span class="text-gray-600">· {{ $order->client?->name ?? '—' }}</span>
                        <span class="text-gray-400">· {{ $statuses[$order->status] ?? $order->status }}</span>
                    </div>
                    <span class="text-xs text-red-700 whitespace-nowrap">{{ $order->updated_at->diffForHumans() }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
    @endif

    @if ($duePlans)
    <a href="{{ route('maintenance-plans.index') }}" class="mt-6 block bg-white rounded-xl shadow-sm border border-amber-200 px-6 py-4 hover:bg-amber-50">
        <span class="text-base font-semibold text-amber-800">{{ $duePlans }} {{ $duePlans === 1 ? 'mantenimiento preventivo vencido o por vencer' : 'mantenimientos preventivos vencidos o por vencer' }}</span>
        <span class="block text-xs text-amber-700 mt-0.5">Avisa a los clientes y agenda las visitas →</span>
    </a>
    @endif

    @if ($todayAppointments->count() || $unassignedAppointments)
    <div class="mt-6 bg-white rounded-xl shadow-sm border border-blue-200">
        <div class="px-6 py-4 border-b bg-blue-50 flex items-center justify-between">
            <h3 class="text-base font-semibold text-blue-900">Citas de hoy ({{ $todayAppointments->count() }})</h3>
            <a href="{{ route('appointments.index') }}" class="text-sm text-blue-800 hover:underline font-medium">Ver agenda →</a>
        </div>
        @if ($unassignedAppointments)
            <p class="px-6 py-2 text-sm text-red-700 bg-red-50 border-b">{{ $unassignedAppointments }} citas próximas sin técnico asignado.</p>
        @endif
        <ul class="divide-y">
            @foreach ($todayAppointments as $appointment)
            <li>
                <a href="{{ route('appointments.show', $appointment) }}" class="px-6 py-3 flex flex-wrap items-center justify-between gap-2 text-sm hover:bg-gray-50">
                    <span><span class="font-semibold">{{ $appointment->scheduled_at->format('H:i') }}</span> · {{ $appointment->title }} · {{ $appointment->client?->name }}</span>
                    <span class="text-xs text-gray-500">{{ $appointment->address ?? '' }}{{ $appointment->user ? ' · ' . $appointment->user->name : '' }}</span>
                </a>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @if ($lowStockParts->count())
    <div class="mt-6 bg-white rounded-xl shadow-sm border border-red-200">
        <div class="px-6 py-4 border-b bg-red-50 flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold text-red-800">Repuestos con stock bajo</h3>
                <p class="text-xs text-red-700 mt-0.5">En o por debajo del stock mínimo.</p>
            </div>
            <a href="{{ route('spare-parts.index', ['low' => 1]) }}" class="text-sm text-red-800 hover:underline font-medium">Ver todos →</a>
        </div>
        <ul class="divide-y">
            @foreach ($lowStockParts as $part)
            <li class="px-6 py-3 flex items-center justify-between text-sm">
                <a href="{{ route('spare-parts.show', $part) }}" class="text-blue-600 hover:underline font-medium">{{ $part->name }}</a>
                <span class="text-xs text-red-700 whitespace-nowrap">
                    @foreach ($part->stocks->filter(fn ($s) => $s->quantity <= $part->min_stock && (\App\Support\BranchContext::current() === null || $s->branch_id === \App\Support\BranchContext::current())) as $stock)
                        {{ \App\Models\Branch::isMultiple() ? $stock->branch?->code . ': ' : 'stock ' }}{{ $stock->quantity }}{{ $loop->last ? '' : ' · ' }}
                    @endforeach
                    / mín. {{ $part->min_stock }}
                </span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @if ($expiringWarranties->count())
    <div class="mt-6 bg-white rounded-xl shadow-sm border border-amber-200">
        <div class="px-6 py-4 border-b bg-amber-50 flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold text-amber-800">Garantías por vencer (próximos {{ $warrantyAlertDays }} días)</h3>
                <p class="text-xs text-amber-700 mt-0.5">Revisa si estos clientes tuvieron problemas o regresos.</p>
            </div>
            <a href="{{ route('warranties.index', ['status' => 'expiring']) }}" class="text-sm text-amber-800 hover:underline font-medium">Ver todas →</a>
        </div>
        <ul class="divide-y">
            @foreach ($expiringWarranties as $warranty)
            <li class="px-6 py-3 flex items-center justify-between text-sm">
                <div>
                    <a href="{{ route('orders.show', $warranty->order_id) }}" class="text-blue-600 hover:underline font-medium">{{ $warranty->order?->order_number }}</a>
                    <span class="text-gray-600">· {{ $warranty->order?->client?->name ?? '—' }}</span>
                    @if ($warranty->order?->client?->phone)
                        <span class="text-gray-400">· {{ $warranty->order->client->phone }}</span>
                    @endif
                </div>
                <span class="text-xs text-amber-700 whitespace-nowrap">vence {{ $warranty->end_date->format('d/m/Y') }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

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
                    <p class="text-3xl font-bold text-emerald-600 mt-1">{{ money($stats['revenue']) }}</p>
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
                        <td class="px-6 py-3">{{ money($order->total_amount) }}</td>
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
