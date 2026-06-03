@extends('layouts.app')

@section('title', 'Modelos')

@section('content')
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Modelos</h2>
            <p class="text-gray-500">Catálogo de modelos por marca</p>
        </div>
        <a href="{{ route('models.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nuevo Modelo
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        @if ($models->count())
            <div class="overflow-x-auto">
                <table data-table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Marca</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipo</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider no-sort">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($models as $model)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-800">{{ $model->name }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $model->brand->name }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $model->device_type === 'celular' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                        {{ $model->device_type === 'tablet' ? 'bg-orange-100 text-orange-800' : '' }}
                                        {{ $model->device_type === 'pc' ? 'bg-gray-100 text-gray-800' : '' }}
                                        {{ $model->device_type === 'aire_split' ? 'bg-cyan-100 text-cyan-800' : '' }}
                                        {{ $model->device_type === 'aire_central' ? 'bg-cyan-100 text-cyan-800' : '' }}
                                        {{ $model->device_type === 'televisor' ? 'bg-rose-100 text-rose-800' : '' }}
                                        {{ $model->device_type === 'otro' || !$model->device_type ? 'bg-slate-100 text-slate-800' : '' }}">
                                        @if ($model->device_type === 'celular')
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        @elseif ($model->device_type === 'tablet')
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        @elseif ($model->device_type === 'pc')
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        @elseif ($model->device_type === 'aire_split' || $model->device_type === 'aire_central')
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0l-3-3m3 3l3-3M5 18h14M5 21h14"/></svg>
                                        @elseif ($model->device_type === 'televisor')
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 21h6M12 17v4M5 13h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                        @else
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                        @endif
                                        {{ $model->device_type === 'celular' ? 'Celular' : ($model->device_type === 'aire_split' ? 'Split' : ($model->device_type === 'aire_central' ? 'Central' : ($model->device_type === 'pc' ? 'PC' : ($model->device_type === 'tablet' ? 'Tablet' : ($model->device_type === 'televisor' ? 'TV' : 'Otro'))))) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full {{ $model->is_active ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                        <span class="text-sm {{ $model->is_active ? 'text-green-700' : 'text-red-700' }}">
                                            {{ $model->is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('models.show', $model) }}" class="text-gray-400 hover:text-gray-600 p-1" title="Ver">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        <a href="{{ route('models.edit', $model) }}" class="text-blue-400 hover:text-blue-600 p-1" title="Editar">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <form method="POST" action="{{ route('models.destroy', $model) }}" onsubmit="return confirm('¿Eliminar este modelo?')" class="inline">
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
                {{ $models->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <p class="text-gray-500 font-medium">No hay modelos registrados</p>
                <a href="{{ route('models.create') }}" class="mt-2 inline-block text-sm text-blue-600 hover:text-blue-800">Crear primer modelo</a>
            </div>
        @endif
    </div>
</div>
@endsection
