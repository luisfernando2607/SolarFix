@extends('layouts.app')

@section('title', 'Estado del sistema')

@php
    $styles = [
        'ok' => ['Listo', 'bg-green-100 text-green-800'],
        'warn' => ['Recomendado', 'bg-amber-100 text-amber-800'],
        'fail' => ['Falta', 'bg-red-100 text-red-800'],
    ];
    $failures = collect($checks)->where('status', 'fail')->count();
@endphp

@section('content')
<div class="p-4 sm:p-6 max-w-4xl mx-auto">
    <h2 class="text-2xl font-bold text-gray-800">Estado del sistema</h2>
    <p class="text-gray-500 mb-6">Lo necesario para usar SolarFix con clientes reales. En el servidor: <code class="text-sm bg-gray-100 px-1.5 py-0.5 rounded">php artisan solarfix:check</code></p>

    <div class="mb-4 rounded-xl border px-5 py-3 text-sm {{ $failures ? 'border-red-200 bg-red-50 text-red-900' : 'border-green-200 bg-green-50 text-green-900' }}">
        {{ $failures ? "Hay {$failures} punto(s) por resolver." : 'Todo lo indispensable está listo.' }}
    </div>

    <ul class="bg-white rounded-xl shadow-sm border divide-y">
        @foreach ($checks as $check)
            <li class="px-5 py-3 flex flex-wrap items-start gap-3">
                <span class="shrink-0 inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $styles[$check['status']][1] }}">{{ $styles[$check['status']][0] }}</span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-800">{{ $check['label'] }}</p>
                    @if ($check['help'])
                        <p class="text-sm text-gray-600 mt-0.5 break-words">{{ $check['help'] }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</div>
@endsection
