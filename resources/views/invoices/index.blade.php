@extends('layouts.app')

@section('title', 'Facturas')

@section('content')
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Facturas</h2>
            <p class="text-gray-500">Gestión de facturación electrónica</p>
        </div>
        <a href="{{ route('invoices.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nueva Factura
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-6 py-3 border-b bg-gray-50/50 flex flex-wrap items-center gap-2">
            <a href="{{ route('invoices.index') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ !$currentStatus ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50' }}">
                Todas
            </a>
            @foreach ($statuses as $key => $label)
                <a href="{{ route('invoices.index', ['status' => $key]) }}"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $currentStatus === $key ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50' }}">
                    {{ $label }}
                </a>
            @endforeach
            <span class="text-gray-300 mx-1">|</span>
            <a href="{{ route('invoices.payments') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white text-gray-600 border hover:bg-gray-50">
                Pagos
            </a>
        </div>

        @if ($invoices->count())
            <div class="overflow-x-auto">
                <table data-table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider"># Factura</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Cliente</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Dispositivo</th>
                            <th class="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Emisión</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider no-sort">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($invoices as $inv)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-3 text-sm font-medium text-gray-800">
                                    <a href="{{ route('invoices.show', $inv) }}" class="text-blue-600 hover:text-blue-800">{{ $inv->invoice_number }}</a>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-700">{{ $inv->client_name }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500">{{ $inv->device_brand ? $inv->device_brand . ' ' . $inv->device_model : ($inv->device_type ?? '—') }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600 text-center">{{ $inv->issued_at?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-6 py-3 text-sm text-gray-800 text-right font-medium">${{ number_format($inv->total, 2) }}</td>
                                <td class="px-6 py-3 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $inv->status === 'emitida' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                        {{ $inv->status === 'pagada' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $inv->status === 'anulada' ? 'bg-red-100 text-red-800' : '' }}">
                                        {{ $statuses[$inv->status] ?? $inv->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('invoices.show', $inv) }}" class="p-1.5 text-gray-400 hover:text-blue-600">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        <a href="{{ route('invoices.pdf', $inv) }}" class="p-1.5 text-gray-400 hover:text-gray-600">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-3 border-t bg-gray-50/50">
                {{ $invoices->links() }}
            </div>
        @else
            <div class="text-center py-8">
                <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <p class="mt-4 text-gray-500 text-sm">No hay facturas registradas.</p>
            </div>
        @endif
    </div>

    @if ($recentPayments->count())
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden mt-6">
        <div class="px-6 py-3 border-b bg-gray-50 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">Pagos Recientes</h3>
            <a href="{{ route('invoices.payments') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Ver todos</a>
        </div>
        <table class="w-full">
            <thead>
                <tr class="border-b bg-gray-50/50">
                    <th class="text-left px-6 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Orden</th>
                    <th class="text-left px-6 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Cliente</th>
                    <th class="text-right px-6 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Monto</th>
                    <th class="text-center px-6 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Método</th>
                    <th class="text-center px-6 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Fecha</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($recentPayments as $payment)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-2 text-sm">
                        <a href="{{ route('orders.show', $payment->order) }}" class="text-blue-600 hover:text-blue-800">#{{ $payment->order->order_number ?? '—' }}</a>
                    </td>
                    <td class="px-6 py-2 text-sm text-gray-700">{{ $payment->order->client->name ?? '—' }}</td>
                    <td class="px-6 py-2 text-sm text-gray-800 text-right font-medium">${{ number_format($payment->amount, 2) }}</td>
                    <td class="px-6 py-2 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                            {{ $payment->method === 'cash' ? 'bg-green-100 text-green-800' : '' }}
                            {{ $payment->method === 'transfer' ? 'bg-blue-100 text-blue-800' : '' }}
                            {{ $payment->method === 'card' ? 'bg-purple-100 text-purple-800' : '' }}
                            {{ $payment->method === 'other' ? 'bg-gray-100 text-gray-800' : '' }}">
                            {{ $payment->method === 'cash' ? 'Efectivo' : ($payment->method === 'transfer' ? 'Transf.' : ($payment->method === 'card' ? 'Tarjeta' : 'Otro')) }}
                        </span>
                    </td>
                    <td class="px-6 py-2 text-sm text-gray-600 text-center">{{ $payment->paid_at?->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
