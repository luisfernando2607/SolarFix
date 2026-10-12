@extends('layouts.app')

@section('title', 'Clientes duplicados')

@section('content')
<div class="p-4 sm:p-6 max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('clients.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Volver a clientes</a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">Posibles clientes duplicados</h2>
        <p class="text-gray-500">Registros con el mismo teléfono o la misma cédula. Elige cuál conservar: los demás se unen a él con todas sus órdenes, citas, planes y facturas.</p>
    </div>

    @forelse ($groups as $index => $group)
        <form method="POST" action="{{ route('clients.merge-group') }}" class="bg-white rounded-xl shadow-sm border mb-4"
            onsubmit="return confirm('¿Unir estos clientes? Los registros no elegidos se eliminan y su historial pasa al que conservas.')">
            @csrf
            <div class="px-5 py-3 border-b text-sm text-gray-700">Misma {{ $group['reason'] }}: <strong>{{ $group['value'] }}</strong></div>
            <ul class="divide-y">
                @foreach ($group['clients'] as $client)
                    <li class="px-5 py-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                        <label class="flex items-center gap-3">
                            <input type="radio" name="target_id" value="{{ $client->id }}" {{ $loop->first ? 'checked' : '' }} class="w-4 h-4"
                                onchange="this.form.querySelectorAll('.source-id').forEach(function (i) { i.disabled = i.value === '{{ $client->id }}'; })">
                            <span>
                                <a href="{{ route('clients.show', $client) }}" class="font-medium text-blue-700 hover:underline">{{ $client->name }}</a>
                                <span class="block text-xs text-gray-500">{{ $client->phone }}{{ $client->id_document ? ' · ' . $client->id_document : '' }}{{ $client->email ? ' · ' . $client->email : '' }} · desde {{ $client->created_at?->format('d/m/Y') }}</span>
                            </span>
                        </label>
                        <span class="text-xs text-gray-500">{{ $client->orders_count }} {{ $client->orders_count === 1 ? 'orden' : 'órdenes' }}</span>
                        <input type="hidden" name="source_ids[]" value="{{ $client->id }}" class="source-id" {{ $loop->first ? 'disabled' : '' }}>
                    </li>
                @endforeach
            </ul>
            <div class="px-5 py-3 border-t text-right">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Conservar el elegido y unir los demás</button>
            </div>
        </form>
    @empty
        <div class="bg-white rounded-xl shadow-sm border p-8 text-center text-sm text-gray-500">No se encontraron clientes duplicados.</div>
    @endforelse
</div>
@endsection
