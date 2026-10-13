@extends('layouts.app')

@section('title', 'Compras sugeridas')

@section('content')
<div class="p-4 sm:p-6">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('spare-parts.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Inventario</a>
            <h2 class="text-2xl font-bold text-gray-800 mt-1">Compras sugeridas</h2>
            <p class="text-gray-500 text-sm">Repuestos por debajo del stock mínimo más un mes de consumo, según lo usado y vendido en los últimos {{ $days }} días. Pedir = stock mínimo + un mes de consumo − stock actual.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-sm text-gray-600">Total estimado <strong class="text-gray-900">{{ money($total) }}</strong></span>
            <a href="{{ route('spare-parts.reorder', ['export' => 'csv']) }}" class="text-sm bg-white border px-3 py-2 rounded-lg hover:bg-gray-50">Excel</a>
            <button type="button" onclick="window.print()" class="text-sm bg-white border px-3 py-2 rounded-lg hover:bg-gray-50">Imprimir</button>
        </div>
    </div>

    @forelse ($groups as $supplier => $rows)
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-6">
            <div class="px-6 py-3 border-b bg-gray-50/50 flex flex-wrap items-center justify-between gap-2 text-sm">
                <span class="font-semibold text-gray-800">{{ $supplier }}</span>
                <span class="flex items-center gap-3">
                    <span class="text-gray-600">{{ money($rows->sum('total')) }}</span>
                    <form method="POST" action="{{ route('purchase-orders.store') }}">
                        @csrf
                        <input type="hidden" name="supplier_id" value="{{ $rows->first()['supplier_id'] }}">
                        <input type="hidden" name="from_suggestion" value="1">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">Crear orden de compra</button>
                    </form>
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-600">
                            <th class="px-6 py-2 font-semibold">Repuesto</th>
                            <th class="px-3 py-2 font-semibold text-right">Stock</th>
                            <th class="px-3 py-2 font-semibold text-right">Mínimo</th>
                            <th class="px-3 py-2 font-semibold text-right">Consumo / mes</th>
                            <th class="px-3 py-2 font-semibold text-right">Alcanza para</th>
                            <th class="px-3 py-2 font-semibold text-right">Pedir</th>
                            <th class="px-6 py-2 font-semibold text-right">Costo estimado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-b last:border-0">
                                <td class="px-6 py-2">
                                    <a href="{{ route('spare-parts.show', $row['part']) }}" class="text-blue-600 hover:underline">{{ $row['part']->name }}</a>
                                    @if ($row['part']->code)<span class="block text-xs text-gray-400">{{ $row['part']->code }}</span>@endif
                                </td>
                                <td class="px-3 py-2 text-right {{ $row['stock'] <= $row['min_stock'] ? 'text-red-600 font-semibold' : '' }}">{{ $row['stock'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $row['min_stock'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $row['monthly'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $row['days_left'] === null ? '—' : $row['days_left'] . ' días' }}</td>
                                <td class="px-3 py-2 text-right font-bold">{{ $row['quantity'] }}</td>
                                <td class="px-6 py-2 text-right">{{ money($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl shadow-sm border p-10 text-center text-gray-500 text-sm">No hace falta comprar nada por ahora: el stock alcanza.</div>
    @endforelse
</div>
@endsection
