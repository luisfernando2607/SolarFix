@extends('layouts.app')

@section('title', 'Ventas de mostrador')

@section('content')
<div class="p-4 sm:p-6">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Ventas de mostrador</h2>
            <p class="text-gray-500">Accesorios y repuestos vendidos sin orden. Entran a la caja del día.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('sales.index') }}" class="flex items-center gap-2">
                <label for="date" class="sr-only">Fecha</label>
                <input type="date" id="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" onchange="this.form.submit()"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-base sm:text-sm bg-white">
            </form>
            <a href="{{ route('sales.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Nueva venta</a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-6 py-4 border-b bg-gray-50/50 flex justify-between text-sm">
            <span class="font-semibold text-gray-800">{{ $date->format('d/m/Y') }} · {{ $sales->whereNull('cancelled_at')->count() }} ventas</span>
            <span class="font-semibold text-gray-800">Total {{ money($total) }}</span>
        </div>
        @if ($sales->isEmpty())
            <p class="px-6 py-10 text-center text-gray-500 text-sm">No hay ventas en esta fecha.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50/50 text-left">
                            <th class="px-6 py-3 font-semibold text-gray-600">Hora</th>
                            <th class="px-6 py-3 font-semibold text-gray-600">Venta</th>
                            <th class="px-6 py-3 font-semibold text-gray-600">Cliente</th>
                            <th class="px-6 py-3 font-semibold text-gray-600">Productos</th>
                            <th class="px-6 py-3 font-semibold text-gray-600">Pago</th>
                            <th class="px-6 py-3 font-semibold text-gray-600 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sales as $sale)
                            <tr class="border-b {{ $sale->isCancelled() ? 'text-gray-400 line-through' : '' }}">
                                <td class="px-6 py-3">{{ $sale->sold_at->format('H:i') }}</td>
                                <td class="px-6 py-3"><a href="{{ route('sales.show', $sale) }}" class="text-blue-600 hover:underline font-medium">{{ $sale->sale_number }}</a></td>
                                <td class="px-6 py-3">{{ $sale->customerLabel() }}</td>
                                <td class="px-6 py-3">{{ $sale->items->map(fn ($i) => $i->quantity . ' × ' . $i->description)->join(', ') }}</td>
                                <td class="px-6 py-3">{{ $sale->method_label }}</td>
                                <td class="px-6 py-3 text-right font-medium">{{ money($sale->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
