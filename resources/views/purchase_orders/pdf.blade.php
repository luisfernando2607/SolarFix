<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $order->number }}</title>
    <style>
        @page { margin: 12mm 14mm; size: A4 portrait; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #111; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #555; font-size: 8.5pt; }
        h1 { font-size: 15pt; margin: 0; }
        .box { border: 0.6pt solid #999; padding: 6pt 8pt; margin-top: 10pt; }
        .items { margin-top: 10pt; }
        .items th { background: #e5e7eb; text-align: left; padding: 4pt 6pt; font-size: 8.5pt; }
        .items td { padding: 4pt 6pt; border-bottom: 0.4pt solid #ddd; }
        .r { text-align: right; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width:60%;vertical-align:top">
                @if ($company['logo']) <img src="{{ $company['logo'] }}" alt="" style="max-height:44pt;max-width:150pt;margin-bottom:4pt"><br> @endif
                <strong>{{ $company['name'] }}</strong>{{ $company['branch'] ? ' — ' . $company['branch'] : '' }}<br>
                <span class="muted">{{ collect([$company['tax_id'] ? 'RUC ' . $company['tax_id'] : null, $company['address'], $company['phone'], $company['email']])->filter()->join(' · ') }}</span>
            </td>
            <td style="width:40%;text-align:right;vertical-align:top">
                <h1>ORDEN DE COMPRA</h1>
                <div><strong>{{ $order->number }}</strong></div>
                <div class="muted">Fecha: {{ ($order->sent_at ?? $order->created_at)->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="box">
        <span class="muted">Proveedor</span><br>
        <strong>{{ $order->supplier?->name ?? '—' }}</strong>
        @if ($order->supplier?->ruc)<br><span class="muted">RUC {{ $order->supplier->ruc }}</span>@endif
        @if ($order->supplier?->contact_name || $order->supplier?->phone)<br><span class="muted">{{ collect([$order->supplier?->contact_name, $order->supplier?->phone, $order->supplier?->email])->filter()->join(' · ') }}</span>@endif
    </div>

    <table class="items">
        <thead><tr><th>Código</th><th>Repuesto</th><th class="r">Cantidad</th><th class="r">Costo unit.</th><th class="r">Subtotal</th></tr></thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->sparePart?->code }}</td>
                    <td>{{ $item->sparePart?->name }}</td>
                    <td class="r">{{ $item->quantity }}</td>
                    <td class="r">{{ money($item->unit_cost) }}</td>
                    <td class="r">{{ money($item->quantity * $item->unit_cost) }}</td>
                </tr>
            @endforeach
            <tr><td colspan="4" class="r"><strong>Total estimado</strong></td><td class="r"><strong>{{ money($order->total()) }}</strong></td></tr>
        </tbody>
    </table>

    @if ($order->notes)
        <div class="box"><span class="muted">Notas</span><br>{{ $order->notes }}</div>
    @endif
</body>
</html>
