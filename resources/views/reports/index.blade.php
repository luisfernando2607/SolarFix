@extends('layouts.app')

@section('title', 'Reportes')

@php
    $money = fn ($v) => money($v);
    $field = 'px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white';
    $maxMonth = max(1, $monthly->max('amount'));
    $peakKey = $monthly->sortByDesc('amount')->first()['key'] ?? null;
    $currentKey = now()->format('Y-m');
@endphp

@section('content')
<div class="p-6">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Reportes</h2>
            <p class="text-gray-500">Del {{ $from->format('d/m/Y') }} al {{ $to->format('d/m/Y') }} · {{ $branchName ?? 'Todas las sucursales' }}</p>
        </div>
        {{-- Filtros en una sola fila sobre los datos --}}
        <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="{{ $field }}" aria-label="Desde">
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="{{ $field }}" aria-label="Hasta">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Ver</button>
            <a href="{{ route('reports.index', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]) }}" class="text-sm text-gray-600 hover:text-blue-600 px-2">Este mes</a>
            <a href="{{ route('reports.index', ['from' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'to' => now()->subMonthNoOverflow()->endOfMonth()->toDateString()]) }}" class="text-sm text-gray-600 hover:text-blue-600 px-2">Mes anterior</a>
            <a href="{{ route('reports.index', ['from' => now()->startOfYear()->toDateString(), 'to' => now()->toDateString()]) }}" class="text-sm text-gray-600 hover:text-blue-600 px-2">Este año</a>
            <span class="text-gray-300">|</span>
            <a href="{{ route('reports.export', ['dataset' => 'orders', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="text-sm bg-white border px-3 py-2 rounded-lg hover:bg-gray-50">Excel: órdenes</a>
            <a href="{{ route('reports.export', ['dataset' => 'payments', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="text-sm bg-white border px-3 py-2 rounded-lg hover:bg-gray-50">Excel: pagos</a>
            <a href="{{ route('reports.export', ['dataset' => 'sales', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="text-sm bg-white border px-3 py-2 rounded-lg hover:bg-gray-50">Excel: ventas</a>
        </form>
    </div>

    {{-- Indicadores del período --}}
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
        @foreach ([
            ['Ingresos cobrados' . ($kpis['sales'] > 0 ? ' (ventas ' . $money($kpis['sales']) . ')' : ''), $money($kpis['revenue'])],
            ['Órdenes recibidas', $kpis['received']],
            ['Órdenes entregadas', $kpis['delivered']],
            ['Ticket promedio', $kpis['avg_ticket'] !== null ? $money($kpis['avg_ticket']) : '—'],
            ['Días de reparación (prom.)', $kpis['avg_days'] ?? '—'],
            ['Satisfacción (' . $satisfaction['count'] . ')', $satisfaction['average'] !== null ? $satisfaction['average'] . ' / 5' : '—'],
        ] as [$label, $value])
            <div class="bg-white rounded-xl shadow-sm border p-5">
                <p class="text-sm text-gray-500">{{ $label }}</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
        {{-- Ingresos por mes: una sola serie, un solo tono; tooltip al pasar el mouse y tabla equivalente --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-base font-semibold text-gray-800">Ingresos cobrados por mes</h3>
            <p class="text-xs text-gray-500 mb-4">Últimos 12 meses</p>
            <div class="relative">
                {{-- pt-5 deja espacio para la etiqueta de la barra más alta; las etiquetas no restan altura a las barras --}}
                <div class="flex items-end gap-1 h-52 pt-5 border-b border-gray-300" role="img"
                    aria-label="Gráfico de barras de ingresos por mes; los valores están en la tabla de abajo">
                    @foreach ($monthly as $month)
                        @php $height = $month['amount'] > 0 ? max(2, round($month['amount'] / $maxMonth * 100, 1)) : 0; @endphp
                        <div class="group relative flex-1 h-full flex flex-col justify-end items-center">
                            <div class="relative w-full max-w-10 rounded-t bg-blue-600 group-hover:bg-blue-700 transition-colors" style="height: {{ $height }}%">
                                @if ($month['key'] === $peakKey && $month['amount'] > 0 || $month['key'] === $currentKey)
                                    <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 text-[10px] text-gray-500 whitespace-nowrap">{{ $money($month['amount']) }}</span>
                                @endif
                            </div>
                            <div class="pointer-events-none absolute bottom-full mb-1 hidden group-hover:block z-10 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white shadow">
                                {{ $month['full'] }}: {{ $money($month['amount']) }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex gap-1 mt-1">
                    @foreach ($monthly as $month)
                        <span class="flex-1 text-center text-[10px] text-gray-500">{{ $month['label'] }}</span>
                    @endforeach
                </div>
            </div>
            <details class="mt-3">
                <summary class="text-xs text-blue-600 cursor-pointer">Ver como tabla</summary>
                <div class="overflow-x-auto">
                <table class="mt-2 w-full text-sm">
                    @foreach ($monthly as $month)
                        <tr class="border-b"><td class="py-1 text-gray-600">{{ $month['full'] }}</td><td class="py-1 text-right text-gray-800">{{ $money($month['amount']) }}</td></tr>
                    @endforeach
                </table>
                </div>
            </details>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-4">Ingresos por método de pago</h3>
            @php $maxMethod = max(1, $byMethod->max('amount')); @endphp
            <ul class="space-y-3">
                @foreach ($byMethod as $row)
                    <li>
                        <div class="flex justify-between text-sm"><span class="text-gray-600">{{ $row['label'] }}</span><span class="font-medium text-gray-800">{{ $money($row['amount']) }}</span></div>
                        <div class="mt-1 h-2 rounded bg-gray-100"><div class="h-2 rounded bg-blue-600" style="width: {{ round($row['amount'] / $maxMethod * 100, 1) }}%"></div></div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50/50">
                <h3 class="text-base font-semibold text-gray-800">Por técnico</h3>
                <p class="text-xs text-gray-500">Órdenes entregadas en el período</p>
            </div>
            @php $maxTech = max(1, $byTechnician->max('total')); @endphp
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b text-left text-gray-600"><th class="px-6 py-2 font-semibold">Técnico</th><th class="px-3 py-2 font-semibold text-right">Órdenes</th><th class="px-3 py-2 font-semibold text-right">Total</th><th class="px-6 py-2 font-semibold text-right">Comisión</th></tr></thead>
                <tbody>
                    @forelse ($byTechnician as $row)
                        <tr class="border-b">
                            <td class="px-6 py-2">
                                {{ $row['name'] }}
                                <div class="mt-1 h-1.5 rounded bg-gray-100"><div class="h-1.5 rounded bg-blue-600" style="width: {{ round($row['total'] / $maxTech * 100, 1) }}%"></div></div>
                            </td>
                            <td class="px-3 py-2 text-right">{{ $row['orders'] }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ $money($row['total']) }}</td>
                            <td class="px-6 py-2 text-right text-gray-600">{{ $money($row['commission']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-6 text-center text-gray-500">Sin entregas en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50/50">
                <h3 class="text-base font-semibold text-gray-800">Por tipo de equipo</h3>
                <p class="text-xs text-gray-500">Órdenes recibidas en el período</p>
            </div>
            @php $maxDevice = max(1, $byDevice->max('orders')); @endphp
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b text-left text-gray-600"><th class="px-6 py-2 font-semibold">Tipo</th><th class="px-3 py-2 font-semibold text-right">Órdenes</th><th class="px-6 py-2 font-semibold text-right">Total presupuestado</th></tr></thead>
                <tbody>
                    @forelse ($byDevice as $row)
                        <tr class="border-b">
                            <td class="px-6 py-2">
                                {{ $row['label'] }}
                                <div class="mt-1 h-1.5 rounded bg-gray-100"><div class="h-1.5 rounded bg-blue-600" style="width: {{ round($row['orders'] / $maxDevice * 100, 1) }}%"></div></div>
                            </td>
                            <td class="px-3 py-2 text-right">{{ $row['orders'] }}</td>
                            <td class="px-6 py-2 text-right font-medium">{{ $money($row['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-6 text-center text-gray-500">Sin órdenes en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div class="mt-6 bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-6 py-4 border-b bg-gray-50/50">
            <h3 class="text-base font-semibold text-gray-800">Repuestos más usados</h3>
            <p class="text-xs text-gray-500">En órdenes recibidas en el período</p>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="border-b text-left text-gray-600"><th class="px-6 py-2 font-semibold">Repuesto</th><th class="px-3 py-2 font-semibold text-right">Unidades</th><th class="px-6 py-2 font-semibold text-right">Vendido</th></tr></thead>
            <tbody>
                @forelse ($topParts as $row)
                    <tr class="border-b"><td class="px-6 py-2">{{ $row['name'] }}</td><td class="px-3 py-2 text-right">{{ $row['quantity'] }}</td><td class="px-6 py-2 text-right font-medium">{{ $money($row['total']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="px-6 py-6 text-center text-gray-500">Sin repuestos del inventario en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{-- Utilidad estimada: cascada de ingresos y costos del período --}}
    <div class="bg-white rounded-xl shadow-sm border p-6 mt-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h3 class="text-base font-semibold text-gray-800">Utilidad estimada del período</h3>
            <p class="text-2xl font-bold {{ $profit['profit'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                {{ $money($profit['profit']) }}
                @if ($profit['margin'] !== null)<span class="text-sm font-medium text-gray-500">· margen {{ $profit['margin'] }}%</span>@endif
            </p>
        </div>
        <div class="overflow-x-auto mt-3">
            <table class="w-full text-sm">
                <tbody class="divide-y">
                    @foreach ([
                        ['Órdenes entregadas (sin IVA)', $profit['orders_revenue'], '+'],
                        ['Costo de repuestos usados', $profit['parts_cost'], '−'],
                        ['Comisiones de técnicos', $profit['commissions'], '−'],
                        ['Ventas de mostrador', $profit['sales_revenue'], '+'],
                        ['Costo de lo vendido', $profit['sales_cost'], '−'],
                        ['Gastos registrados en caja', $profit['expenses'], '−'],
                    ] as [$label, $amount, $sign])
                        <tr>
                            <td class="py-2 text-gray-600">{{ $label }}</td>
                            <td class="py-2 text-right font-medium {{ $sign === '−' ? 'text-gray-700' : 'text-gray-900' }}">{{ $sign }} {{ $money($amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 mt-2">Estimación de gestión: usa el costo promedio de cada repuesto al momento de usarlo o venderlo. No reemplaza la contabilidad.</p>
    </div>

    @if ($satisfaction['latest']->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border p-6 mt-6">
            <h3 class="text-base font-semibold text-gray-800 mb-3">Últimos comentarios de clientes</h3>
            <ul class="divide-y text-sm">
                @foreach ($satisfaction['latest'] as $review)
                    <li class="py-2 flex flex-wrap items-baseline gap-x-3">
                        <span class="text-amber-500" aria-label="{{ $review->rating }} de 5">{{ str_repeat('★', $review->rating) }}<span class="text-gray-300">{{ str_repeat('★', 5 - $review->rating) }}</span></span>
                        <a href="{{ route('orders.show', $review->order_id) }}" class="text-blue-600 hover:underline">{{ $review->order?->order_number }}</a>
                        <span class="text-gray-700 flex-1 min-w-0">{{ $review->comment }}</span>
                        <span class="text-xs text-gray-400">{{ $review->created_at->format('d/m/Y') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
