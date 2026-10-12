@extends('layouts.app')

@section('title', $supplier->exists ? 'Editar proveedor' : 'Nuevo proveedor')

@php $input = 'w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none'; @endphp

@section('content')
<div class="p-6 max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Volver</a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">{{ $supplier->exists ? 'Editar proveedor' : 'Nuevo proveedor' }}</h2>
    </div>
    <form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
        @csrf
        @if ($supplier->exists) @method('PUT') @endif
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
            <input type="text" id="name" name="name" maxlength="150" required value="{{ old('name', $supplier->name) }}" class="{{ $input }} @error('name') border-red-500 @enderror">
            @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach (['ruc' => 'RUC', 'phone' => 'Teléfono', 'email' => 'Email', 'contact_name' => 'Persona de contacto'] as $field => $label)
                <div>
                    <label for="{{ $field }}" class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
                    <input type="{{ $field === 'email' ? 'email' : 'text' }}" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $supplier->{$field}) }}" class="{{ $input }} @error($field) border-red-500 @enderror">
                    @error($field) <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
            <textarea id="notes" name="notes" rows="3" maxlength="2000" class="{{ $input }}" placeholder="Condiciones de pago, tiempos de entrega...">{{ old('notes', $supplier->notes) }}</textarea>
        </div>
        <label class="flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $supplier->is_active) ? 'checked' : '' }} class="w-4 h-4 text-blue-600 border-gray-300 rounded">
            <span class="text-sm text-gray-700">Proveedor activo</span>
        </label>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium">Guardar</button>
    </form>
</div>
@endsection
