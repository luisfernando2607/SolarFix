<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Comprobante #{{ $payment->id }}</title>
    <style>
        @page { margin: 5mm; size: 80mm 297mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #000; margin: 0; padding: 0; text-align: center; }
        .hdr { border-bottom: 1px dashed #000; padding-bottom: 4pt; margin-bottom: 6pt; }
        .hdr h1 { font-size: 12pt; margin: 0 0 2pt 0; }
        .hdr p { margin: 1pt 0; font-size: 7.5pt; color: #555; }
        table { width: 100%; border-collapse: collapse; margin: 6pt 0; }
        table td { padding: 2pt 0; font-size: 8.5pt; text-align: left; }
        table td.r { text-align: right; }
        table td.c { text-align: center; }
        .total { border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 3pt 0; margin: 4pt 0; font-size: 10pt; font-weight: bold; }
        .line { border-top: 1px dashed #000; margin: 6pt 0; }
        .footer { font-size: 7pt; color: #888; margin-top: 6pt; }
        .label { color: #555; font-size: 7.5pt; }
        .val { font-weight: bold; font-size: 9pt; }
    </style>
</head>
<body>

<div class="hdr">
    <h1>COMPROBANTE DE PAGO</h1>
    <p>SolarFix — Sistema de Reparaciones</p>
    <p>Orden #{{ $order->order_number }}</p>
</div>

<table>
    <tr><td style="width:40%" class="label">Cliente</td><td class="val">{{ $order->client->name ?? '—' }}</td></tr>
    <tr><td class="label">Documento</td><td>{{ $order->client->id_document ?? '—' }}</td></tr>
    <tr><td class="label">Teléfono</td><td>{{ $order->client->phone ?? '—' }}</td></tr>
    <tr><td class="label">Dispositivo</td><td>{{ \App\Models\Order::deviceTypes()[$order->device_type] ?? $order->device_type }} — {{ $order->brand?->name ?? $order->brand_text ?? '' }} {{ $order->deviceModel?->name ?? $order->model_text ?? '' }}</td></tr>
</table>

<div class="line"></div>

<table>
    <tr><td style="width:50%" class="label">Monto Pagado</td><td class="r val">${{ number_format($payment->amount, 2) }}</td></tr>
    <tr><td class="label">Método</td><td class="r">{{ $payment->method === 'cash' ? 'Efectivo' : ($payment->method === 'transfer' ? 'Transferencia' : ($payment->method === 'card' ? 'Tarjeta' : 'Otro')) }}</td></tr>
    @if ($payment->reference)
    <tr><td class="label">Referencia</td><td class="r">{{ $payment->reference }}</td></tr>
    @endif
    <tr><td class="label">Fecha</td><td class="r">{{ $payment->paid_at ? $payment->paid_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</td></tr>
    <tr><td class="label">Registrado por</td><td class="r">{{ $payment->registeredBy?->name ?? '—' }}</td></tr>
</table>

<div class="line"></div>

<table>
    <tr><td style="width:50%" class="label">Total Orden</td><td class="r">${{ number_format($order->total_amount, 2) }}</td></tr>
    <tr><td class="label">Total Pagado</td><td class="r">${{ number_format($order->amount_paid, 2) }}</td></tr>
    <tr><td class="label">Saldo Pendiente</td><td class="r {{ $order->balance_due > 0 ? 'val' : '' }}">${{ number_format($order->balance_due, 2) }}</td></tr>
</table>

<div class="total">
    @if ($order->balance_due <= 0)
        CANCELADO
    @else
        SALDO: ${{ number_format($order->balance_due, 2) }}
    @endif
</div>

@if ($payment->notes)
<div style="margin:4pt 0;font-size:8pt;text-align:left">
    <span class="label">Notas:</span> {{ $payment->notes }}
</div>
@endif

<div class="footer">
    <p>Generado {{ now()->format('d/m/Y H:i') }}</p>
    <p>Gracias por su preferencia</p>
</div>

</body>
</html>
