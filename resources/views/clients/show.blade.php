@extends('layouts.app')

@section('title', $client->name)

@section('content')
<div class="p-6 max-w-6xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('clients.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver a clientes
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden h-fit">
        <div class="p-6 bg-gradient-to-r from-green-500 to-green-600 text-white">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center text-2xl font-bold uppercase">
                    {{ substr($client->name, 0, 2) }}
                </div>
                <div>
                    <h2 class="text-xl font-bold">{{ $client->name }}</h2>
                    <p class="text-green-100">{{ $client->phone }}</p>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Teléfono</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $client->phone }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Correo Electrónico</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $client->email ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipo de Cliente</p>
                    <p class="text-sm font-medium text-gray-800 mt-1 capitalize">
                        {{ $client->client_type === 'individual' ? 'Persona' : ($client->client_type === 'business' ? 'Empresa' : 'Frecuente') }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Cédula / RUC</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $client->id_document ?? '—' }}</p>
                </div>
                <div class="col-span-2">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Dirección</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $client->address ?? '—' }}</p>
                </div>
                @if ($client->notes)
                <div class="col-span-2">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Notas</p>
                    <p class="text-sm text-gray-800 mt-1">{{ $client->notes }}</p>
                </div>
                @endif
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Registrado</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $client->created_at->format('d/m/Y h:i A') }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Última actualización</p>
                    <p class="text-sm font-medium text-gray-800 mt-1">{{ $client->updated_at->format('d/m/Y h:i A') }}</p>
                </div>
            </div>

            <div class="pt-4 flex items-center gap-3 border-t">
                <a href="{{ route('clients.edit', $client) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Editar Cliente
                </a>
                <a href="{{ route('orders.create', ['client_id' => $client->id]) }}" class="bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium transition">
                    Nueva orden
                </a>
                @can('manage-appointments')
                    <a href="{{ route('appointments.create', ['client_id' => $client->id]) }}" class="bg-white hover:bg-gray-50 text-gray-700 border px-4 py-2 rounded-lg text-sm font-medium transition">
                        Agendar cita
                    </a>
                @endcan
            </div>
        </div>
    </div>

    <div class="lg:col-span-2 space-y-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ([
                ['Órdenes', $stats['orders'], 'text-gray-800'],
                ['Total pagado', '$' . number_format($stats['paid'], 2), 'text-gray-800'],
                ['Saldo pendiente', '$' . number_format($stats['balance'], 2), $stats['balance'] > 0 ? 'text-red-600' : 'text-gray-800'],
                ['Garantías vigentes', $warranties->count(), 'text-gray-800'],
            ] as [$label, $value, $color])
                <div class="bg-white rounded-xl shadow-sm border p-4">
                    <p class="text-xs text-gray-500">{{ $label }}</p>
                    <p class="text-xl font-bold mt-1 {{ $color }}">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        @if ($appointments->count())
            <div class="bg-white rounded-xl shadow-sm border">
                <h3 class="px-6 py-3 border-b text-base font-semibold text-gray-800">Próximas citas</h3>
                <ul class="divide-y">
                    @foreach ($appointments as $appointment)
                        <li><a href="{{ route('appointments.show', $appointment) }}" class="px-6 py-2 block text-sm hover:bg-gray-50">
                            <span class="font-medium">{{ $appointment->scheduled_at->format('d/m/Y H:i') }}</span> · {{ $appointment->title }}
                            <span class="text-xs text-gray-500">{{ $appointment->user ? '· ' . $appointment->user->name : '· sin técnico' }}</span>
                        </a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        @can('manage-appointments')
        <div class="bg-white rounded-xl shadow-sm border">
            <div class="px-6 py-3 border-b flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-800">Mantenimientos preventivos</h3>
                <a href="{{ route('maintenance-plans.create', ['client_id' => $client->id]) }}" class="text-sm text-blue-600 hover:underline">+ Nuevo plan</a>
            </div>
            @forelse ($plans as $plan)
                <a href="{{ route('maintenance-plans.edit', $plan) }}" class="px-6 py-2 flex justify-between text-sm hover:bg-gray-50 border-b last:border-b-0">
                    <span>{{ $plan->title }} <span class="text-xs text-gray-500">· cada {{ $plan->interval_months }} meses</span></span>
                    <span class="{{ $plan->dueStatus() === 'overdue' ? 'text-red-600 font-medium' : ($plan->dueStatus() === 'due_soon' ? 'text-amber-700' : 'text-gray-500') }}">próximo {{ $plan->next_due_date->format('d/m/Y') }}</span>
                </a>
            @empty
                <p class="px-6 py-3 text-sm text-gray-400">Sin planes de mantenimiento.</p>
            @endforelse
        </div>
        @endcan

        @if ($warranties->count())
            <div class="bg-white rounded-xl shadow-sm border">
                <h3 class="px-6 py-3 border-b text-base font-semibold text-gray-800">Garantías vigentes</h3>
                <ul class="divide-y">
                    @foreach ($warranties as $warranty)
                        <li><a href="{{ route('orders.show', $warranty->order_id) }}" class="px-6 py-2 flex justify-between text-sm hover:bg-gray-50">
                            <span>Orden {{ $orders->firstWhere('id', $warranty->order_id)?->order_number }} · {{ \App\Models\Warranty::types()[$warranty->warranty_type] ?? '' }}</span>
                            <span class="text-gray-500">hasta {{ $warranty->end_date->format('d/m/Y') }}</span>
                        </a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <h3 class="px-6 py-3 border-b text-base font-semibold text-gray-800">Órdenes ({{ $stats['orders'] }})</h3>
            @if ($orders->count())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50/50 text-left text-gray-600">
                                <th class="px-6 py-2 font-semibold">Orden</th>
                                <th class="px-3 py-2 font-semibold">Equipo</th>
                                <th class="px-3 py-2 font-semibold">Estado</th>
                                <th class="px-3 py-2 font-semibold text-right">Total</th>
                                <th class="px-6 py-2 font-semibold text-right">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr class="border-b">
                                    <td class="px-6 py-2">
                                        <a href="{{ route('orders.show', $order) }}" class="text-blue-600 hover:underline font-medium">{{ $order->order_number }}</a>
                                        <span class="block text-xs text-gray-400">{{ $order->entry_date?->format('d/m/Y') }}</span>
                                    </td>
                                    <td class="px-3 py-2">{{ \App\Models\Order::deviceTypes()[$order->device_type] ?? $order->device_type }} {{ $order->brand?->name ?? $order->brand_text }} {{ $order->deviceModel?->name ?? $order->model_text }}</td>
                                    <td class="px-3 py-2">{{ $statuses[$order->status] ?? $order->status }}</td>
                                    <td class="px-3 py-2 text-right">${{ number_format($order->total_amount, 2) }}</td>
                                    <td class="px-6 py-2 text-right {{ $order->balance_due > 0 ? 'text-red-600 font-medium' : 'text-gray-500' }}">${{ number_format($order->balance_due, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($stats['orders'] > $orders->count())
                    <p class="px-6 py-2 text-xs text-gray-500">Mostrando las {{ $orders->count() }} más recientes.</p>
                @endif
            @else
                <p class="px-6 py-6 text-sm text-gray-500 text-center">Sin órdenes.</p>
            @endif
        </div>
    </div>
    </div>
</div>
@endsection
