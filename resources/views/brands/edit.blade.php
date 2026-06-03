@extends('layouts.app')

@section('title', 'Editar Marca')

@section('content')
<div class="p-6 max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('brands.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver a marcas
        </a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">Editar Marca</h2>
        <p class="text-gray-500">{{ $brand->name }}</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <form method="POST" action="{{ route('brands.update', $brand) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $brand->name) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('name') border-red-500 @enderror"
                        required>
                    @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipos de dispositivo</label>
                    @error('device_types') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        @foreach (\App\Models\DeviceBrand::deviceTypes() as $key => $label)
                            <label class="flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition
                                {{ in_array($key, old('device_types', $brand->device_types ?? [])) ? 'bg-blue-50 border-blue-300' : '' }}">
                                <input type="checkbox" name="device_types[]" value="{{ $key }}"
                                    {{ in_array($key, old('device_types', $brand->device_types ?? [])) ? 'checked' : '' }}
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <span class="text-sm text-gray-700">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $brand->is_active) ? 'checked' : '' }}
                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm text-gray-700">Marca activa</span>
                    </label>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                    Actualizar Marca
                </button>
                <a href="{{ route('brands.index') }}" class="text-gray-600 hover:text-gray-800 px-4 py-2.5 text-sm font-medium transition">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
