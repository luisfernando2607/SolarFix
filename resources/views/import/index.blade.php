@extends('layouts.app')

@section('title', 'Importar datos')

@php
    $labels = ['clients' => 'Clientes', 'parts' => 'Repuestos'];
    $help = [
        'clients' => 'Se omiten los que ya existen (misma cédula o teléfono). "nombre" y "telefono" son obligatorios.',
        'parts' => 'Si el código ya existe, se actualizan nombre, categoría, precios y mínimo. "stock" queda como conteo inicial en la sucursal activa.',
    ];
    $result = session('importResult');
@endphp

@section('content')
<div class="p-4 sm:p-6 max-w-4xl mx-auto">
    <h2 class="text-2xl font-bold text-gray-800">Importar datos</h2>
    <p class="text-gray-500 mb-6">Carga clientes o repuestos desde Excel: descarga la plantilla, llénala y guárdala como <strong>CSV</strong> (Archivo → Guardar como → CSV).</p>

    @if ($result)
        <div class="mb-6 rounded-xl border {{ $result['errors'] ? 'border-amber-200 bg-amber-50' : 'border-green-200 bg-green-50' }} px-5 py-4 text-sm" role="status">
            <p class="font-semibold text-gray-800">Importación de {{ strtolower($labels[$result['type']]) }} terminada</p>
            <p class="mt-1 text-gray-700">{{ $result['created'] }} nuevos · {{ $result['updated'] }} actualizados · {{ $result['skipped'] }} ya existían · {{ count($result['errors']) }} con error</p>
            @if ($result['errors'])
                <ul class="mt-2 list-disc pl-5 text-amber-900 max-h-48 overflow-y-auto">
                    @foreach (array_slice($result['errors'], 0, 50) as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach ($columns as $type => $fields)
            <form method="POST" action="{{ route('import.store') }}" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border p-6 flex flex-col">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <h3 class="text-base font-semibold text-gray-800">{{ $labels[$type] }}</h3>
                <p class="text-xs text-gray-500 mt-1">Columnas: {{ implode(', ', $fields) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $help[$type] }}</p>
                <a href="{{ route('import.template', $type) }}" class="mt-3 text-sm text-blue-600 hover:underline">Descargar plantilla</a>
                <label for="file_{{ $type }}" class="block text-sm font-medium text-gray-700 mt-4 mb-1">Archivo CSV</label>
                <input type="file" id="file_{{ $type }}" name="file" accept=".csv,text/csv" required
                    class="block w-full text-sm text-gray-700 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-gray-100 file:text-gray-700">
                <button type="submit" class="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Importar {{ strtolower($labels[$type]) }}</button>
            </form>
        @endforeach
    </div>
    @error('file') <p class="text-sm text-red-600 mt-3">{{ $message }}</p> @enderror
</div>
@endsection
