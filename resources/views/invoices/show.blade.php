@extends('layouts.app')

@section('title', 'Factura #' . $invoice->invoice_number)

@section('content')
<div class="p-6 max-w-4xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="{{ route('invoices.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Volver a facturas
            </a>
            <div class="flex items-center gap-3 mt-2">
                <h2 class="text-2xl font-bold text-gray-800">Factura #{{ $invoice->invoice_number }}</h2>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    {{ $invoice->status === 'emitida' ? 'bg-yellow-100 text-yellow-800' : '' }}
                    {{ $invoice->status === 'pagada' ? 'bg-green-100 text-green-800' : '' }}
                    {{ $invoice->status === 'anulada' ? 'bg-red-100 text-red-800' : '' }}">
                    {{ $statuses[$invoice->status] ?? $invoice->status }}
                </span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('invoices.pdf', $invoice) }}" class="bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                PDF
            </a>
            @can('manage-payments')
                @if ($invoice->status === 'emitida')
                    <form method="POST" action="{{ route('invoices.mark-paid', $invoice) }}" class="inline"
                        onsubmit="return confirm('¿Marcar esta factura como pagada?')">
                        @csrf @method('PATCH')
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                            Marcar Pagada
                        </button>
                    </form>
                    <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" class="inline"
                        onsubmit="return confirm('¿Anular esta factura?')">
                        @csrf @method('PATCH')
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                            Anular
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
        <div class="flex justify-between items-start">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">{{ $invoice->client_name }}</h3>
                <p class="text-sm text-gray-500 mt-1">{{ $invoice->client_document ? 'Doc: ' . $invoice->client_document : '' }}</p>
                <p class="text-sm text-gray-500">{{ $invoice->client_phone ? 'Tel: ' . $invoice->client_phone : '' }}</p>
                <p class="text-sm text-gray-500">{{ $invoice->client_email }}</p>
                @if ($invoice->client_address)
                    <p class="text-sm text-gray-500">{{ $invoice->client_address }}</p>
                @endif
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500">Emisión: <span class="font-medium text-gray-700">{{ $invoice->issued_at?->format('d/m/Y') ?? '—' }}</span></p>
                @if ($invoice->paid_at)
                    <p class="text-sm text-gray-500">Pagada: <span class="font-medium text-gray-700">{{ $invoice->paid_at->format('d/m/Y') }}</span></p>
                @endif
                @if ($invoice->order)
                    <p class="text-sm text-gray-500 mt-2">Orden: <a href="{{ route('orders.show', $invoice->order) }}" class="text-blue-600 hover:text-blue-800 font-medium">#{{ $invoice->order->order_number }}</a></p>
                @endif
            </div>
        </div>

        @if ($invoice->device_type || $invoice->device_brand)
        <div class="border-t mt-4 pt-4">
            <p class="text-sm font-medium text-gray-600 mb-1">Dispositivo</p>
            <p class="text-sm text-gray-800">{{ $invoice->device_type ? $invoice->device_type . ' — ' : '' }}{{ $invoice->device_brand }} {{ $invoice->device_model }}</p>
            @if ($invoice->device_serial)
                <p class="text-sm text-gray-500">Serial: {{ $invoice->device_serial }}</p>
            @endif
        </div>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-6">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 border-b">
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Descripción</th>
                    <th class="text-center px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Cant.</th>
                    <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">P. Unit.</th>
                    <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($invoice->items as $item)
                <tr>
                    <td class="px-6 py-3 text-sm text-gray-800">{{ $item->description }}</td>
                    <td class="px-6 py-3 text-sm text-gray-600 text-center">{{ $item->quantity }}</td>
                    <td class="px-6 py-3 text-sm text-gray-600 text-right">${{ number_format($item->unit_price, 2) }}</td>
                    <td class="px-6 py-3 text-sm text-gray-800 text-right font-medium">${{ number_format($item->subtotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t bg-gray-50/50">
                <tr>
                    <td colspan="3" class="px-6 py-2 text-sm text-gray-600 text-right">Subtotal</td>
                    <td class="px-6 py-2 text-sm text-gray-800 text-right">${{ number_format($invoice->subtotal, 2) }}</td>
                </tr>
                @if ($invoice->iva_percent > 0)
                <tr>
                    <td colspan="3" class="px-6 py-2 text-sm text-gray-600 text-right">IVA {{ $invoice->iva_percent }}%</td>
                    <td class="px-6 py-2 text-sm text-gray-800 text-right">${{ number_format($invoice->iva_amount, 2) }}</td>
                </tr>
                @endif
                <tr class="border-t font-bold">
                    <td colspan="3" class="px-6 py-3 text-sm text-gray-800 text-right">TOTAL</td>
                    <td class="px-6 py-3 text-sm text-gray-800 text-right">${{ number_format($invoice->total, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if ($invoicePayments->count())
    <div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Pagos Registrados</h3>
        <div class="space-y-2">
            @foreach ($invoicePayments as $payment)
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
                <span class="text-xs text-gray-400">{{ $payment->paid_at?->format('d/m/Y H:i') ?? '' }}</span>
            </div>
            @endforeach
        </div>
        <div class="mt-3 text-sm text-gray-600 border-t pt-2 flex justify-between">
            <span>Total pagado</span>
            <span class="font-medium text-gray-800">${{ number_format($invoicePayments->sum('amount'), 2) }}</span>
        </div>
    </div>
    @endif

    @if ($invoice->notes)
    <div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
        <h4 class="text-sm font-semibold text-gray-600 mb-1">Notas</h4>
        <p class="text-sm text-gray-700">{{ $invoice->notes }}</p>
    </div>
    @endif

    @if ($invoice->createdBy)
    <p class="text-xs text-gray-400">Creada por {{ $invoice->createdBy->name }} el {{ $invoice->created_at->format('d/m/Y H:i') }}</p>
    @endif
</div>
@endsection
