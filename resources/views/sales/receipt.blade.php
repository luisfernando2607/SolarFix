<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Venta {{ $sale->sale_number }}</title>
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
        .no-print { margin: 12px 0; text-align: center; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Imprimir</button>
        <a href="{{ route('sales.show', $sale) }}">Volver a la venta</a>
    </div>

    <div class="c b big">{{ $company['name'] }}</div>
    @if ($company['tax_id']) <div class="c">RUC {{ $company['tax_id'] }}</div> @endif
    @if ($company['address']) <div class="c">{{ $company['address'] }}</div> @endif
    @if ($company['phone']) <div class="c">Tel. {{ $company['phone'] }}</div> @endif
    <hr>
    <div class="c b">VENTA {{ $sale->sale_number }}</div>
    <div class="c">{{ $sale->sold_at->format('d/m/Y H:i') }}</div>
    @if ($sale->isCancelled()) <div class="c b big">*** ANULADA ***</div> @endif
    <div>Cliente: {{ $sale->customerLabel() }}</div>
    <hr>
    <table>
        @foreach ($sale->items as $item)
            <tr><td colspan="2">{{ $item->description }}</td></tr>
            <tr><td>{{ $item->quantity }} x {{ money($item->unit_price) }}</td><td class="r">{{ money($item->subtotal) }}</td></tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr class="b big"><td>TOTAL</td><td class="r">{{ money($sale->total) }}</td></tr>
        <tr><td>Pago:</td><td class="r">{{ $sale->method_label }}{{ $sale->reference ? ' · ' . $sale->reference : '' }}</td></tr>
        <tr><td>Atendió:</td><td class="r">{{ $sale->soldBy?->name }}</td></tr>
    </table>
    <hr>
    <div class="c">¡Gracias por su compra!</div>
    <div class="c">Comprobante interno, no válido como factura.</div>
</body>
</html>
