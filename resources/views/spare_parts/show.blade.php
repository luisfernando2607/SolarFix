@extends('layouts.app')

@section('title', $part->name)

@php
    $input = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none';
    $typeBadge = ['in' => 'bg-green-100 text-green-800', 'out' => 'bg-blue-100 text-blue-800', 'adjustment' => 'bg-amber-100 text-amber-800'];
@endphp

@section('content')
<div class="p-6 max-w-5xl mx-auto">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('spare-parts.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Volver al inventario
            </a>
            <h2 class="text-2xl font-bold text-gray-800 mt-2">{{ $part->name }}</h2>
            <p class="text-gray-500">
                {{ $part->code ?? 'Sin código' }} · {{ $part->category ?? 'Sin categoría' }}
                @unless ($part->is_active) · <span class="text-red-600">Desactivado</span> @endunless
            </p>
        </div>
        @can('manage-inventory')
            <div class="flex items-center gap-2">
                <a href="{{ route('spare-parts.edit', $part) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">Editar</a>
                <form method="POST" action="{{ route('spare-parts.destroy', $part) }}" onsubmit="return confirm('¿Eliminar este repuesto?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="bg-white hover:bg-red-50 text-red-600 border border-red-200 px-4 py-2 rounded-lg text-sm font-medium transition">Eliminar</button>
                </form>
            </div>
        @endcan
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border p-5 {{ $part->isLowStock() ? 'border-red-300' : '' }}">
            <p class="text-sm text-gray-500">Stock</p>
            <p class="text-3xl font-bold {{ $part->isLowStock() ? 'text-red-600' : 'text-gray-800' }}">{{ $part->stock_quantity }}</p>
            <p class="text-xs {{ $part->isLowStock() ? 'text-red-600' : 'text-gray-400' }}">mínimo {{ $part->min_stock }}{{ $part->isLowStock() ? ' · stock bajo' : '' }}</p>
            @if ($reserved)
                <p class="text-xs text-blue-700 mt-1">{{ $reserved }} más en órdenes abiertas (ya descontadas)</p>
            @endif
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <p class="text-sm text-gray-500">Precio de venta</p>
            <p class="text-2xl font-bold text-gray-800">{{ money($part->sale_price) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <p class="text-sm text-gray-500">Costo promedio</p>
            <p class="text-2xl font-bold text-gray-800">{{ money($part->cost_price) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <p class="text-sm text-gray-500">Ubicación</p>
            <p class="text-lg font-semibold text-gray-800">{{ $part->location ?? '—' }}</p>
            <p class="text-xs text-gray-400">{{ trim($part->compatible_brand . ' ' . $part->compatible_model) ?: 'Compatibilidad no indicada' }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="space-y-6">
            @can('receive-inventory')
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-3">Registrar entrada (compra)</h3>
                <form method="POST" action="{{ route('spare-parts.receive', $part) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Cantidad *</label>
                            <input type="number" name="quantity" min="1" required value="{{ old('quantity') }}" class="{{ $input }} @error('quantity') border-red-500 @enderror">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Costo unitario *</label>
                            <input type="number" name="unit_cost" step="0.01" min="0" required value="{{ old('unit_cost') }}" placeholder="Precio de esta compra" class="{{ $input }}">
                        </div>
                    </div>
                    @can('manage-inventory')
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Nuevo precio de venta (opcional)</label>
                            <input type="number" name="sale_price" step="0.01" min="0" value="{{ old('sale_price') }}" placeholder="{{ number_format($part->sale_price, 2) }}" class="{{ $input }}">
                        </div>
                    @endcan
                    @if ($suppliers->count())
                        <select name="supplier_id" class="{{ $input }} bg-white">
                            <option value="">Proveedor (opcional)</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ (string) old('supplier_id') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <input type="text" name="reason" maxlength="200" value="{{ old('reason') }}" placeholder="N.º de factura de compra / detalle" class="{{ $input }}">
                    @error('quantity') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Registrar entrada</button>
                </form>
            </div>
            @endcan

            @can('manage-inventory')
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-1">Ajuste por conteo</h3>
                <p class="text-xs text-gray-500 mb-3">Deja el stock en la cantidad contada físicamente.</p>
                <form method="POST" action="{{ route('spare-parts.adjust', $part) }}" class="space-y-3">
                    @csrf
                    <input type="number" name="counted_quantity" min="0" required placeholder="Cantidad contada" class="{{ $input }} @error('counted_quantity') border-red-500 @enderror">
                    <input type="text" name="reason" maxlength="200" required placeholder="Motivo (ej. conteo mensual, pieza dañada)" class="{{ $input }} @error('reason') border-red-500 @enderror">
                    @error('reason') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <button type="submit" class="w-full bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium">Ajustar stock</button>
                </form>
            </div>
            @endcan
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border overflow-hidden h-fit">
            <div class="px-6 py-4 border-b bg-gray-50/50">
                <h3 class="text-base font-semibold text-gray-800">Movimientos</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50/50 text-left">
                            <th class="px-4 py-3 font-semibold text-gray-600">Fecha</th>
                            <th class="px-4 py-3 font-semibold text-gray-600">Tipo</th>
                            <th class="px-4 py-3 font-semibold text-gray-600 text-right">Cantidad</th>
                            <th class="px-4 py-3 font-semibold text-gray-600">Detalle</th>
                            <th class="px-4 py-3 font-semibold text-gray-600">Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($part->movements->take(100) as $movement)
                            @php $signed = $movement->type === 'out' ? -$movement->quantity : $movement->quantity; @endphp
                            <tr class="border-b">
                                <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ $movement->moved_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $typeBadge[$movement->type] }}">{{ \App\Models\SparePartMovement::types()[$movement->type] }}</span>
                                </td>
                                <td class="px-4 py-2 text-right font-medium {{ $signed < 0 ? 'text-red-600' : 'text-green-700' }}">{{ $signed > 0 ? '+' : '' }}{{ $signed }}</td>
                                <td class="px-4 py-2">
                                    @if ($movement->order)
                                        <a href="{{ route('orders.show', $movement->order) }}" class="text-blue-600 hover:underline">{{ $movement->reason }}</a>
                                    @else
                                        {{ $movement->reason ?? '—' }}
                                    @endif
                                    @if ($movement->supplier)
                                        <span class="text-xs text-gray-500">· <a href="{{ route('suppliers.show', $movement->supplier) }}" class="hover:underline">{{ $movement->supplier->name }}</a></span>
                                    @endif
                                    @if ($movement->unit_cost !== null && $movement->type === 'in')
                                        <span class="text-xs text-gray-400">· {{ money($movement->unit_cost) }} c/u</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-500">{{ $movement->registeredBy?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Sin movimientos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
