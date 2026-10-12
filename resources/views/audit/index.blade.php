@extends('layouts.app')

@section('title', 'Auditoría')

@php
    $eventBadge = [
        'created' => 'bg-green-100 text-green-800',
        'updated' => 'bg-blue-100 text-blue-800',
        'deleted' => 'bg-red-100 text-red-800',
        'login' => 'bg-gray-100 text-gray-700',
        'logout' => 'bg-gray-100 text-gray-700',
        'login_failed' => 'bg-amber-100 text-amber-800',
    ];
    $field = 'px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white';
@endphp

@section('content')
<div class="p-6">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Auditoría</h2>
        <p class="text-gray-500">Quién creó, modificó o eliminó registros, y los accesos al sistema.</p>
    </div>

    <form method="GET" action="{{ route('audit.index') }}" class="bg-white rounded-xl shadow-sm border p-4 mb-6 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <select name="user_id" class="{{ $field }}">
            <option value="">Todos los usuarios</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}" {{ (string) request('user_id') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
            @endforeach
        </select>
        <select name="type" class="{{ $field }}">
            <option value="">Todo tipo</option>
            @foreach (\App\Models\AuditLog::types() as $key => $label)
                <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <select name="event" class="{{ $field }}">
            <option value="">Toda acción</option>
            @foreach (\App\Models\AuditLog::events() as $key => $label)
                <option value="{{ $key }}" {{ request('event') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="{{ $field }}" title="Desde">
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="{{ $field }}" title="Hasta">
        <div class="flex gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Orden, factura..." class="{{ $field }} min-w-0 flex-1">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm font-medium">Filtrar</button>
        </div>
    </form>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50/50 text-left">
                        <th class="px-4 py-3 font-semibold text-gray-600 whitespace-nowrap">Fecha</th>
                        <th class="px-4 py-3 font-semibold text-gray-600">Usuario</th>
                        <th class="px-4 py-3 font-semibold text-gray-600">Acción</th>
                        <th class="px-4 py-3 font-semibold text-gray-600">Registro</th>
                        <th class="px-4 py-3 font-semibold text-gray-600">Cambios</th>
                        <th class="px-4 py-3 font-semibold text-gray-600">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-b align-top">
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $log->user?->name ?? 'Sistema' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap {{ $eventBadge[$log->event] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ \App\Models\AuditLog::events()[$log->event] ?? $log->event }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($log->order_id)
                                    <a href="{{ route('orders.show', $log->order_id) }}" class="text-blue-600 hover:underline">{{ $log->description }}</a>
                                @else
                                    {{ $log->description ?? (\App\Models\AuditLog::types()[$log->auditable_type] ?? '') }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">@include('audit.partials.changes', ['log' => $log])</td>
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Sin registros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
