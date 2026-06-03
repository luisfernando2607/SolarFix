@extends('layouts.app')

@section('title', 'Editar Cliente')

@section('content')
<div class="p-6 max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('clients.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver a clientes
        </a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">Editar Cliente</h2>
        <p class="text-gray-500">{{ $client->name }}</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <form method="POST" action="{{ route('clients.update', $client) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $client->name) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('name') border-red-500 @enderror"
                        required>
                    @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Teléfono *</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $client->phone) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('phone') border-red-500 @enderror"
                        required>
                    @error('phone') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Correo Electrónico</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $client->email) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('email') border-red-500 @enderror">
                    @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="id_document" class="block text-sm font-medium text-gray-700 mb-1">Cédula / RUC</label>
                    <input type="text" id="id_document" name="id_document" value="{{ old('id_document', $client->id_document) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('id_document') border-red-500 @enderror">
                    @error('id_document') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="client_type" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Cliente *</label>
                    <select id="client_type" name="client_type" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('client_type') border-red-500 @enderror">
                        <option value="individual" {{ old('client_type', $client->client_type) === 'individual' ? 'selected' : '' }}>Persona</option>
                        <option value="business" {{ old('client_type', $client->client_type) === 'business' ? 'selected' : '' }}>Empresa</option>
                        <option value="frequent" {{ old('client_type', $client->client_type) === 'frequent' ? 'selected' : '' }}>Frecuente</option>
                    </select>
                    @error('client_type') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                    <input type="text" id="address" name="address" value="{{ old('address', $client->address) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('address') border-red-500 @enderror">
                    @error('address') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                    <textarea id="notes" name="notes" rows="3"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('notes') border-red-500 @enderror">{{ old('notes', $client->notes) }}</textarea>
                    @error('notes') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                    Actualizar Cliente
                </button>
                <a href="{{ route('clients.index') }}" class="text-gray-600 hover:text-gray-800 px-4 py-2.5 text-sm font-medium transition">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
