@extends('layouts.app')

@section('title', 'Mantenimientos preventivos')

@php
    $badge = ['overdue' => 'bg-red-100 text-red-800', 'due_soon' => 'bg-amber-100 text-amber-800', 'ok' => 'bg-green-100 text-green-800'];
    $tabs = ['due' => 'Vencidos y por vencer', 'all' => 'Todos los activos', 'inactive' => 'Inactivos'];
@endphp

@section('content')
<div class="p-6">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Mantenimientos preventivos</h2>
            <p class="text-gray-500">Planes por cliente; al completar la cita, se programa el siguiente.</p>
        </div>
        <a href="{{ route('maintenance-plans.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">+ Nuevo plan</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-6 py-3 border-b bg-gray-50/50 flex flex-wrap items-center gap-2">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('maintenance-plans.index', array_filter(['filter' => $key, 'q' => request('q')])) }}"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $filter === $key ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="overflow-x-auto">
            <table data-table data-search="server" data-search-placeholder="Buscar por equipo, cliente o teléfono..." class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50/50 text-left text-gray-600">
                        <th class="px-6 py-3 font-semibold">Equipo / cliente</th>
                        <th class="px-3 py-3 font-semibold">Cada</th>
                        <th class="px-3 py-3 font-semibold">Último</th>
                        <th class="px-3 py-3 font-semibold">Próximo</th>
                        <th class="px-6 py-3 font-semibold no-sort">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plans as $plan)
                        @php $status = $plan->dueStatus(); $open = $plan->appointments->first(); @endphp
                        <tr class="border-b align-top">
                            <td class="px-6 py-3">
                                <a href="{{ route('maintenance-plans.edit', $plan) }}" class="font-medium text-blue-600 hover:underline">{{ $plan->title }}</a>
                                <span class="block text-xs text-gray-500">
                                    <a href="{{ route('clients.show', $plan->client_id) }}" class="hover:underline">{{ $plan->client?->name }}</a>
                                    · {{ $plan->client?->phone }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">{{ $plan->interval_months }} {{ $plan->interval_months === 1 ? 'mes' : 'meses' }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $plan->last_done_at?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                {{ $plan->next_due_date->format('d/m/Y') }}
                                @if ($plan->is_active)
                                    <span class="ml-1 inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $badge[$status] }}">{{ \App\Models\MaintenancePlan::dueStatuses()[$status] }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex flex-wrap gap-2">
                                    @if ($open)
                                        <a href="{{ route('appointments.show', $open) }}" class="text-xs px-3 py-1.5 rounded-lg border bg-blue-50 text-blue-700">Cita {{ $open->scheduled_at->format('d/m H:i') }}</a>
                                    @elseif ($plan->is_active)
                                        <form method="POST" action="{{ route('maintenance-plans.whatsapp', $plan) }}" target="_blank">
                                            @csrf
                                            <button type="submit" class="text-xs px-3 py-1.5 rounded-lg border border-green-300 text-green-700 hover:bg-green-50">Avisar por WhatsApp</button>
                                        </form>
                                        <a href="{{ route('appointments.create', ['plan_id' => $plan->id]) }}" class="text-xs px-3 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Agendar</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">{{ $filter === 'due' ? 'No hay mantenimientos vencidos ni por vencer.' : 'Sin planes.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 border-t">{{ $plans->links() }}</div>
    </div>
</div>
@endsection
