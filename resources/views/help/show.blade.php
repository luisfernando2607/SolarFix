@extends('layouts.app')

@section('title', 'Ayuda · ' . $manuals[$manual])

@section('content')
<div class="p-4 sm:p-6 max-w-4xl mx-auto">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-2xl font-bold text-gray-800">Ayuda</h2>
        <a href="{{ route('help.pdf', $manual) }}" target="_blank" class="bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium">Descargar PDF</a>
    </div>

    @if (count($manuals) > 1)
        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Manuales">
            @foreach ($manuals as $key => $label)
                <a href="{{ route('help.show', $key) }}"
                    class="px-3 py-1.5 rounded-full text-sm font-medium {{ $key === $manual ? 'bg-blue-600 text-white' : 'bg-white border text-gray-700 hover:bg-gray-50' }}"
                    @if ($key === $manual) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    @endif

    <article class="manual bg-white rounded-xl shadow-sm border p-5 sm:p-8">
        {!! $html !!}
    </article>
</div>
@endsection
