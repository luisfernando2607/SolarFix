@extends('layouts.app')

@section('title', 'Sucursales')

@section('content')
<div class="p-4 sm:p-6 max-w-4xl mx-auto">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Sucursales</h2>
            <p class="text-gray-500">El código aparece en el número de orden (ej. ORD-GYE-20261012-0001).</p>
        </div>
        <a href="{{ route('branches.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">+ Nueva sucursal</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full text-sm">
            <thead><tr class="border-b bg-gray-50/50 text-left text-gray-600">
                <th class="px-5 py-3 font-semibold">Sucursal</th><th class="px-3 py-3 font-semibold">Código</th>
                <th class="px-3 py-3 font-semibold text-right">Usuarios</th><th class="px-5 py-3 font-semibold text-right">Órdenes</th>
            </tr></thead>
            <tbody>
                @foreach ($branches as $branch)
                    <tr class="border-b {{ $branch->is_active ? '' : 'opacity-50' }}">
                        <td class="px-5 py-3">
                            <a href="{{ route('branches.edit', $branch) }}" class="font-medium text-blue-700 hover:underline">{{ $branch->name }}</a>
                            <span class="block text-xs text-gray-500">{{ $branch->address ?? 'Sin dirección' }}{{ $branch->phone ? ' · ' . $branch->phone : '' }}{{ $branch->is_active ? '' : ' · inactiva' }}</span>
                        </td>
                        <td class="px-3 py-3 font-mono">{{ $branch->code }}</td>
                        <td class="px-3 py-3 text-right">{{ $branch->users_count }}</td>
                        <td class="px-5 py-3 text-right">{{ $branch->orders_count }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
