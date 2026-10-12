@extends('layouts.app')

@section('title', 'Firma de ' . strtolower(\App\Models\OrderSignature::stages()[$stage]))

@section('content')
<div class="p-6 max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('orders.show', $order) }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Volver a la orden
        </a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">
            {{ $stage === 'reception' ? 'Firma de recepción' : ($order->isReturnWithoutRepair() ? 'Devolución sin reparación' : 'Firma de entrega') }} · Orden #{{ $order->order_number }}
        </h2>
        <p class="text-gray-500">Muestra este resumen al cliente y pídele que firme.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Cliente</dt>
                <dd class="font-medium text-gray-800">{{ $order->client?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Equipo</dt>
                <dd class="font-medium text-gray-800">
                    {{ \App\Models\Order::deviceTypes()[$order->device_type] ?? $order->device_type }}
                    {{ $order->brand?->name ?? $order->brand_text }} {{ $order->deviceModel?->name ?? $order->model_text }}
                </dd>
            </div>
            @if ($stage === 'reception')
                <div class="sm:col-span-2">
                    <dt class="text-gray-500">Falla declarada</dt>
                    <dd class="text-gray-800">{{ $order->declared_fault }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-gray-500">Estado físico</dt>
                    <dd class="text-gray-800">{{ $order->physical_condition ?: '—' }}</dd>
                </div>
                @if ($damagedItems->isNotEmpty())
                    <div class="sm:col-span-2 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3">
                        <dt class="text-amber-800 font-medium">Daños registrados en la revisión</dt>
                        <dd class="text-amber-900 mt-1">
                            <ul class="list-disc list-inside">
                                @foreach ($damagedItems as $result)
                                    <li>{{ $result->item->label }}{{ $result->notes ? ': ' . $result->notes : '' }}</li>
                                @endforeach
                            </ul>
                        </dd>
                    </div>
                @endif
                <div class="sm:col-span-2">
                    <dt class="text-gray-500">Accesorios recibidos</dt>
                    <dd class="text-gray-800">{{ $order->accessories->pluck('name')->join(', ') ?: 'Ninguno' }}</dd>
                </div>
            @elseif ($order->isReturnWithoutRepair())
                <div class="sm:col-span-2">
                    <dt class="text-gray-500">Diagnóstico</dt>
                    <dd class="text-gray-800">{{ $order->diagnosis ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Costo de diagnóstico</dt>
                    <dd class="font-medium text-gray-800">{{ money($order->diagnosis_cost) }}</dd>
                </div>
                @if ($order->balance_due > 0)
                    <div class="sm:col-span-2 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-red-700">
                        Hay un saldo pendiente de <strong>{{ money($order->balance_due) }}</strong>. Cobra el diagnóstico antes de devolver el equipo.
                    </div>
                @endif
            @else
                <div class="sm:col-span-2">
                    <dt class="text-gray-500">Trabajo realizado</dt>
                    <dd class="text-gray-800">{{ $order->work_done ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Total</dt>
                    <dd class="font-medium text-gray-800">{{ money($order->total_amount) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Garantía</dt>
                    <dd class="font-medium text-gray-800">{{ $order->warranty_days > 0 ? $order->warranty_days . ' días' : 'Sin garantía' }}</dd>
                </div>
                @if ($order->balance_due > 0)
                    <div class="sm:col-span-2 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-red-700">
                        Hay un saldo pendiente de <strong>{{ money($order->balance_due) }}</strong>. Regístralo antes de entregar el equipo.
                    </div>
                @endif
            @endif
        </dl>
        <p class="mt-4 text-sm text-gray-600 border-t pt-4">
            @if ($stage === 'reception')
                Con mi firma confirmo que los datos del equipo, su estado físico y los accesorios entregados son correctos.
            @elseif ($order->isReturnWithoutRepair())
                Con mi firma confirmo que retiro mi equipo sin reparar, en el mismo estado en que lo entregué.
            @else
                Con mi firma confirmo que recibí el equipo conforme y que se me explicaron el trabajo realizado y la garantía.
            @endif
        </p>
    </div>

    <form method="POST" action="{{ route('orders.signatures.store', [$order, $stage]) }}" id="signatureForm" class="bg-white rounded-xl shadow-sm border p-6">
        @csrf
        <input type="hidden" name="signature" id="signatureInput">
        <div class="flex items-center justify-between mb-2">
            <label class="text-sm font-medium text-gray-700">Firma del cliente</label>
            <button type="button" id="clearSignature" class="text-sm text-gray-500 hover:text-gray-700">Borrar</button>
        </div>
        <canvas id="signaturePad" class="w-full h-56 border-2 border-dashed border-gray-300 rounded-lg bg-white touch-none cursor-crosshair"></canvas>
        @error('signature') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        <p id="signatureEmpty" class="hidden text-sm text-red-600 mt-1">Falta la firma del cliente.</p>

        @if ($stage === 'delivery' && ! $order->isReturnWithoutRepair() && $order->balance_due > 0)
            @can('register-payments')
                <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-semibold text-amber-900 mb-3">Cobrar el saldo antes de entregar</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <input type="number" name="payment_amount" step="0.01" min="0" max="{{ $order->balance_due }}" inputmode="decimal"
                            value="{{ old('payment_amount', $order->balance_due) }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm @error('payment_amount') border-red-500 @enderror">
                        <select name="payment_method" class="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white @error('payment_method') border-red-500 @enderror">
                            @foreach (\App\Support\PaymentMethods::all() as $methodKey => $methodLabel)
                                <option value="{{ $methodKey }}" {{ old('payment_method', 'cash') === $methodKey ? 'selected' : '' }}>{{ $methodLabel }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="payment_reference" maxlength="100" placeholder="Referencia (opcional)" value="{{ old('payment_reference') }}"
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    @error('payment_amount') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    @error('payment_method') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-amber-800 mt-2">Deja 0 si el cliente no paga ahora; el saldo queda pendiente.</p>
                </div>
            @endcan
        @endif

        <button type="submit" class="mt-4 w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg text-sm font-medium transition">
            {{ $stage === 'reception' ? 'Guardar firma de recepción' : ($order->isReturnWithoutRepair() ? 'Firmar devolución' : 'Firmar y marcar como entregado') }}
        </button>
    </form>
</div>

<script>
(function () {
    var canvas = document.getElementById('signaturePad');
    var ctx = canvas.getContext('2d');
    var drawing = false;
    var hasInk = false;

    function resize() {
        var ratio = window.devicePixelRatio || 1;
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.lineWidth = 2.2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#111827';
        hasInk = false;
    }

    function point(e) {
        var rect = canvas.getBoundingClientRect();
        return { x: e.clientX - rect.left, y: e.clientY - rect.top };
    }

    canvas.addEventListener('pointerdown', function (e) {
        drawing = true;
        canvas.setPointerCapture(e.pointerId);
        var p = point(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        ctx.lineTo(p.x + 0.1, p.y + 0.1);
        ctx.stroke();
        hasInk = true;
    });
    canvas.addEventListener('pointermove', function (e) {
        if (!drawing) return;
        var p = point(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (evt) {
        canvas.addEventListener(evt, function () { drawing = false; });
    });

    document.getElementById('clearSignature').addEventListener('click', function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasInk = false;
    });

    document.getElementById('signatureForm').addEventListener('submit', function (e) {
        if (!hasInk) {
            e.preventDefault();
            document.getElementById('signatureEmpty').classList.remove('hidden');
            return;
        }
        document.getElementById('signatureInput').value = canvas.toDataURL('image/png');
    });

    resize();
    // Solo si cambia el ancho: en móviles la barra del navegador dispara "resize" al hacer scroll.
    var lastWidth = canvas.offsetWidth;
    window.addEventListener('resize', function () {
        if (canvas.offsetWidth !== lastWidth) {
            lastWidth = canvas.offsetWidth;
            resize();
        }
    });
})();
</script>
@endsection
