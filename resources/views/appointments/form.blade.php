@extends('layouts.app')

@section('title', $appointment->exists ? 'Editar cita' : 'Nueva cita')

@php
    $input = 'w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none';
    $client = $appointment->client ?? ($appointment->client_id ? \App\Models\Client::withTrashed()->find($appointment->client_id) : null);
@endphp

@section('content')
<div class="p-6 max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ $appointment->exists ? route('appointments.show', $appointment) : route('appointments.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver
        </a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">{{ $appointment->exists ? 'Editar cita' : 'Nueva cita' }}</h2>
    </div>

    <form method="POST" action="{{ $appointment->exists ? route('appointments.update', $appointment) : route('appointments.store') }}" class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
        @csrf
        @if ($appointment->exists) @method('PUT') @endif
        <input type="hidden" name="order_id" value="{{ old('order_id', $appointment->order_id) }}">

        <div class="relative">
            <label for="clientSearch" class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
            <input type="text" id="clientSearch" autocomplete="off" placeholder="Buscar por nombre, cédula o teléfono..."
                value="{{ old('client_label', $client?->name) }}" class="{{ $input }} @error('client_id') border-red-500 @enderror">
            <input type="hidden" name="client_label" id="clientLabel" value="{{ old('client_label', $client?->name) }}">
            <input type="hidden" name="client_id" id="clientId" value="{{ old('client_id', $appointment->client_id) }}">
            <div id="clientResults" class="hidden absolute z-50 w-full bg-white border border-gray-300 rounded-lg mt-1 shadow-lg max-h-56 overflow-y-auto"></div>
            @error('client_id') <p class="text-sm text-red-600 mt-1">Selecciona un cliente de la lista.</p> @enderror
            <p class="text-xs text-gray-500 mt-1">¿Cliente nuevo? Créalo primero en Clientes.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Tipo de servicio *</label>
                <select id="type" name="type" class="{{ $input }} bg-white">
                    @foreach (\App\Models\Appointment::types() as $key => $label)
                        <option value="{{ $key }}" {{ old('type', $appointment->type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">Técnico</label>
                <select id="user_id" name="user_id" class="{{ $input }} bg-white">
                    <option value="">Sin asignar</option>
                    @foreach ($technicians as $technician)
                        <option value="{{ $technician->id }}" {{ (string) old('user_id', $appointment->user_id) === (string) $technician->id ? 'selected' : '' }}>{{ $technician->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Motivo *</label>
            <input type="text" id="title" name="title" maxlength="150" required value="{{ old('title', $appointment->title) }}"
                placeholder="Ej. Mantenimiento aire split 12000 BTU" class="{{ $input }} @error('title') border-red-500 @enderror">
            @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
            <input type="text" id="address" name="address" maxlength="255" value="{{ old('address', $appointment->address) }}" class="{{ $input }}">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="scheduled_at" class="block text-sm font-medium text-gray-700 mb-1">Fecha y hora *</label>
                <input type="datetime-local" id="scheduled_at" name="scheduled_at" required
                    value="{{ old('scheduled_at', $appointment->scheduled_at?->format('Y-m-d\TH:i')) }}" class="{{ $input }} @error('scheduled_at') border-red-500 @enderror">
                @error('scheduled_at') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="duration_min" class="block text-sm font-medium text-gray-700 mb-1">Duración (minutos) *</label>
                <input type="number" id="duration_min" name="duration_min" min="15" max="480" step="15" required
                    value="{{ old('duration_min', $appointment->duration_min) }}" class="{{ $input }}">
            </div>
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
            <textarea id="notes" name="notes" rows="3" maxlength="2000" placeholder="Modelo del equipo, problema reportado, piso, acceso..."
                class="{{ $input }}">{{ old('notes', $appointment->notes) }}</textarea>
        </div>

        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
            {{ $appointment->exists ? 'Guardar cambios' : 'Agendar cita' }}
        </button>
    </form>
</div>

<script>
(function () {
    var input = document.getElementById('clientSearch');
    var hidden = document.getElementById('clientId');
    var label = document.getElementById('clientLabel');
    var results = document.getElementById('clientResults');
    var address = document.getElementById('address');
    var url = @json(route('clients.search'));
    var timer;

    input.addEventListener('input', function () {
        hidden.value = '';
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) { results.classList.add('hidden'); return; }
        timer = setTimeout(function () {
            fetch(url + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (items) {
                    if (input.value.trim() !== q) return;
                    results.innerHTML = '';
                    if (!items.length) {
                        results.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500">Sin resultados</div>';
                    }
                    items.forEach(function (item) {
                        var div = document.createElement('div');
                        div.className = 'px-3 py-2 text-sm cursor-pointer hover:bg-blue-50';
                        div.textContent = item.label + (item.phone ? ' · ' + item.phone : '');
                        div.addEventListener('mousedown', function (e) {
                            e.preventDefault();
                            input.value = item.name;
                            label.value = item.name;
                            hidden.value = item.id;
                            if (!address.value && item.address) address.value = item.address;
                            results.classList.add('hidden');
                        });
                        results.appendChild(div);
                    });
                    results.classList.remove('hidden');
                });
        }, 250);
    });
    input.addEventListener('blur', function () { setTimeout(function () { results.classList.add('hidden'); }, 200); });
})();
</script>
@endsection
