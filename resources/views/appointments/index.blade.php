@extends('layouts.app')

@section('title', 'Agenda')

@php
    $statusBadge = [
        'pending' => 'bg-amber-100 text-amber-800',
        'confirmed' => 'bg-blue-100 text-blue-800',
        'completed' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-gray-100 text-gray-500 line-through',
    ];
    $query = fn ($week) => array_filter(['week' => $week->toDateString(), 'user_id' => request('user_id')]);
@endphp

@section('content')
<div class="p-6">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Agenda</h2>
            <p class="text-gray-500">Semana del {{ $weekStart->format('d/m') }} al {{ $weekStart->copy()->endOfWeek()->format('d/m/Y') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('appointments.index', $query($weekStart->copy()->subWeek())) }}" class="bg-white border px-3 py-2 rounded-lg text-sm hover:bg-gray-50" aria-label="Semana anterior">←</a>
            <a href="{{ route('appointments.index', $query(today())) }}" class="bg-white border px-3 py-2 rounded-lg text-sm hover:bg-gray-50">Hoy</a>
            <a href="{{ route('appointments.index', $query($weekStart->copy()->addWeek())) }}" class="bg-white border px-3 py-2 rounded-lg text-sm hover:bg-gray-50" aria-label="Semana siguiente">→</a>
            @unless (Auth::user()->isTechnician())
                <form method="GET" action="{{ route('appointments.index') }}">
                    <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
                    <select name="user_id" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                        <option value="">Todos los técnicos</option>
                        @foreach ($technicians as $technician)
                            <option value="{{ $technician->id }}" {{ (string) request('user_id') === (string) $technician->id ? 'selected' : '' }}>{{ $technician->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endunless
            @can('manage-appointments')
                <a href="{{ route('appointments.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">+ Nueva cita</a>
            @endcan
        </div>
    </div>

    @if ($reminders->count())
        <div class="mb-6 bg-white rounded-xl shadow-sm border border-amber-200">
            <div class="px-6 py-3 border-b bg-amber-50">
                <h3 class="text-sm font-semibold text-amber-800">Recordatorios por enviar · citas de mañana ({{ $reminders->count() }})</h3>
            </div>
            <ul class="divide-y">
                @foreach ($reminders as $reminder)
                    <li class="px-6 py-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span>
                            <span class="font-medium">{{ $reminder->scheduled_at->format('H:i') }}</span> · {{ $reminder->client?->name }} · {{ $reminder->title }}
                            @unless ($reminder->user_id) <span class="text-red-600 text-xs">· sin técnico</span> @endunless
                        </span>
                        <form method="POST" action="{{ route('appointments.whatsapp', [$reminder, 'appointment_reminder']) }}" target="{{ \App\Services\WhatsAppCloud::enabled() ? '_self' : '_blank' }}"
                            onsubmit="setTimeout(function () { window.location.reload(); }, 1500)">
                            @csrf
                            <button type="submit" class="text-xs bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg">Enviar recordatorio</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-4">
        @foreach ($days as $day)
            @php $dayAppointments = $appointments[$day->toDateString()] ?? collect(); @endphp
            <div class="bg-white rounded-xl shadow-sm border {{ $day->isToday() ? 'border-blue-300' : '' }}">
                <div class="px-5 py-3 border-b flex items-center justify-between {{ $day->isToday() ? 'bg-blue-50' : 'bg-gray-50/50' }}">
                    <h3 class="text-sm font-semibold text-gray-800">
                        {{ \Illuminate\Support\Str::ucfirst($day->translatedFormat('l d \d\e F')) }}
                        @if ($day->isToday()) <span class="ml-1 text-xs font-medium text-blue-700">· hoy</span> @endif
                    </h3>
                    <span class="text-xs text-gray-500">{{ $dayAppointments->count() ? $dayAppointments->count() . ($dayAppointments->count() === 1 ? ' cita' : ' citas') : 'Sin citas' }}</span>
                </div>
                @if ($dayAppointments->count())
                    <ul class="divide-y">
                        @foreach ($dayAppointments as $appointment)
                            <li>
                                <a href="{{ route('appointments.show', $appointment) }}" class="px-5 py-3 flex flex-wrap items-start gap-x-4 gap-y-1 hover:bg-gray-50">
                                    <span class="w-24 shrink-0 text-sm font-semibold text-gray-800">{{ $appointment->scheduled_at->format('H:i') }}–{{ $appointment->endsAt()->format('H:i') }}</span>
                                    <span class="flex-1 min-w-48">
                                        <span class="block text-sm font-medium text-gray-800">{{ $appointment->title }}</span>
                                        <span class="block text-xs text-gray-500">
                                            {{ \App\Models\Appointment::types()[$appointment->type] ?? $appointment->type }} · {{ $appointment->client?->name }}
                                            @if ($appointment->address) · {{ $appointment->address }} @endif
                                        </span>
                                    </span>
                                    <span class="text-xs {{ $appointment->user ? 'text-gray-600' : 'text-red-600 font-medium' }}">{{ $appointment->user?->name ?? 'Sin técnico' }}</span>
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $statusBadge[$appointment->status] }}">{{ \App\Models\Appointment::statuses()[$appointment->status] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
