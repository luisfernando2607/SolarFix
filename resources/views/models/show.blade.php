@extends('layouts.app')

@section('title', $model->name)

@section('content')
<div class="p-6 max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('models.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver a modelos
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="p-6 bg-gradient-to-r from-teal-500 to-teal-600 text-white">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center text-2xl font-bold">
                    {{ substr($model->name, 0, 2) }}
                </div>
                <div>
                    <h2 class="text-xl font-bold">{{ $model->name }}</h2>
                    <p class="text-teal-100">{{ $model->brand->name }}</p>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Marca</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $model->brand->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipo</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium
                            {{ $model->device_type === 'celular' ? 'bg-indigo-100 text-indigo-800' : '' }}
                            {{ $model->device_type === 'tablet' ? 'bg-orange-100 text-orange-800' : '' }}
                            {{ $model->device_type === 'pc' ? 'bg-gray-100 text-gray-800' : '' }}
                            {{ $model->device_type === 'aire_split' ? 'bg-cyan-100 text-cyan-800' : '' }}
                            {{ $model->device_type === 'aire_central' ? 'bg-cyan-100 text-cyan-800' : '' }}
                            {{ $model->device_type === 'televisor' ? 'bg-rose-100 text-rose-800' : '' }}
                            {{ !$model->device_type || $model->device_type === 'otro' ? 'bg-slate-100 text-slate-800' : '' }}">
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
                            {{ $model->device_type === 'celular' ? 'Celular' : ($model->device_type === 'aire_split' ? 'Aire Split' : ($model->device_type === 'aire_central' ? 'Aire Central' : ($model->device_type === 'pc' ? 'PC / Laptop' : ($model->device_type === 'tablet' ? 'Tablet' : ($model->device_type === 'televisor' ? 'Televisor' : 'Otro'))))) }}
                        </span>
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</p>
                    <p class="text-sm font-medium mt-1">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full {{ $model->is_active ? 'bg-green-500' : 'bg-red-500' }}"></span>
                            {{ $model->is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </p>
                </div>
            </div>

            <div class="pt-4 flex items-center gap-3 border-t">
                <a href="{{ route('models.edit', $model) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Editar Modelo
                </a>
                <a href="{{ route('models.index') }}" class="text-gray-600 hover:text-gray-800 px-4 py-2 text-sm font-medium transition">
                    Volver
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
