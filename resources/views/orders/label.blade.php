<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiqueta {{ $order->order_number }}</title>
    <style>
        /* Etiqueta de 50 × 30 mm para impresoras térmicas de etiquetas. */
        @page { size: 50mm 30mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #000; background: #f3f4f6; }
        .label { width: 50mm; height: 30mm; padding: 1.5mm; background: #fff; display: flex; gap: 1.5mm; overflow: hidden; margin: 8px auto; }
        .qr { width: 22mm; height: 22mm; flex-shrink: 0; align-self: center; }
        .text { min-width: 0; display: flex; flex-direction: column; justify-content: center; line-height: 1.15; }
        .number { font-size: 9pt; font-weight: bold; word-break: break-all; }
        .line { font-size: 7pt; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .actions { text-align: center; font-size: 14px; margin: 12px; }
        @media print { body { background: #fff; } .label { margin: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="label">
        <img class="qr" src="{{ \App\Support\QrCode::dataUri(route('orders.show', $order), 3) }}" alt="QR de la orden">
        <div class="text">
            <div class="number">{{ $order->order_number }}</div>
            <div class="line">{{ $order->client?->name }}</div>
            <div class="line">{{ trim(($order->brand?->name ?? $order->brand_text) . ' ' . ($order->deviceModel?->name ?? $order->model_text)) ?: (\App\Models\Order::deviceTypes()[$order->device_type] ?? $order->device_type) }}</div>
            <div class="line">Ingreso {{ $order->entry_date?->format('d/m/Y') }}</div>
        </div>
    </div>
    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir etiqueta</button>
    </div>
    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
