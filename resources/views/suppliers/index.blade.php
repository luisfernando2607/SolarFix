@extends('layouts.app')

@section('title', 'Proveedores')

@section('content')
<div class="p-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Proveedores</h2>
            <p class="text-gray-500">A quién se le compran los repuestos.</p>
        </div>
        <a href="{{ route('suppliers.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">+ Nuevo proveedor</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="overflow-x-auto">
            <table data-table data-search="server" data-search-placeholder="Buscar por nombre, RUC o contacto..." class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50/50 text-left text-gray-600">
                        <th class="px-6 py-3 font-semibold">Proveedor</th>
                        <th class="px-3 py-3 font-semibold">RUC</th>
                        <th class="px-3 py-3 font-semibold">Contacto</th>
                        <th class="px-6 py-3 font-semibold text-right">Compras</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr class="border-b {{ $supplier->is_active ? '' : 'opacity-50' }}">
                            <td class="px-6 py-3"><a href="{{ route('suppliers.show', $supplier) }}" class="text-blue-600 hover:underline font-medium">{{ $supplier->name }}</a></td>
                            <td class="px-3 py-3 text-gray-600">{{ $supplier->ruc ?? '—' }}</td>
                            <td class="px-3 py-3 text-gray-600">{{ trim(($supplier->contact_name ?? '') . ' ' . ($supplier->phone ?? '')) ?: '—' }}</td>
                            <td class="px-6 py-3 text-right">{{ $supplier->purchases_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">Sin proveedores.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 border-t">{{ $suppliers->links() }}</div>
    </div>
</div>
@endsection
