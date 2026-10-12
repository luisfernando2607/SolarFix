@extends('layouts.app')

@section('title', 'Inventario')

@section('content')
<div class="p-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Inventario de repuestos</h2>
            <p class="text-gray-500">Stock, precios y movimientos.</p>
        </div>
        @can('manage-inventory')
            <a href="{{ route('spare-parts.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">+ Nuevo repuesto</a>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-6 py-3 border-b bg-gray-50/50 flex flex-wrap items-center gap-2">
            <a href="{{ route('spare-parts.index', array_filter(['q' => request('q')])) }}"
                class="px-3 py-1.5 text-xs font-medium rounded-lg {{ !request('low') && !request('category') ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50' }}">Todos</a>
            <a href="{{ route('spare-parts.index', array_filter(['low' => 1, 'q' => request('q')])) }}"
                class="px-3 py-1.5 text-xs font-medium rounded-lg {{ request('low') ? 'bg-red-600 text-white' : 'bg-white text-red-600 border border-red-200 hover:bg-red-50' }}">
                Stock bajo ({{ $lowCount }})
            </a>
            @foreach ($categories as $category)
                <a href="{{ route('spare-parts.index', array_filter(['category' => $category, 'q' => request('q')])) }}"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg {{ request('category') === $category ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50' }}">{{ $category }}</a>
            @endforeach
        </div>

        <div class="overflow-x-auto">
            <table data-table data-search="server" data-search-placeholder="Buscar por nombre, código, categoría o compatibilidad..." class="w-full">
                <thead>
                    <tr class="border-b bg-gray-50/50 text-left">
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase">Repuesto</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase">Categoría</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase">Compatible con</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase text-right">Stock</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase text-right">Precio venta</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase">Ubicación</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($parts as $part)
                        <tr class="border-b hover:bg-gray-50 text-sm {{ $part->is_active ? '' : 'opacity-50' }}">
                            <td class="px-6 py-3">
                                <a href="{{ route('spare-parts.show', $part) }}" class="text-blue-600 hover:underline font-medium">{{ $part->name }}</a>
                                @if ($part->code) <span class="block text-xs text-gray-400">{{ $part->code }}</span> @endif
                            </td>
                            <td class="px-6 py-3">{{ $part->category ?? '—' }}</td>
                            <td class="px-6 py-3">{{ trim($part->compatible_brand . ' ' . $part->compatible_model) ?: '—' }}</td>
                            <td class="px-6 py-3 text-right">
                                <span class="font-semibold {{ $part->isLowStock() ? 'text-red-600' : 'text-gray-800' }}">{{ $part->stock_quantity }}</span>
                                <span class="text-xs text-gray-400">/ mín. {{ $part->min_stock }}</span>
                            </td>
                            <td class="px-6 py-3 text-right">{{ money($part->sale_price) }}</td>
                            <td class="px-6 py-3 text-gray-500">{{ $part->location ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500 text-sm">No hay repuestos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 border-t">{{ $parts->links() }}</div>
    </div>
</div>
@endsection
