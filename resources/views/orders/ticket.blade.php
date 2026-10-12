<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $order->order_number }}</title>
    <style>
        /* Impresora térmica de 80 mm (área útil ~72 mm). */
        @page { size: 80mm auto; margin: 3mm; }
        * { box-sizing: border-box; }
        body { width: 72mm; margin: 0 auto; font-family: 'DejaVu Sans Mono', 'Courier New', monospace; font-size: 11px; color: #000; line-height: 1.35; }
        .c { text-align: center; }
        .b { font-weight: bold; }
        .big { font-size: 15px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        td.r { text-align: right; white-space: nowrap; }
        .qr { width: 32mm; height: 32mm; }
        .sign { margin-top: 18px; border-top: 1px solid #000; padding-top: 2px; }
        .no-print { margin: 12px 0; text-align: center; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Imprimir</button>
        <a href="{{ route('orders.show', $order) }}">Volver a la orden</a>
    </div>

    <div class="c b big">{{ $company['name'] }}</div>
    @if ($company['tax_id']) <div class="c">RUC {{ $company['tax_id'] }}</div> @endif
    @if ($company['address']) <div class="c">{{ $company['address'] }}</div> @endif
    @if ($company['phone']) <div class="c">Tel. {{ $company['phone'] }}</div> @endif
    <hr>
    <div class="c b big">ORDEN {{ $order->order_number }}</div>
    <div class="c">Ingreso: {{ $order->entry_date?->format('d/m/Y') }} · {{ $order->created_at?->format('H:i') }}</div>
    <hr>
    <table>
        <tr><td>Cliente:</td><td class="r b">{{ $order->client?->name }}</td></tr>
        @if ($order->client?->phone) <tr><td>Teléfono:</td><td class="r">{{ $order->client->phone }}</td></tr> @endif
        <tr><td>Equipo:</td><td class="r">{{ \App\Models\Order::deviceTypes()[$order->device_type] ?? $order->device_type }}</td></tr>
        <tr><td>Marca/modelo:</td><td class="r">{{ $order->brand?->name ?? $order->brand_text }} {{ $order->deviceModel?->name ?? $order->model_text }}</td></tr>
        @if ($order->serial_imei) <tr><td>Serie/IMEI:</td><td class="r">{{ $order->serial_imei }}</td></tr> @endif
    </table>
    <hr>
    <div class="b">Falla declarada:</div>
    <div>{{ $order->declared_fault }}</div>
    @if ($order->physical_condition)
        <div class="b" style="margin-top:4px">Estado físico:</div>
        <div>{{ $order->physical_condition }}</div>
    @endif
    @if ($order->accessories->count())
        <div class="b" style="margin-top:4px">Accesorios:</div>
        <div>{{ $order->accessories->pluck('name')->join(', ') }}</div>
    @endif
    <hr>
    <table>
        @if ((float) $order->total_amount > 0)
            <tr><td>Total:</td><td class="r">{{ money($order->total_amount) }}</td></tr>
        @endif
        <tr><td>Abonado:</td><td class="r">{{ money($order->amount_paid) }}</td></tr>
        @if ((float) $order->total_amount > 0)
            <tr class="b"><td>Saldo:</td><td class="r">{{ money($order->balance_due) }}</td></tr>
        @endif
        @if ($order->estimated_delivery)
            <tr><td>Entrega estimada:</td><td class="r">{{ $order->estimated_delivery->format('d/m/Y') }}</td></tr>
        @endif
    </table>
    <hr>
    <div class="c">Consulte el estado de su orden:</div>
    <div class="c"><img class="qr" src="{{ \App\Support\QrCode::dataUri($order->trackingUrl(), 4) }}" alt="QR de seguimiento"></div>
    <div class="c" style="font-size:9px;word-break:break-all">{{ $order->trackingUrl() }}</div>
    <hr>
    <div style="font-size:9px;white-space:pre-line">{{ $company['terms'] ?: 'Presente este ticket para retirar su equipo.' }}</div>
    <div class="sign c">Firma del cliente</div>

    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
