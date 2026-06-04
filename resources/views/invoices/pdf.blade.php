<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Factura #{{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 15mm; size: A4 portrait; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #000; margin: 0; padding: 0; line-height: 1.3; }
        table { width: 100%; border-collapse: collapse; }
        .header { border-bottom: 2pt solid #000; padding-bottom: 10pt; margin-bottom: 12pt; }
        .header h1 { font-size: 16pt; margin: 0; }
        .header .sub { font-size: 8pt; color: #666; }
        .client-box { margin-bottom: 12pt; }
        .client-box table td { padding: 1pt 0; font-size: 9pt; }
        .client-box .label { color: #666; font-size: 7.5pt; }
        .items { margin-bottom: 10pt; }
        .items th { border: 0.5pt solid #000; padding: 4pt 6pt; text-align: left; font-size: 8pt; background: #d9d9d9; font-weight: bold; }
        .items td { border: 0.5pt solid #000; padding: 3pt 6pt; text-align: left; font-size: 8.5pt; }
        .r { text-align: right; }
        .c { text-align: center; }
        .b { font-weight: bold; }
        .totals { margin-top: 4pt; }
        .totals td { padding: 2pt 6pt; font-size: 9pt; }
        .footer { text-align: center; font-size: 7pt; color: #888; border-top: 0.5pt solid #000; padding-top: 5pt; margin-top: 15pt; }
        .status-box { border: 0.5pt solid #000; padding: 4pt 8pt; font-size: 8pt; font-weight: bold; text-align: center; display: inline-block; }
    </style>
</head>
<body>

<div class="header">
    <table>
        <tr>
            <td style="width:60%">
                <h1>FACTURA</h1>
                <div class="sub">SolarFix — Sistema de Reparaciones</div>
                <div class="sub">RUC: 1234567890001</div>
            </td>
            <td style="width:40%;text-align:right">
                <div style="font-size:14pt;font-weight:bold">{{ $invoice->invoice_number }}</div>
                <div class="sub">Emisión: {{ $invoice->issued_at?->format('d/m/Y') ?? '—' }}</div>
                <div class="sub">Estado: {{ $statuses[$invoice->status] ?? $invoice->status }}</div>
                @if ($invoice->order)
                <div class="sub">Orden: #{{ $invoice->order->order_number }}</div>
                @endif
            </td>
        </tr>
    </table>
</div>

<div class="client-box">
    <table>
        <tr><td style="width:15%" class="label">Cliente</td><td style="width:35%"><strong>{{ $invoice->client_name }}</strong></td><td style="width:15%" class="label">Documento</td><td style="width:35%">{{ $invoice->client_document ?? '—' }}</td></tr>
        <tr><td class="label">Dirección</td><td>{{ $invoice->client_address ?? '—' }}</td><td class="label">Teléfono</td><td>{{ $invoice->client_phone ?? '—' }}</td></tr>
        <tr><td class="label">Email</td><td colspan="3">{{ $invoice->client_email ?? '—' }}</td></tr>
        @if ($invoice->device_type || $invoice->device_brand)
        <tr><td class="label">Dispositivo</td><td colspan="3">{{ $invoice->device_type ? $invoice->device_type . ' — ' : '' }}{{ $invoice->device_brand }} {{ $invoice->device_model }} {{ $invoice->device_serial ? '/ ' . $invoice->device_serial : '' }}</td></tr>
        @endif
    </table>
</div>

<table class="items">
    <tr>
        <th style="width:8%">#</th>
        <th style="width:52%">Descripción</th>
        <th style="width:10%" class="c">Cant.</th>
        <th style="width:15%" class="r">P. Unit.</th>
        <th style="width:15%" class="r">Subtotal</th>
    </tr>
    @foreach ($invoice->items as $i => $item)
    <tr>
        <td class="c">{{ $i + 1 }}</td>
        <td>{{ $item->description }}</td>
        <td class="c">{{ $item->quantity }}</td>
        <td class="r">${{ number_format($item->unit_price, 2) }}</td>
        <td class="r">${{ number_format($item->subtotal, 2) }}</td>
    </tr>
    @endforeach
</table>

<table class="totals">
    <tr><td style="width:70%"></td><td style="width:15%" class="r">Subtotal:</td><td style="width:15%" class="r">${{ number_format($invoice->subtotal, 2) }}</td></tr>
    @if ($invoice->iva_percent > 0)
    <tr><td></td><td class="r">IVA {{ $invoice->iva_percent }}%:</td><td class="r">${{ number_format($invoice->iva_amount, 2) }}</td></tr>
    @endif
    <tr style="font-weight:bold;font-size:11pt">
        <td></td>
        <td class="r">TOTAL:</td>
        <td class="r">${{ number_format($invoice->total, 2) }}</td>
    </tr>
</table>

<div style="margin-top:10pt">
    <div class="status-box">
        {{ $statuses[$invoice->status] ?? $invoice->status }}
        @if ($invoice->paid_at)
         — {{ $invoice->paid_at->format('d/m/Y') }}
        @endif
    </div>
</div>

@if ($invoicePayments->count())
<div style="margin-top:8pt">
    <strong style="font-size:8pt">Pagos recibidos:</strong>
    <table style="margin-top:2pt;width:auto">
        @foreach ($invoicePayments as $payment)
        <tr>
            <td style="padding:1pt 4pt;font-size:8pt">${{ number_format($payment->amount, 2) }}</td>
            <td style="padding:1pt 4pt;font-size:8pt;color:#666">{{ $payment->method === 'cash' ? 'Efectivo' : ($payment->method === 'transfer' ? 'Transf.' : ($payment->method === 'card' ? 'Tarjeta' : 'Otro')) }}</td>
            <td style="padding:1pt 4pt;font-size:8pt;color:#666">{{ $payment->paid_at?->format('d/m/Y') ?? '' }}</td>
        </tr>
        @endforeach
        <tr style="font-weight:bold"><td style="padding:1pt 4pt;font-size:8pt;border-top:0.5pt solid #000" colspan="2">Total pagado: ${{ number_format($invoicePayments->sum('amount'), 2) }}</td></tr>
    </table>
</div>
@endif

@if ($invoice->notes)
<div style="margin-top:8pt;font-size:8pt">
    <strong>Notas:</strong> {{ $invoice->notes }}
</div>
@endif

<div class="footer">
    SolarFix — {{ $invoice->createdBy->name ?? '—' }} — Generado {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
