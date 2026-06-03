@extends('layouts.app')

@section('title', 'Accesorios')

@section('content')
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Accesorios</h2>
            <p class="text-gray-500">Catálogo de accesorios que entregan los clientes</p>
        </div>
        <a href="{{ route('accessories.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo Accesorio
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        @if ($accessories->count())
            <div class="overflow-x-auto">
                <table data-table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider no-sort">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($accessories as $accessory)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-800">{{ $accessory->name }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full {{ $accessory->is_active ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                        <span class="text-sm {{ $accessory->is_active ? 'text-green-700' : 'text-red-700' }}">
                                            {{ $accessory->is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('accessories.show', $accessory) }}" class="text-gray-400 hover:text-gray-600 p-1" title="Ver">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        <a href="{{ route('accessories.edit', $accessory) }}" class="text-blue-400 hover:text-blue-600 p-1" title="Editar">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <form method="POST" action="{{ route('accessories.destroy', $accessory) }}" onsubmit="return confirm('¿Eliminar este accesorio?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-400 hover:text-red-600 p-1" title="Eliminar">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t bg-gray-50">
                {{ $accessories->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <p class="text-gray-500 font-medium">No hay accesorios registrados</p>
                <a href="{{ route('accessories.create') }}" class="mt-2 inline-block text-sm text-blue-600 hover:text-blue-800">Crear primer accesorio</a>
            </div>
        @endif
    </div>
</div>
@endsection
