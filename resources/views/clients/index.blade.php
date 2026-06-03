@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Clientes</h2>
            <p class="text-gray-500">Gestión de clientes</p>
        </div>
        <a href="{{ route('clients.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo Cliente
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        @if ($clients->count())
            <div class="overflow-x-auto">
                <table data-table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Teléfono</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipo</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Documento</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider no-sort">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($clients as $client)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-800">{{ $client->name }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $client->phone }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $client->email ?? '—' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $client->client_type === 'individual' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $client->client_type === 'business' ? 'bg-purple-100 text-purple-800' : '' }}
                                        {{ $client->client_type === 'frequent' ? 'bg-green-100 text-green-800' : '' }}">
                                        {{ $client->client_type === 'individual' ? 'Persona' : ($client->client_type === 'business' ? 'Empresa' : 'Frecuente') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $client->id_document ?? '—' }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('clients.show', $client) }}" class="text-gray-400 hover:text-gray-600 p-1" title="Ver">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        <a href="{{ route('clients.edit', $client) }}" class="text-blue-400 hover:text-blue-600 p-1" title="Editar">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <form method="POST" action="{{ route('clients.destroy', $client) }}" onsubmit="return confirm('¿Eliminar este cliente?')" class="inline">
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
                {{ $clients->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <p class="text-gray-500 font-medium">No hay clientes registrados</p>
                <a href="{{ route('clients.create') }}" class="mt-2 inline-block text-sm text-blue-600 hover:text-blue-800">Crear primer cliente</a>
            </div>
        @endif
    </div>
</div>
@endsection
