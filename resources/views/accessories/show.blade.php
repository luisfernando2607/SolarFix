@extends('layouts.app')

@section('title', $accessory->name)

@section('content')
<div class="p-6 max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('accessories.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver a accesorios
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="p-6 bg-gradient-to-r from-amber-500 to-amber-600 text-white">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center text-2xl font-bold uppercase">
                    {{ substr($accessory->name, 0, 2) }}
                </div>
                <div>
                    <h2 class="text-xl font-bold">{{ $accessory->name }}</h2>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</p>
                    <p class="text-sm font-medium mt-1">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full {{ $accessory->is_active ? 'bg-green-500' : 'bg-red-500' }}"></span>
                            {{ $accessory->is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </p>
                </div>
            </div>

            <div class="pt-4 flex items-center gap-3 border-t">
                <a href="{{ route('accessories.edit', $accessory) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Editar Accesorio
                </a>
                <a href="{{ route('accessories.index') }}" class="text-gray-600 hover:text-gray-800 px-4 py-2 text-sm font-medium transition">
                    Volver
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
