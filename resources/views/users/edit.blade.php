@extends('layouts.app')

@section('title', 'Editar Usuario')

@section('content')
<div class="p-6 max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('users.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver a usuarios
        </a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">Editar Usuario</h2>
        <p class="text-gray-500">{{ $user->name }}</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <form method="POST" action="{{ route('users.update', $user) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('name') border-red-500 @enderror"
                        required>
                    @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Correo Electrónico *</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('email') border-red-500 @enderror"
                        required>
                    @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $user->phone) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('phone') border-red-500 @enderror">
                    @error('phone') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Nueva Contraseña</label>
                    <input type="password" id="password" name="password"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('password') border-red-500 @enderror"
                        placeholder="Dejar vacío para mantener">
                    @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirmar Contraseña</label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>

                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Rol *</label>
                    <select id="role" name="role" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('role') border-red-500 @enderror">
                        <option value="technician" {{ old('role', $user->role) === 'technician' ? 'selected' : '' }}>Técnico</option>
                        <option value="receptionist" {{ old('role', $user->role) === 'receptionist' ? 'selected' : '' }}>Recepcionista</option>
                        <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin</option>
                        <option value="super_admin" {{ old('role', $user->role) === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                    </select>
                    @error('role') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Sucursal</label>
                    <select id="branch_id" name="branch_id"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('branch_id') border-red-500 @enderror">
                        <option value="">— Sin asignar —</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" {{ old('branch_id', $user->branch_id) == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('branch_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="commission_type" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Comisión</label>
                    <select id="commission_type" name="commission_type"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        <option value="">— Sin comisión —</option>
                        <option value="fixed" {{ old('commission_type', $user->commission_type) === 'fixed' ? 'selected' : '' }}>Monto Fijo</option>
                        <option value="percent" {{ old('commission_type', $user->commission_type) === 'percent' ? 'selected' : '' }}>Porcentaje</option>
                    </select>
                </div>

                <div>
                    <label for="commission_value" class="block text-sm font-medium text-gray-700 mb-1">Valor Comisión</label>
                    <input type="number" step="0.01" min="0" id="commission_value" name="commission_value" value="{{ old('commission_value', $user->commission_value) }}"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('commission_value') border-red-500 @enderror">
                    @error('commission_value') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }}
                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm text-gray-700">Usuario activo</span>
                    </label>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
                    Actualizar Usuario
                </button>
                <a href="{{ route('users.index') }}" class="text-gray-600 hover:text-gray-800 px-4 py-2.5 text-sm font-medium transition">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
