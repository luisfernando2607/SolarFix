@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Usuarios</h2>
            <p class="text-gray-500">Gestión de usuarios del sistema</p>
        </div>
        <a href="{{ route('users.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo Usuario
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        @if ($users->count())
            <div class="overflow-x-auto">
                <table data-table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Rol</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Sucursal</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider no-sort">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($users as $user)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-gray-200 flex items-center justify-center text-sm font-bold text-gray-700 uppercase">
                                            {{ substr($user->name, 0, 2) }}
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-800">{{ $user->name }}</p>
                                            <p class="text-xs text-gray-500">{{ $user->phone ?? '—' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $user->email }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $user->role === 'super_admin' ? 'bg-purple-100 text-purple-800' : '' }}
                                        {{ $user->role === 'admin' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $user->role === 'technician' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $user->role === 'receptionist' ? 'bg-amber-100 text-amber-800' : '' }}">
                                        {{ $user->role === 'super_admin' ? 'Super Admin' : ($user->role === 'admin' ? 'Admin' : ($user->role === 'technician' ? 'Técnico' : 'Recepcionista')) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $user->branch?->name ?? '—' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full {{ $user->is_active ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                        <span class="text-sm {{ $user->is_active ? 'text-green-700' : 'text-red-700' }}">
                                            {{ $user->is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('users.show', $user) }}" class="text-gray-400 hover:text-gray-600 p-1" title="Ver">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        <a href="{{ route('users.edit', $user) }}" class="text-blue-400 hover:text-blue-600 p-1" title="Editar">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        @if ($user->id !== auth()->id())
                                            <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('¿Eliminar este usuario?')" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-400 hover:text-red-600 p-1" title="Eliminar">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t bg-gray-50">
                {{ $users->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>
                <p class="text-gray-500 font-medium">No hay usuarios registrados</p>
                <a href="{{ route('users.create') }}" class="mt-2 inline-block text-sm text-blue-600 hover:text-blue-800">Crear primer usuario</a>
            </div>
        @endif
    </div>
</div>
@endsection
