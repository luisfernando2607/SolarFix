@extends('layouts.app')

@section('title', $client->name)

@section('content')
<div class="p-6 max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('clients.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver a clientes
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="p-6 bg-gradient-to-r from-green-500 to-green-600 text-white">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center text-2xl font-bold uppercase">
                    {{ substr($client->name, 0, 2) }}
                </div>
                <div>
                    <h2 class="text-xl font-bold">{{ $client->name }}</h2>
                    <p class="text-green-100">{{ $client->phone }}</p>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Teléfono</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $client->phone }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Correo Electrónico</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $client->email ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipo de Cliente</p>
                    <p class="text-sm font-medium text-gray-800 mt-1 capitalize">
                        {{ $client->client_type === 'individual' ? 'Persona' : ($client->client_type === 'business' ? 'Empresa' : 'Frecuente') }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Cédula / RUC</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $client->id_document ?? '—' }}</p>
                </div>
                <div class="col-span-2">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Dirección</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $client->address ?? '—' }}</p>
                </div>
                @if ($client->notes)
                <div class="col-span-2">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Notas</p>
                    <p class="text-sm text-gray-800 mt-1">{{ $client->notes }}</p>
                </div>
                @endif
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Registrado</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $client->created_at->format('d/m/Y h:i A') }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Última actualización</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $client->updated_at->format('d/m/Y h:i A') }}</p>
                </div>
            </div>

            <div class="pt-4 flex items-center gap-3 border-t">
                <a href="{{ route('clients.edit', $client) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Editar Cliente
                </a>
                <a href="{{ route('clients.index') }}" class="text-gray-600 hover:text-gray-800 px-4 py-2 text-sm font-medium transition">
                    Volver
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
