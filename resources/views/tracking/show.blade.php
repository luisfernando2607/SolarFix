<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Orden {{ $order->order_number }} · {{ $company['name'] }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-800">
@php
    $flow = ['received', 'diagnosing', 'waiting_approval', 'repairing', 'ready', 'delivered'];
    $current = array_search($order->status, $flow, true);
    $closed = $order->status === 'closed_no_repair';
    $message = [
        'received' => 'Recibimos su equipo. Pronto lo revisará un técnico.',
        'diagnosing' => 'Un técnico está revisando su equipo.',
        'waiting_approval' => 'Le enviamos el presupuesto. Esperamos su aprobación para continuar.',
        'repairing' => 'Su equipo está en reparación.',
        'ready' => '¡Su equipo está listo! Puede pasar a retirarlo.',
        'delivered' => 'Su equipo fue entregado. ¡Gracias por su confianza!',
        'closed_no_repair' => 'La orden se cerró sin reparación.',
        'warranty' => 'Estamos atendiendo su reclamo de garantía.',
    ][$order->status] ?? '';
@endphp
<main class="max-w-lg mx-auto p-4 sm:p-6">
    <header class="text-center mb-6">
        <h1 class="text-xl font-bold">{{ $company['name'] }}</h1>
        <p class="text-sm text-gray-500">Seguimiento de su orden</p>
    </header>

    <section class="bg-white rounded-2xl shadow-sm border p-5 mb-4">
        <p class="text-xs text-gray-500">Orden</p>
        <p class="text-2xl font-bold tracking-tight">{{ $order->order_number }}</p>
        <p class="text-sm text-gray-600 mt-1">
            {{ \App\Models\Order::deviceTypes()[$order->device_type] ?? $order->device_type }}
            {{ $order->brand?->name ?? $order->brand_text }} {{ $order->deviceModel?->name ?? $order->model_text }}
        </p>

        <div class="mt-4 rounded-xl px-4 py-3 {{ $order->status === 'ready' ? 'bg-green-50 border border-green-200 text-green-900' : ($closed ? 'bg-gray-50 border text-gray-700' : 'bg-blue-50 border border-blue-200 text-blue-900') }}">
            <p class="font-semibold">{{ $statuses[$order->status] ?? $order->status }}</p>
            <p class="text-sm mt-0.5">{{ $message }}</p>
        </div>

        @if ($current !== false)
            <ol class="mt-5 space-y-3">
                @foreach ($flow as $i => $step)
                    <li class="flex items-center gap-3 text-sm">
                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                            {{ $i < $current ? 'bg-green-600 text-white' : ($i === $current ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500') }}">
                            {{ $i < $current ? '✓' : $i + 1 }}
                        </span>
                        <span class="{{ $i <= $current ? 'text-gray-800 font-medium' : 'text-gray-400' }}">{{ $statuses[$step] }}</span>
                        @php $when = $order->statusHistory->where('to_status', $step)->max('changed_at'); @endphp
                        @if ($when && $i <= $current)
                            <span class="ml-auto text-xs text-gray-400">{{ $when->format('d/m H:i') }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    <section class="bg-white rounded-2xl shadow-sm border p-5 mb-4">
        <dl class="grid grid-cols-2 gap-3 text-sm">
            <div><dt class="text-gray-500">Ingreso</dt><dd class="font-medium">{{ $order->entry_date?->format('d/m/Y') ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Entrega estimada</dt><dd class="font-medium">{{ $order->estimated_delivery?->format('d/m/Y') ?? 'Por confirmar' }}</dd></div>
            @if ((float) $order->total_amount > 0)
                <div><dt class="text-gray-500">Total</dt><dd class="font-medium">{{ money($order->total_amount) }}</dd></div>
                <div><dt class="text-gray-500">Abonado</dt><dd class="font-medium">{{ money($order->amount_paid) }}</dd></div>
                <div class="col-span-2 border-t pt-3 flex justify-between">
                    <dt class="font-semibold">Saldo pendiente</dt>
                    <dd class="font-bold {{ $order->balance_due > 0 ? 'text-red-600' : 'text-green-700' }}">{{ money($order->balance_due) }}</dd>
                </div>
            @endif
            @if ($order->warranty)
                <div class="col-span-2 border-t pt-3">
                    <dt class="text-gray-500">Garantía</dt>
                    <dd class="font-medium">{{ $order->warranty->isValid() ? 'Vigente hasta el ' . $order->warranty->end_date->format('d/m/Y') : 'Venció el ' . $order->warranty->end_date->format('d/m/Y') }}</dd>
                </div>
            @endif
        </dl>
    </section>

    @if ($company['phone'] || $company['address'])
        <section class="text-center text-sm text-gray-600 space-y-1">
            @if ($company['address']) <p>{{ $company['address'] }}</p> @endif
            @if ($company['phone'])
                <p><a href="tel:{{ preg_replace('/[^0-9+]/', '', $company['phone']) }}" class="text-blue-600 font-medium">{{ $company['phone'] }}</a></p>
            @endif
        </section>
    @endif

    <p class="text-center text-xs text-gray-400 mt-6">Actualizado: {{ now()->format('d/m/Y H:i') }}</p>
</main>
</body>
</html>
