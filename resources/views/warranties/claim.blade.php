@extends('layouts.app')

@section('title', 'Reclamo de garantía')

@section('content')
<div class="p-6 max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('orders.show', $order) }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver a la orden
        </a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">Reclamo de garantía</h2>
        <p class="text-gray-500">Orden original #{{ $order->order_number }} · {{ $order->client?->name ?? '—' }}</p>
    </div>

    <div class="bg-cyan-50 border border-cyan-200 rounded-xl p-4 mb-6 text-sm text-cyan-900">
        <p><strong>{{ \App\Models\Warranty::types()[$warranty->warranty_type] ?? $warranty->warranty_type }}</strong>
            · vigente hasta el {{ $warranty->end_date->format('d/m/Y') }}</p>
        @if ($warranty->conditions)
            <p class="mt-1">{{ $warranty->conditions }}</p>
        @endif
        <p class="mt-2 text-cyan-800">Si el daño es por golpe, caída o líquido posterior a la entrega, la garantía no cubre: abre una orden normal.</p>
    </div>

    <form method="POST" action="{{ route('warranties.claim.store', $order) }}" class="bg-white rounded-xl shadow-sm border p-6">
        @csrf
        <div class="mb-4">
            <label for="declared_fault" class="block text-sm font-medium text-gray-700 mb-1">Falla que reporta el cliente *</label>
            <textarea id="declared_fault" name="declared_fault" rows="4" required
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('declared_fault') border-red-500 @enderror">{{ old('declared_fault') }}</textarea>
            @error('declared_fault') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="mb-4">
            <label for="physical_condition" class="block text-sm font-medium text-gray-700 mb-1">Estado físico actual</label>
            <textarea id="physical_condition" name="physical_condition" rows="3"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">{{ old('physical_condition') }}</textarea>
        </div>
        <p class="text-xs text-gray-500 mb-4">Se crea una orden de garantía vinculada, sin costo, con el mismo equipo y técnico. Después podrás agregar fotos y la firma de recepción.</p>
        <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
            Abrir orden de garantía
        </button>
    </form>
</div>
@endsection
