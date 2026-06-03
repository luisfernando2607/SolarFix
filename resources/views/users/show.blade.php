@extends('layouts.app')

@section('title', $user->name)

@section('content')
<div class="p-6 max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('users.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver a usuarios
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="p-6 bg-gradient-to-r from-blue-500 to-blue-600 text-white">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center text-2xl font-bold uppercase">
                    {{ substr($user->name, 0, 2) }}
                </div>
                <div>
                    <h2 class="text-xl font-bold">{{ $user->name }}</h2>
                    <p class="text-blue-100">{{ $user->email }}</p>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Rol</p>
                    <p class="text-sm font-medium text-gray-800 mt-1 capitalize">
                        {{ $user->role === 'super_admin' ? 'Super Admin' : ($user->role === 'admin' ? 'Admin' : ($user->role === 'technician' ? 'Técnico' : 'Recepcionista')) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Teléfono</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $user->phone ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Sucursal</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $user->branch?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</p>
                    <p class="text-sm font-medium mt-1">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full {{ $user->is_active ? 'bg-green-500' : 'bg-red-500' }}"></span>
                            {{ $user->is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipo de Comisión</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $user->commission_type === 'fixed' ? 'Monto Fijo' : ($user->commission_type === 'percent' ? 'Porcentaje' : '—') }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Valor Comisión</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $user->commission_value ? '$ ' . number_format($user->commission_value, 2) : '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Registrado</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $user->created_at->format('d/m/Y h:i A') }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Última actualización</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $user->updated_at->format('d/m/Y h:i A') }}</p>
                </div>
            </div>

            <div class="pt-4 flex items-center gap-3 border-t">
                <a href="{{ route('users.edit', $user) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Editar Usuario
                </a>
                <a href="{{ route('users.index') }}" class="text-gray-600 hover:text-gray-800 px-4 py-2 text-sm font-medium transition">
                    Volver
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
