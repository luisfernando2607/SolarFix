@extends('layouts.app')

@section('title', $supplier->name)

@section('content')
<div class="p-6 max-w-5xl mx-auto">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('suppliers.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Volver a proveedores</a>
            <h2 class="text-2xl font-bold text-gray-800 mt-2">{{ $supplier->name }}</h2>
            <p class="text-gray-500">
                {{ $supplier->ruc ? 'RUC ' . $supplier->ruc : 'Sin RUC' }}
                @if ($supplier->contact_name) · {{ $supplier->contact_name }} @endif
                @if ($supplier->phone) · {{ $supplier->phone }} @endif
                @if ($supplier->email) · {{ $supplier->email }} @endif
                @unless ($supplier->is_active) · <span class="text-red-600">Inactivo</span> @endunless
            </p>
        </div>
        <a href="{{ route('suppliers.edit', $supplier) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Editar</a>
    </div>

    @if ($supplier->notes)
        <div class="mb-6 bg-white rounded-xl shadow-sm border p-4 text-sm text-gray-700 whitespace-pre-line">{{ $supplier->notes }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-6 py-4 border-b bg-gray-50/50 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-800">Compras</h3>
            <span class="text-sm text-gray-600">Total comprado: <strong>${{ number_format($total, 2) }}</strong></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-gray-600">
                        <th class="px-6 py-2 font-semibold">Fecha</th>
                        <th class="px-3 py-2 font-semibold">Repuesto</th>
                        <th class="px-3 py-2 font-semibold text-right">Cantidad</th>
                        <th class="px-3 py-2 font-semibold text-right">Costo unit.</th>
                        <th class="px-3 py-2 font-semibold">Detalle</th>
                        <th class="px-6 py-2 font-semibold">Registró</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchases as $purchase)
                        <tr class="border-b">
                            <td class="px-6 py-2 text-gray-500 whitespace-nowrap">{{ $purchase->moved_at->format('d/m/Y') }}</td>
                            <td class="px-3 py-2"><a href="{{ route('spare-parts.show', $purchase->spare_part_id) }}" class="text-blue-600 hover:underline">{{ $purchase->sparePart?->name }}</a></td>
                            <td class="px-3 py-2 text-right">{{ $purchase->quantity }}</td>
                            <td class="px-3 py-2 text-right">${{ number_format($purchase->unit_cost, 2) }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $purchase->reason }}</td>
                            <td class="px-6 py-2 text-gray-500">{{ $purchase->registeredBy?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Sin compras registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 border-t">{{ $purchases->links() }}</div>
    </div>
</div>
@endsection
