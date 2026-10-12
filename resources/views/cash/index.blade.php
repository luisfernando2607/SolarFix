@extends('layouts.app')

@section('title', 'Caja del día')

@section('content')
<div class="p-6">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Caja del día</h2>
            <p class="text-gray-500">Ingresos registrados el {{ $date->translatedFormat('l d \d\e F \d\e Y') }}</p>
        </div>
        <form method="GET" action="{{ route('cash.index') }}" class="flex items-center gap-2">
            <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            <button type="submit" class="bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium transition">Ver</button>
        </form>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        @foreach ($methods as $key => $label)
            <div class="bg-white rounded-xl shadow-sm border p-5">
                <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ money($totals[$key]) }}</p>
            </div>
        @endforeach
        <div class="bg-white rounded-xl shadow-sm border border-emerald-200 p-5">
            <p class="text-sm font-medium text-emerald-700">Total del día</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1">{{ money($totals['total']) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50/50">
                <h3 class="text-base font-semibold text-gray-800">Pagos del día ({{ $payments->count() }})</h3>
            </div>
            @if ($payments->count())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50/50 text-left">
                            <th class="px-6 py-3 font-semibold text-gray-600">Hora</th>
                            <th class="px-6 py-3 font-semibold text-gray-600">Orden</th>
                            <th class="px-6 py-3 font-semibold text-gray-600">Cliente</th>
                            <th class="px-6 py-3 font-semibold text-gray-600">Método</th>
                            <th class="px-6 py-3 font-semibold text-gray-600">Referencia</th>
                            <th class="px-6 py-3 font-semibold text-gray-600 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                        <tr class="border-b">
                            <td class="px-6 py-3 text-gray-500">{{ $payment->paid_at->format('H:i') }}</td>
                            <td class="px-6 py-3">
                                @if ($payment->order)
                                    <a href="{{ route('orders.show', $payment->order) }}" class="text-blue-600 hover:underline font-medium">{{ $payment->order->order_number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-6 py-3">{{ $payment->order?->client?->name ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $methods[$payment->method] ?? $payment->method }}</td>
                            <td class="px-6 py-3 text-gray-500">{{ $payment->reference ?: '—' }}</td>
                            <td class="px-6 py-3 text-right font-medium">{{ money($payment->amount) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
                <p class="px-6 py-8 text-center text-gray-500 text-sm">No hay pagos registrados en esta fecha.</p>
            @endif
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border overflow-hidden lg:order-3">
            <div class="px-6 py-4 border-b bg-gray-50/50">
                <h3 class="text-base font-semibold text-gray-800">Movimientos de efectivo</h3>
                <p class="text-xs text-gray-500">Fondo inicial, gastos, retiros y otros ingresos que no vienen de una orden.</p>
            </div>
            @if ($movements->count())
                <ul class="divide-y">
                    @foreach ($movements as $movement)
                        <li class="px-6 py-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span>
                                <span class="font-medium">{{ \App\Models\CashMovement::types()[$movement->type] }}</span>
                                · {{ $movement->description }}
                                <span class="text-xs text-gray-400">· {{ $movement->registeredBy?->name }} {{ $movement->created_at->format('H:i') }}</span>
                            </span>
                            <span class="flex items-center gap-3">
                                <span class="font-semibold {{ $movement->signedAmount() < 0 ? 'text-red-600' : 'text-green-700' }}">{{ $movement->signedAmount() < 0 ? '−' : '+' }}{{ money($movement->amount) }}</span>
                                @if (Auth::user()->isAdmin() || Auth::user()->isSuperAdmin())
                                    <form method="POST" action="{{ route('cash.movements.destroy', $movement) }}" onsubmit="return confirm('¿Eliminar este movimiento?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-500 hover:text-red-700">Eliminar</button>
                                    </form>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <form method="POST" action="{{ route('cash.movements.store') }}" class="px-6 py-4 border-t flex flex-wrap items-end gap-2">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <select name="type" class="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                    @foreach (\App\Models\CashMovement::types() as $key => $label)
                        <option value="{{ $key }}" {{ old('type', $movements->where('type', 'opening')->isEmpty() ? 'opening' : 'expense') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="number" name="amount" step="0.01" min="0.01" required placeholder="Monto" value="{{ old('amount') }}"
                    class="w-28 px-3 py-2 border border-gray-300 rounded-lg text-sm @error('amount') border-red-500 @enderror">
                <input type="text" name="description" maxlength="255" required placeholder="Detalle (ej. compra de pegamento)" value="{{ old('description') }}"
                    class="flex-1 min-w-48 px-3 py-2 border border-gray-300 rounded-lg text-sm @error('description') border-red-500 @enderror">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Registrar</button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6 h-fit">
            <h3 class="text-base font-semibold text-gray-800 mb-1">Cuadre de caja</h3>
            <p class="text-xs text-gray-500 mb-3">Cuenta el efectivo físico y compáralo con lo que debería haber.</p>
            <dl class="mb-4 text-sm space-y-1">
                @foreach ([
                    ['Fondo inicial', $cash['opening'], ''],
                    ['Cobros en efectivo', $cash['payments'], '+'],
                    ['Otros ingresos', $cash['income'], '+'],
                    ['Gastos', $cash['expenses'], '−'],
                    ['Retiros / depósitos', $cash['withdrawals'], '−'],
                ] as [$label, $value, $sign])
                    <div class="flex justify-between"><dt class="text-gray-500">{{ $sign }} {{ $label }}</dt><dd class="text-gray-800">{{ money($value) }}</dd></div>
                @endforeach
                <div class="flex justify-between border-t pt-1 font-semibold"><dt>Efectivo esperado</dt><dd>{{ money($cash['expected']) }}</dd></div>
            </dl>

            @if ($closing)
                <div class="mb-4 rounded-lg border p-3 text-sm {{ (float) $closing->difference == 0 ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200' }}">
                    <p class="font-medium text-gray-800">Cerrada por {{ $closing->closedBy?->name ?? '—' }} · {{ $closing->updated_at->format('d/m/Y H:i') }}</p>
                    <p class="mt-1">Contado: {{ money($closing->counted_cash) }} · Diferencia:
                        <span class="font-semibold {{ (float) $closing->difference == 0 ? 'text-green-700' : 'text-amber-700' }}">{{ money($closing->difference) }}</span>
                    </p>
                    @if ($closing->notes)
                        <p class="mt-1 text-gray-600">{{ $closing->notes }}</p>
                    @endif
                    @if ((float) $closing->expected_cash != $cash['expected'])
                        <p class="mt-2 text-xs text-red-700">Hubo movimientos de efectivo después del cierre (esperado al cerrar: {{ money($closing->expected_cash) }}). Vuelve a cerrar la caja.</p>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('cash.store') }}">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <div class="mb-4">
                    <label for="counted_cash" class="block text-sm font-medium text-gray-700 mb-1">Efectivo contado *</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">{{ \App\Models\Setting::get('currency_symbol', '$') }}</span>
                        <input type="number" step="0.01" min="0" id="counted_cash" name="counted_cash" required
                            value="{{ old('counted_cash', $closing?->counted_cash) }}"
                            class="w-full pl-7 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('counted_cash') border-red-500 @enderror">
                    </div>
                    @error('counted_cash') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="mb-4">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Obligatorio si hay diferencia"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('notes') border-red-500 @enderror">{{ old('notes', $closing?->notes) }}</textarea>
                    @error('notes') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition">
                    {{ $closing ? 'Actualizar cierre' : 'Cerrar caja' }}
                </button>
            </form>
            <p class="text-xs text-gray-500 mt-3">Para el depósito: transferencias y tarjeta ya están en banco ({{ money($totals['transfer'] + $totals['card']) }}).</p>
        </div>
    </div>

    @if ($history->count())
    <div class="mt-6 bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-6 py-4 border-b bg-gray-50/50">
            <h3 class="text-base font-semibold text-gray-800">Últimos cierres</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50/50 text-left">
                        <th class="px-6 py-3 font-semibold text-gray-600">Fecha</th>
                        <th class="px-6 py-3 font-semibold text-gray-600 text-right">Efectivo sistema</th>
                        <th class="px-6 py-3 font-semibold text-gray-600 text-right">Contado</th>
                        <th class="px-6 py-3 font-semibold text-gray-600 text-right">Diferencia</th>
                        <th class="px-6 py-3 font-semibold text-gray-600">Cerró</th>
                        <th class="px-6 py-3 font-semibold text-gray-600">Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($history as $row)
                    <tr class="border-b">
                        <td class="px-6 py-3">
                            <a href="{{ route('cash.index', ['date' => $row->closing_date->toDateString()]) }}" class="text-blue-600 hover:underline">{{ $row->closing_date->format('d/m/Y') }}</a>
                        </td>
                        <td class="px-6 py-3 text-right">{{ money($row->expected_cash) }}</td>
                        <td class="px-6 py-3 text-right">{{ money($row->counted_cash) }}</td>
                        <td class="px-6 py-3 text-right font-medium {{ (float) $row->difference == 0 ? 'text-green-700' : 'text-amber-700' }}">{{ money($row->difference) }}</td>
                        <td class="px-6 py-3">{{ $row->closedBy?->name ?? '—' }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ $row->notes ?: '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
