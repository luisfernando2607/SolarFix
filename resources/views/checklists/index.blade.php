@extends('layouts.app')

@section('title', 'Checklists')

@section('content')
<div class="p-6 max-w-5xl mx-auto">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Checklists</h2>
        <p class="text-gray-500">Un checklist por tipo de equipo y etapa. Se completan desde cada orden.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50/50 text-left">
                        <th class="px-6 py-3 font-semibold text-gray-600">Tipo de equipo</th>
                        @foreach ($stages as $stageLabel)
                            <th class="px-6 py-3 font-semibold text-gray-600">{{ $stageLabel }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($deviceTypes as $typeKey => $typeLabel)
                        <tr class="border-b">
                            <td class="px-6 py-3 font-medium text-gray-800">{{ $typeLabel }}</td>
                            @foreach ($stages as $stageKey => $stageLabel)
                                @php $checklist = $checklists[$typeKey . '|' . $stageKey] ?? null; @endphp
                                <td class="px-6 py-3">
                                    @if ($checklist)
                                        <a href="{{ route('checklists.edit', $checklist) }}" class="text-blue-600 hover:underline">
                                            {{ $checklist->active_items_count }} ítems
                                        </a>
                                        @unless ($checklist->is_active)
                                            <span class="text-xs text-gray-400">(inactivo)</span>
                                        @endunless
                                    @else
                                        <a href="{{ route('checklists.create', ['device_type' => $typeKey, 'stage' => $stageKey]) }}" class="text-gray-400 hover:text-blue-600">+ Crear</a>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
