@extends('layouts.app')

@section('title', 'Comisiones')

@php $field = 'px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white'; @endphp

@section('content')
<div class="p-6">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">{{ $canManage ? 'Comisiones de técnicos' : 'Mis comisiones' }}</h2>
            <p class="text-gray-500">Se generan al entregar la orden asignada. Base: {{ strtolower(\App\Models\Commission::bases()[\App\Models\Setting::get('commission_base', 'service')]) }}.</p>
        </div>
        <form method="GET" action="{{ route('commissions.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="month" name="period" value="{{ $period }}" class="{{ $field }}">
            @if ($canManage)
                <select name="user_id" class="{{ $field }}">
                    <option value="">Todos los técnicos</option>
                    @foreach ($technicians as $technician)
                        <option value="{{ $technician->id }}" {{ (string) request('user_id') === (string) $technician->id ? 'selected' : '' }}>{{ $technician->name }}</option>
                    @endforeach
                </select>
            @endif
            <select name="status" class="{{ $field }}">
                <option value="">Pendientes y pagadas</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pendientes</option>
                <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Pagadas</option>
            </select>
            <button type="submit" class="bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium">Ver</button>
        </form>
    </div>

    @if ($canManage)
        <form method="POST" action="{{ route('commissions.advances.store') }}" class="mb-6 bg-white rounded-xl shadow-sm border p-4 flex flex-wrap items-end gap-2">
            @csrf
            <input type="hidden" name="period" value="{{ $period }}">
            <span class="text-sm font-medium text-gray-700 mr-2">Registrar adelanto</span>
            <select name="user_id" required class="{{ $field }}">
                <option value="">Técnico...</option>
                @foreach ($technicians as $technician)
                    <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                @endforeach
            </select>
            <input type="number" name="amount" step="0.01" min="0.01" required placeholder="Monto" class="{{ $field }} w-28">
            <input type="text" name="notes" maxlength="255" placeholder="Detalle (opcional)" class="{{ $field }} flex-1 min-w-40">
            <button type="submit" class="bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium">Registrar</button>
        </form>
    @endif

    @if ($advances->isNotEmpty())
        <div class="mb-6 bg-white rounded-xl shadow-sm border">
            <h3 class="px-6 py-3 border-b text-sm font-semibold text-gray-800">Adelantos del mes</h3>
            <ul class="divide-y">
                @foreach ($advances as $advance)
                    <li class="px-6 py-2 flex flex-wrap justify-between gap-2 text-sm">
                        <span>{{ $advance->user?->name }} · {{ $advance->created_at->format('d/m/Y') }}{{ $advance->notes ? ' · ' . $advance->notes : '' }}</span>
                        <span class="{{ $advance->is_settled ? 'text-gray-400' : 'font-medium text-gray-800' }}">
                            {{ money($advance->amount) }} {{ $advance->is_settled ? '(descontado)' : '' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($summary->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border p-8 text-center text-gray-500 text-sm">
            No hay comisiones en {{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $period)->translatedFormat('F Y') }}.
            @if ($canManage)
                <p class="mt-1 text-xs">Configura el tipo y valor de comisión en cada usuario técnico.</p>
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            @foreach ($summary as $userId => $row)
                <div class="bg-white rounded-xl shadow-sm border p-5">
                    <p class="font-semibold text-gray-800">{{ $row['user']?->name ?? '—' }}</p>
                    <p class="text-xs text-gray-500">{{ $row['orders'] }} órdenes entregadas</p>
                    <div class="mt-3 flex justify-between text-sm">
                        <span class="text-gray-500">Pendiente</span>
                        <span class="font-bold text-amber-600">{{ money($row['pending']) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Pagado</span>
                        <span class="font-semibold text-green-700">{{ money($row['paid']) }}</span>
                    </div>
                    @if ($row['advances'] > 0)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Adelantos</span>
                            <span class="font-semibold text-gray-700">−{{ money($row['advances']) }}</span>
                        </div>
                        @if ($row['pending'] > 0)
                            <div class="flex justify-between text-sm border-t mt-1 pt-1">
                                <span class="text-gray-700 font-medium">A pagar</span>
                                <span class="font-bold text-gray-900">{{ money($row['to_pay']) }}</span>
                            </div>
                        @endif
                    @endif
                    @if ($canManage && $row['pending'] > 0)
                        <form method="POST" action="{{ route('commissions.pay') }}" class="mt-3"
                            onsubmit="return confirm({{ \Illuminate\Support\Js::from('¿Marcar como pagadas las comisiones pendientes de ' . ($row['user']?->name ?? '') . '?') }})">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $userId }}">
                            <input type="hidden" name="period" value="{{ $period }}">
                            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                Pagar {{ money($row['to_pay']) }}
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50/50 text-left">
                            <th class="px-4 py-3 font-semibold text-gray-600">Orden</th>
                            @if ($canManage) <th class="px-4 py-3 font-semibold text-gray-600">Técnico</th> @endif
                            <th class="px-4 py-3 font-semibold text-gray-600">Cliente</th>
                            <th class="px-4 py-3 font-semibold text-gray-600 text-right">Base</th>
                            <th class="px-4 py-3 font-semibold text-gray-600">Tarifa</th>
                            <th class="px-4 py-3 font-semibold text-gray-600 text-right">Comisión</th>
                            <th class="px-4 py-3 font-semibold text-gray-600">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($commissions as $commission)
                            <tr class="border-b">
                                <td class="px-4 py-3">
                                    @if ($commission->order && ! $commission->order->trashed())
                                        <a href="{{ route('orders.show', $commission->order) }}" class="text-blue-600 hover:underline">{{ $commission->order->order_number }}</a>
                                    @else
                                        {{ $commission->order?->order_number ?? '—' }}
                                    @endif
                                </td>
                                @if ($canManage) <td class="px-4 py-3">{{ $commission->user?->name }}</td> @endif
                                <td class="px-4 py-3">{{ $commission->order?->client?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">{{ money($commission->order_total) }}</td>
                                <td class="px-4 py-3 text-gray-500">
                                    {{ $commission->commission_type === 'percent' ? rtrim(rtrim(number_format($commission->commission_value, 2), '0'), '.') . '%' : money($commission->commission_value) . ' fijo' }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">{{ money($commission->commission_amount) }}</td>
                                <td class="px-4 py-3">
                                    @if ($commission->is_paid)
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Pagada {{ $commission->paid_at?->format('d/m') }}</span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Pendiente</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
