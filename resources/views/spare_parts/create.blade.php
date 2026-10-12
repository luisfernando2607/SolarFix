@extends('layouts.app')

@section('title', 'Nuevo repuesto')

@section('content')
<div class="p-6 max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('spare-parts.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver
        </a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">Nuevo repuesto</h2>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <form method="POST" action="{{ route('spare-parts.store') }}">
            @csrf

            @include('spare_parts._form')
            <div class="mt-6">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">Crear repuesto</button>
            </div>
        </form>
    </div>
</div>
@endsection
