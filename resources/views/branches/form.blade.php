@extends('layouts.app')

@section('title', $branch->exists ? 'Editar sucursal' : 'Nueva sucursal')

@php $input = 'w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none'; @endphp

@section('content')
<div class="p-4 sm:p-6 max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('branches.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Volver a sucursales</a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">{{ $branch->exists ? 'Editar sucursal' : 'Nueva sucursal' }}</h2>
    </div>
    <form method="POST" action="{{ $branch->exists ? route('branches.update', $branch) : route('branches.store') }}" class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
        @csrf
        @if ($branch->exists) @method('PUT') @endif
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                <input type="text" id="name" name="name" maxlength="120" required value="{{ old('name', $branch->name) }}" placeholder="Ej. Sucursal Norte" class="{{ $input }} @error('name') border-red-500 @enderror">
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Código *</label>
                <input type="text" id="code" name="code" maxlength="6" required value="{{ old('code', $branch->code) }}" placeholder="GYE" style="text-transform:uppercase" class="{{ $input }} font-mono @error('code') border-red-500 @enderror">
                @error('code') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
            <input type="text" id="address" name="address" maxlength="255" value="{{ old('address', $branch->address) }}" class="{{ $input }}">
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                <input type="text" id="phone" name="phone" maxlength="30" value="{{ old('phone', $branch->phone) }}" class="{{ $input }}">
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" id="email" name="email" maxlength="120" value="{{ old('email', $branch->email) }}" class="{{ $input }} @error('email') border-red-500 @enderror">
                @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <label class="flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $branch->is_active) ? 'checked' : '' }} class="w-4 h-4 text-blue-600 border-gray-300 rounded">
            <span class="text-sm text-gray-700">Sucursal activa</span>
        </label>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium">Guardar</button>
    </form>
</div>
@endsection
