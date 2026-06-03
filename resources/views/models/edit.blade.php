@extends('layouts.app')

@section('title', 'Editar Modelo')

@section('content')
<div class="p-6 max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('models.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver a modelos
        </a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">Editar Modelo</h2>
        <p class="text-gray-500">{{ $model->name }}</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <form method="POST" action="{{ route('models.update', $model) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="brand_id" class="block text-sm font-medium text-gray-700 mb-1">Marca *</label>
                    <select id="brand_id" name="brand_id" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('brand_id') border-red-500 @enderror">
                        <option value="">— Seleccionar —</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" {{ old('brand_id', $model->brand_id) == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                    @error('brand_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="device_type" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Dispositivo *</label>
                    <select id="device_type" name="device_type" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('device_type') border-red-500 @enderror">
                        @foreach (\App\Models\DeviceModel::deviceTypes() as $key => $label)
                            <option value="{{ $key }}" {{ old('device_type', $model->device_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('device_type') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nombre del Modelo *</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $model->name) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('name') border-red-500 @enderror"
                        required>
                    @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $model->is_active) ? 'checked' : '' }}
                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm text-gray-700">Modelo activo</span>
                    </label>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                    Actualizar Modelo
                </button>
                <a href="{{ route('models.index') }}" class="text-gray-600 hover:text-gray-800 px-4 py-2.5 text-sm font-medium transition">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
