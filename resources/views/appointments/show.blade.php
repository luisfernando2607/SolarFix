@extends('layouts.app')

@section('title', 'Cita · ' . $appointment->title)

@section('content')
<div class="p-6 max-w-3xl mx-auto">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('appointments.index', ['week' => $appointment->scheduled_at->toDateString()]) }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Volver a la agenda
            </a>
            <h2 class="text-2xl font-bold text-gray-800 mt-2">{{ $appointment->title }}</h2>
            <p class="text-gray-500">{{ \Illuminate\Support\Str::ucfirst($appointment->scheduled_at->translatedFormat('l d \d\e F \d\e Y')) }}, {{ $appointment->scheduled_at->format('H:i') }}–{{ $appointment->endsAt()->format('H:i') }}</p>
        </div>
        <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">{{ \App\Models\Appointment::statuses()[$appointment->status] }}</span>
        @if ($appointment->requested_online)
            <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">Solicitada en línea</span>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div><dt class="text-gray-500">Cliente</dt><dd class="font-medium text-gray-800">{{ $appointment->client?->name }} @if ($appointment->client?->phone) · {{ $appointment->client->phone }} @endif</dd></div>
            <div><dt class="text-gray-500">Tipo</dt><dd class="font-medium text-gray-800">{{ \App\Models\Appointment::types()[$appointment->type] ?? $appointment->type }}</dd></div>
            <div><dt class="text-gray-500">Técnico</dt><dd class="font-medium {{ $appointment->user ? 'text-gray-800' : 'text-red-600' }}">{{ $appointment->user?->name ?? 'Sin asignar' }}</dd></div>
            <div><dt class="text-gray-500">Dirección</dt><dd class="text-gray-800">
                {{ $appointment->address ?? '—' }}
                @if ($appointment->address)
                    <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($appointment->address) }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline text-xs ml-1">Ver mapa</a>
                @endif
            </dd></div>
            @if ($appointment->notes)
                <div class="sm:col-span-2"><dt class="text-gray-500">Notas</dt><dd class="text-gray-800 whitespace-pre-line">{{ $appointment->notes }}</dd></div>
            @endif
            <div><dt class="text-gray-500">Orden</dt><dd>
                @if ($appointment->order)
                    <a href="{{ route('orders.show', $appointment->order) }}" class="text-blue-600 hover:underline font-medium">#{{ $appointment->order->order_number }}</a>
                @else
                    <span class="text-gray-400">Sin orden</span>
                @endif
            </dd></div>
            @if ($appointment->maintenancePlan)
                <div><dt class="text-gray-500">Plan de mantenimiento</dt><dd>
                    <a href="{{ route('maintenance-plans.edit', $appointment->maintenancePlan) }}" class="text-blue-600 hover:underline">{{ $appointment->maintenancePlan->title }}</a>
                    <span class="block text-xs text-gray-500">Al completar la cita se programa el siguiente.</span>
                </dd></div>
            @endif
            <div><dt class="text-gray-500">Recordatorio</dt><dd class="text-gray-800">{{ $appointment->reminder_sent ? 'Enviado' : 'No enviado' }}</dd></div>
        </dl>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @if (! $appointment->order && $appointment->isOpen())
            <a href="{{ route('orders.create', ['appointment_id' => $appointment->id]) }}" class="text-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium">Crear orden de servicio</a>
        @endif
        @foreach (\App\Services\WhatsAppMessage::APPOINTMENT_TYPES as $waType => $waLabel)
            @if ($appointment->isOpen())
                <form method="POST" action="{{ route('appointments.whatsapp', [$appointment, $waType]) }}" target="_blank"
                    onsubmit="setTimeout(function () { window.location.reload(); }, 1500)">
                    @csrf
                    <button type="submit" class="w-full bg-white hover:bg-gray-50 text-green-700 border border-green-300 px-4 py-2.5 rounded-lg text-sm font-medium">WhatsApp: {{ strtolower($waLabel) }}</button>
                </form>
            @endif
        @endforeach
        @foreach (['confirmed' => 'Marcar confirmada', 'completed' => 'Marcar completada', 'cancelled' => 'Cancelar cita'] as $status => $label)
            @if ($appointment->status !== $status && ($appointment->isOpen() || $status !== 'cancelled'))
                <form method="POST" action="{{ route('appointments.status', $appointment) }}"
                    @if ($status === 'cancelled') onsubmit="return confirm('¿Cancelar esta cita?')" @endif>
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $status }}">
                    <button type="submit" class="w-full px-4 py-2.5 rounded-lg text-sm font-medium border {{ $status === 'cancelled' ? 'text-red-600 border-red-200 hover:bg-red-50' : 'text-gray-700 hover:bg-gray-50' }} bg-white">{{ $label }}</button>
                </form>
            @endif
        @endforeach
        @can('manage-appointments')
            <a href="{{ route('appointments.edit', $appointment) }}" class="text-center bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2.5 rounded-lg text-sm font-medium">Editar / reprogramar</a>
        @endcan
    </div>
</div>
@endsection
