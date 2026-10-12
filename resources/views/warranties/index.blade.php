@extends('layouts.app')

@section('title', 'Garantías')

@php
    $badge = [
        'active' => 'bg-green-100 text-green-800',
        'expiring' => 'bg-amber-100 text-amber-800',
        'expired' => 'bg-gray-100 text-gray-600',
        'claimed' => 'bg-cyan-100 text-cyan-800',
    ];
@endphp

@section('content')
<div class="p-6">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Garantías</h2>
        <p class="text-gray-500">Se emiten al entregar una orden con días de garantía.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-6 py-3 border-b bg-gray-50/50 flex flex-wrap items-center gap-2">
            <a href="{{ route('warranties.index', array_filter(['q' => request('q')])) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ !$currentStatus ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50' }}">Todas</a>
            @foreach ($statuses as $key => $label)
                <a href="{{ route('warranties.index', array_filter(['status' => $key, 'q' => request('q')])) }}"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $currentStatus === $key ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50' }}">{{ $label }}</a>
            @endforeach
        </div>

        <div class="overflow-x-auto">
            <table data-table data-search="server" data-search-placeholder="Buscar por orden, cliente, cédula, teléfono o serie..." class="w-full">
                <thead>
                    <tr class="border-b bg-gray-50/50 text-left">
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase">Orden</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase">Cliente</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase">Tipo</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase">Desde</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase">Hasta</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($warranties as $warranty)
                        @php $status = $warranty->currentStatus(); @endphp
                        <tr class="border-b hover:bg-gray-50 text-sm">
                            <td class="px-6 py-3">
                                <a href="{{ route('orders.show', $warranty->order_id) }}" class="text-blue-600 hover:underline font-medium">{{ $warranty->order?->order_number }}</a>
                            </td>
                            <td class="px-6 py-3">
                                {{ $warranty->order?->client?->name ?? '—' }}
                                @if ($warranty->order?->client?->phone)
                                    <span class="text-gray-400">· {{ $warranty->order->client->phone }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3">{{ \App\Models\Warranty::types()[$warranty->warranty_type] ?? $warranty->warranty_type }}</td>
                            <td class="px-6 py-3">{{ $warranty->start_date->format('d/m/Y') }}</td>
                            <td class="px-6 py-3">{{ $warranty->end_date->format('d/m/Y') }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge[$status] }}">{{ $statuses[$status] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500 text-sm">No hay garantías.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 border-t">
            {{ $warranties->links() }}
        </div>
    </div>
</div>
@endsection
