@extends('layouts.app')

@section('title', $checklist->exists ? 'Editar checklist' : 'Nuevo checklist')

@php
    $rows = old('items', $items->map(fn ($i) => ['id' => $i->id, 'label' => $i->label, 'is_required' => $i->is_required])->all());
    if (! $rows) {
        $rows = [['id' => null, 'label' => '', 'is_required' => true]];
    }
@endphp

@section('content')
<div class="p-6 max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('checklists.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver a checklists
        </a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">{{ $checklist->exists ? 'Editar checklist' : 'Nuevo checklist' }}</h2>
        <p class="text-gray-500">
            {{ \App\Models\Order::deviceTypes()[$checklist->device_type] ?? $checklist->device_type }}
            · {{ \App\Models\Checklist::stages()[$checklist->stage] ?? $checklist->stage }}
        </p>
    </div>

    <form method="POST" action="{{ $checklist->exists ? route('checklists.update', $checklist) : route('checklists.store') }}" class="bg-white rounded-xl shadow-sm border p-6">
        @csrf
        @if ($checklist->exists)
            @method('PUT')
        @else
            <input type="hidden" name="device_type" value="{{ $checklist->device_type }}">
            <input type="hidden" name="stage" value="{{ $checklist->stage }}">
        @endif

        <div class="mb-4">
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
            <input type="text" id="name" name="name" value="{{ old('name', $checklist->name) }}" required
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('name') border-red-500 @enderror">
            @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            @error('stage') <p class="text-sm text-red-600 mt-1">Ya existe un checklist para este tipo y etapa.</p> @enderror
        </div>

        <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-gray-700">Ítems</p>
            <p class="text-xs text-gray-500">Los ítems quitados se desactivan; las órdenes anteriores conservan sus resultados.</p>
        </div>
        <div id="itemRows" class="space-y-2">
            @foreach ($rows as $i => $row)
                <div class="flex items-center gap-2 item-row">
                    <input type="hidden" name="items[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
                    <input type="text" name="items[{{ $i }}][label]" value="{{ $row['label'] ?? '' }}" maxlength="150" placeholder="Ej. Pantalla sin rayaduras"
                        class="flex-1 min-w-0 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <label class="flex items-center gap-1 text-xs text-gray-600 whitespace-nowrap">
                        <input type="hidden" name="items[{{ $i }}][is_required]" value="0">
                        <input type="checkbox" name="items[{{ $i }}][is_required]" value="1" {{ ! empty($row['is_required']) ? 'checked' : '' }} class="w-4 h-4 rounded">
                        Obligatorio
                    </label>
                    <button type="button" class="move-up text-gray-400 hover:text-gray-700 px-1" title="Subir">↑</button>
                    <button type="button" class="remove-row text-red-400 hover:text-red-600 px-1" title="Quitar">✕</button>
                </div>
            @endforeach
        </div>
        <button type="button" id="addRow" class="mt-3 text-sm text-blue-600 hover:text-blue-800">+ Agregar ítem</button>

        <label class="flex items-center gap-2 mt-6">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $checklist->is_active) ? 'checked' : '' }} class="w-4 h-4 text-blue-600 border-gray-300 rounded">
            <span class="text-sm text-gray-700">Checklist activo</span>
        </label>

        <button type="submit" class="mt-6 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">Guardar checklist</button>
    </form>
</div>

<script>
(function () {
    var container = document.getElementById('itemRows');

    // Los nombres usan índices; se renumeran al reordenar para que el orden enviado sea el visible.
    function renumber() {
        container.querySelectorAll('.item-row').forEach(function (row, i) {
            row.querySelectorAll('[name^="items["]').forEach(function (el) {
                el.name = el.name.replace(/^items\[\d+\]/, 'items[' + i + ']');
            });
        });
    }

    container.addEventListener('click', function (e) {
        var row = e.target.closest('.item-row');
        if (!row) return;
        if (e.target.classList.contains('remove-row')) {
            // La última fila se vacía en lugar de borrarse: sirve de molde para "Agregar ítem".
            if (container.querySelectorAll('.item-row').length === 1) {
                row.querySelectorAll('input[type=text], input[type=hidden][name$="[id]"]').forEach(function (el) { el.value = ''; });
                return;
            }
            row.remove();
            renumber();
        } else if (e.target.classList.contains('move-up') && row.previousElementSibling) {
            container.insertBefore(row, row.previousElementSibling);
            renumber();
        }
    });

    document.getElementById('addRow').addEventListener('click', function () {
        var template = container.querySelector('.item-row');
        var row = template ? template.cloneNode(true) : null;
        if (!row) return;
        row.querySelectorAll('input').forEach(function (el) {
            if (el.type === 'text') el.value = '';
            if (el.type === 'hidden' && el.name.endsWith('[id]')) el.value = '';
            if (el.type === 'checkbox') el.checked = true;
        });
        container.appendChild(row);
        renumber();
        row.querySelector('input[type=text]').focus();
    });
})();
</script>
@endsection
