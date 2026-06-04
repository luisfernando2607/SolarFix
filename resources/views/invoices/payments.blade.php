@extends('layouts.app')

@section('title', 'Pagos')

@section('content')
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Pagos Registrados</h2>
            <p class="text-gray-500">Historial de pagos en todas las órdenes</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('invoices.index') }}" class="bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium transition">
                Facturas
            </a>
            <a href="{{ route('invoices.payments') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                Pagos
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-6 py-3 border-b bg-gray-50/50 flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('invoices.payments') }}" class="flex flex-wrap items-center gap-2 w-full">
                <select name="method" class="px-3 py-1.5 text-xs border rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos los métodos</option>
                    <option value="cash" {{ request('method') === 'cash' ? 'selected' : '' }}>Efectivo</option>
                    <option value="transfer" {{ request('method') === 'transfer' ? 'selected' : '' }}>Transferencia</option>
                    <option value="card" {{ request('method') === 'card' ? 'selected' : '' }}>Tarjeta</option>
                    <option value="other" {{ request('method') === 'other' ? 'selected' : '' }}>Otro</option>
                </select>
                <input type="date" name="date_from" value="{{ request('date_from') }}" placeholder="Desde"
                    class="px-3 py-1.5 text-xs border rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                <input type="date" name="date_to" value="{{ request('date_to') }}" placeholder="Hasta"
                    class="px-3 py-1.5 text-xs border rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                <button type="submit" class="px-3 py-1.5 text-xs font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Filtrar
                </button>
                <a href="{{ route('invoices.payments') }}" class="px-3 py-1.5 text-xs text-gray-600 hover:text-gray-800 {{ request()->anyFilled(['method', 'date_from', 'date_to']) ? '' : 'hidden' }}">
                    Limpiar
                </a>
            </form>
        </div>

        @if ($payments->count())
        <div class="overflow-x-auto">
            <table data-table class="w-full">
                <thead>
                    <tr class="bg-gray-50 border-b">
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">#</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Orden</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Cliente</th>
                        <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Monto</th>
                        <th class="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Método</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Referencia</th>
                        <th class="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Fecha</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Registrado por</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Factura</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($payments as $payment)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-3 text-sm text-gray-600">{{ $payment->id }}</td>
                        <td class="px-6 py-3 text-sm">
                            <a href="{{ route('orders.show', $payment->order) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                                #{{ $payment->order->order_number ?? '—' }}
                            </a>
                        </td>
                        <td class="px-6 py-3 text-sm text-gray-700">{{ $payment->order->client->name ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm text-gray-800 text-right font-medium">${{ number_format($payment->amount, 2) }}</td>
                        <td class="px-6 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                {{ $payment->method === 'cash' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $payment->method === 'transfer' ? 'bg-blue-100 text-blue-800' : '' }}
                                {{ $payment->method === 'card' ? 'bg-purple-100 text-purple-800' : '' }}
                                {{ $payment->method === 'other' ? 'bg-gray-100 text-gray-800' : '' }}">
                                {{ $payment->method === 'cash' ? 'Efectivo' : ($payment->method === 'transfer' ? 'Transf.' : ($payment->method === 'card' ? 'Tarjeta' : 'Otro')) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-sm text-gray-500">{{ $payment->reference ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm text-gray-600 text-center">{{ $payment->paid_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm text-gray-500">{{ $payment->registeredBy?->name ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm">
                            @if ($payment->invoice)
                                <a href="{{ route('invoices.show', $payment->invoice) }}" class="text-blue-600 hover:text-blue-800">
                                    #{{ $payment->invoice->invoice_number }}
                                </a>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 border-t bg-gray-50/50">
            {{ $payments->links() }}
        </div>
        @else
        <div class="text-center py-12">
            <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <p class="mt-4 text-gray-500 text-sm">No hay pagos registrados.</p>
        </div>
        @endif
    </div>
</div>
@endsection
