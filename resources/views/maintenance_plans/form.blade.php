@extends('layouts.app')

@section('title', $plan->exists ? 'Editar plan de mantenimiento' : 'Nuevo plan de mantenimiento')

@php
    $input = 'w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none';
    $client = $plan->client ?? ($plan->client_id ? \App\Models\Client::withTrashed()->find($plan->client_id) : null);
@endphp

@section('content')
<div class="p-6 max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('maintenance-plans.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver a mantenimientos
        </a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">{{ $plan->exists ? 'Editar plan de mantenimiento' : 'Nuevo plan de mantenimiento' }}</h2>
    </div>

    <form method="POST" action="{{ $plan->exists ? route('maintenance-plans.update', $plan) : route('maintenance-plans.store') }}" class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
        @csrf
        @if ($plan->exists) @method('PUT') @endif

        <div class="relative">
            <label for="clientSearch" class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
            <input type="text" id="clientSearch" autocomplete="off" placeholder="Buscar por nombre, cédula o teléfono..."
                value="{{ old('client_label', $client?->name) }}" class="{{ $input }} @error('client_id') border-red-500 @enderror">
            <input type="hidden" name="client_label" id="clientLabel" value="{{ old('client_label', $client?->name) }}">
            <input type="hidden" name="client_id" id="clientId" value="{{ old('client_id', $plan->client_id) }}">
            <div id="clientResults" class="hidden absolute z-50 w-full bg-white border border-gray-300 rounded-lg mt-1 shadow-lg max-h-56 overflow-y-auto"></div>
            @error('client_id') <p class="text-sm text-red-600 mt-1">Selecciona un cliente de la lista.</p> @enderror
        </div>

        <div>
            <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Equipo *</label>
            <input type="text" id="title" name="title" maxlength="150" required value="{{ old('title', $plan->title) }}"
                placeholder="Ej. Split sala 12000 BTU" class="{{ $input }} @error('title') border-red-500 @enderror">
            @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
            <input type="text" id="address" name="address" maxlength="255" value="{{ old('address', $plan->address) }}" class="{{ $input }}">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="interval_months" class="block text-sm font-medium text-gray-700 mb-1">Cada cuántos meses *</label>
                <input type="number" id="interval_months" name="interval_months" min="1" max="36" required
                    value="{{ old('interval_months', $plan->interval_months) }}" class="{{ $input }} @error('interval_months') border-red-500 @enderror">
                @error('interval_months') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="next_due_date" class="block text-sm font-medium text-gray-700 mb-1">Próximo mantenimiento *</label>
                <input type="date" id="next_due_date" name="next_due_date" required
                    value="{{ old('next_due_date', $plan->next_due_date?->toDateString()) }}" class="{{ $input }} @error('next_due_date') border-red-500 @enderror">
                @error('next_due_date') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
            <textarea id="notes" name="notes" rows="3" maxlength="2000" placeholder="Marca, ubicación del equipo, acceso..." class="{{ $input }}">{{ old('notes', $plan->notes) }}</textarea>
        </div>

        <label class="flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $plan->is_active) ? 'checked' : '' }} class="w-4 h-4 text-blue-600 border-gray-300 rounded">
            <span class="text-sm text-gray-700">Plan activo</span>
        </label>

        @if ($plan->last_done_at)
            <p class="text-xs text-gray-500">Último mantenimiento: {{ $plan->last_done_at->format('d/m/Y') }}</p>
        @endif

        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">Guardar plan</button>
    </form>
</div>

@include('partials.client-search')
@endsection
