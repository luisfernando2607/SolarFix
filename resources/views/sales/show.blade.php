@extends('layouts.app')

@section('title', 'Venta ' . $sale->sale_number)

@section('content')
<div class="p-4 sm:p-6 max-w-3xl mx-auto">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('sales.index', ['date' => $sale->sold_at->toDateString()]) }}" class="text-sm text-gray-500 hover:text-gray-700">← Ventas del {{ $sale->sold_at->format('d/m/Y') }}</a>
            <h2 class="text-2xl font-bold text-gray-800 mt-1">Venta {{ $sale->sale_number }}</h2>
            <p class="text-gray-500 text-sm">{{ $sale->sold_at->format('d/m/Y H:i') }} · {{ $sale->soldBy?->name }}{{ \App\Models\Branch::isMultiple() ? ' · ' . $sale->branch?->name : '' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sales.receipt', $sale) }}" target="_blank" class="bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium">Imprimir comprobante</a>
            @can('manage-payments')
                @unless ($sale->isCancelled())
                    <form method="POST" action="{{ route('sales.cancel', $sale) }}" onsubmit="return confirm('¿Anular la venta? Los productos vuelven al stock y el dinero sale de la caja.')">
                        @csrf @method('PATCH')
                        <button type="submit" class="bg-white hover:bg-red-50 text-red-600 border border-red-200 px-4 py-2 rounded-lg text-sm font-medium">Anular</button>
                    </form>
                @endunless
            @endcan
        </div>
    </div>

    @if ($sale->isCancelled())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-5 py-3 text-sm text-red-800">
            Anulada el {{ $sale->cancelled_at->format('d/m/Y H:i') }} por {{ $sale->cancelledBy?->name ?? '—' }}.
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <p class="text-sm text-gray-500">Cliente</p>
        <p class="font-medium text-gray-800">
            @if ($sale->client)
                <a href="{{ route('clients.show', $sale->client) }}" class="text-blue-600 hover:underline">{{ $sale->client->name }}</a>
            @else
                {{ $sale->customerLabel() }}
            @endif
        </p>

        <div class="overflow-x-auto mt-4">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left">
                        <th class="py-2 font-semibold text-gray-600">Producto</th>
                        <th class="py-2 font-semibold text-gray-600 text-right">Cant.</th>
                        <th class="py-2 font-semibold text-gray-600 text-right">Precio</th>
                        <th class="py-2 font-semibold text-gray-600 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sale->items as $item)
                        <tr class="border-b">
                            <td class="py-2">
                                @if ($item->sparePart)
                                    <a href="{{ route('spare-parts.show', $item->sparePart) }}" class="hover:underline">{{ $item->description }}</a>
                                @else
                                    {{ $item->description }}
                                @endif
                            </td>
                            <td class="py-2 text-right">{{ $item->quantity }}</td>
                            <td class="py-2 text-right">{{ money($item->unit_price) }}</td>
                            <td class="py-2 text-right font-medium">{{ money($item->subtotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><td colspan="3" class="py-3 text-right font-semibold">Total</td><td class="py-3 text-right text-lg font-bold">{{ money($sale->total) }}</td></tr>
                </tfoot>
            </table>
        </div>

        <dl class="mt-2 grid grid-cols-2 gap-3 text-sm">
            <div><dt class="text-gray-500">Pago</dt><dd class="font-medium">{{ $sale->method_label }}{{ $sale->reference ? ' · ' . $sale->reference : '' }}</dd></div>
            @if ($sale->notes)
                <div><dt class="text-gray-500">Notas</dt><dd>{{ $sale->notes }}</dd></div>
            @endif
        </dl>
    </div>
</div>
@endsection
