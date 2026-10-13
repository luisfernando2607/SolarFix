<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Presupuesto {{ $order->order_number }}</title>
    <style>
        @page { margin: 12mm 14mm; size: A4 portrait; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #111; margin: 0; line-height: 1.3; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .muted { color: #555; font-size: 8.5pt; }
        h1 { font-size: 16pt; margin: 0; letter-spacing: 1pt; }
        .box { border: 0.6pt solid #999; padding: 6pt 8pt; margin-top: 10pt; }
        .items { margin-top: 10pt; }
        .items th { background: #e5e7eb; text-align: left; padding: 4pt 6pt; font-size: 8.5pt; border-bottom: 0.6pt solid #999; }
        .items td { padding: 4pt 6pt; border-bottom: 0.4pt solid #ddd; }
        .r { text-align: right; }
        .total td { font-weight: bold; font-size: 11pt; border-top: 1pt solid #111; border-bottom: none; }
        .approve { margin-top: 14pt; border: 1pt solid #111; padding: 8pt; }
        .sign { margin-top: 30pt; }
        .sign td { width: 50%; padding-top: 2pt; border-top: 0.6pt solid #111; text-align: center; font-size: 8.5pt; }
        .ftr { margin-top: 14pt; font-size: 7.5pt; color: #777; text-align: center; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width:60%">
                @if ($company['logo']) <img src="{{ $company['logo'] }}" alt="" style="max-height:48pt;max-width:160pt;margin-bottom:4pt"><br> @endif
                <strong style="font-size:11pt">{{ $company['name'] }}</strong>{{ $company['branch'] ? ' — ' . $company['branch'] : '' }}<br>
                <span class="muted">{{ collect([$company['tax_id'] ? 'RUC ' . $company['tax_id'] : null, $company['address'], $company['phone'], $company['email']])->filter()->join(' · ') }}</span>
            </td>
            <td style="width:40%;text-align:right">
                <h1>PRESUPUESTO</h1>
                <div>Orden <strong>{{ $order->order_number }}</strong></div>
                <div class="muted">Fecha: {{ now()->format('d/m/Y') }}</div>
                <div class="muted">Válido hasta: {{ now()->addDays($validDays)->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="box">
        <table>
            <tr>
                <td style="width:50%"><span class="muted">Cliente</span><br><strong>{{ $order->client?->name }}</strong>
                    @if ($order->client?->id_document)<br><span class="muted">C.I./RUC {{ $order->client->id_document }}</span>@endif
                    @if ($order->client?->phone)<br><span class="muted">Tel. {{ $order->client->phone }}</span>@endif</td>
                <td style="width:50%"><span class="muted">Equipo</span><br><strong>{{ \App\Models\Order::deviceTypes()[$order->device_type] ?? $order->device_type }} {{ $order->brand?->name ?? $order->brand_text }} {{ $order->deviceModel?->name ?? $order->model_text }}</strong>
                    @if ($order->serial_imei)<br><span class="muted">Serie/IMEI {{ $order->serial_imei }}</span>@endif
                    <br><span class="muted">Falla reportada: {{ $order->declared_fault }}</span></td>
            </tr>
        </table>
    </div>

    @if ($order->diagnosis)
        <div class="box"><span class="muted">Diagnóstico</span><br>{{ $order->diagnosis }}</div>
    @endif

    <table class="items">
        <thead><tr><th>Concepto</th><th class="r" style="width:30%">Valor</th></tr></thead>
        <tbody>
            @forelse ($concepts as [$label, $amount])
                <tr><td>{{ $label }}</td><td class="r">{{ money($amount) }}</td></tr>
            @empty
                <tr><td colspan="2" class="muted">Sin valores registrados todavía.</td></tr>
            @endforelse
            <tr class="total"><td>TOTAL{{ $pricesIncludeIva ? ' (incluye IVA)' : ' (más IVA al facturar)' }}</td><td class="r">{{ money($order->total_amount) }}</td></tr>
            @if ((float) $order->amount_paid > 0)
                <tr><td class="muted">Abonado</td><td class="r muted">{{ money($order->amount_paid) }}</td></tr>
                <tr><td><strong>Saldo</strong></td><td class="r"><strong>{{ money($order->balance_due) }}</strong></td></tr>
            @endif
        </tbody>
    </table>

    <div class="approve">
        <table>
            <tr>
                <td style="vertical-align:top">
                    <strong>¿Aprueba la reparación?</strong><br>
                    @if ($onlineApproval && $order->tracking_token)
                        Puede aprobarla o rechazarla en línea escaneando el código QR o en:<br>
                        <span class="muted">{{ $order->trackingUrl() }}</span><br>
                    @endif
                    También puede confirmarnos por teléfono o WhatsApp{{ $company['phone'] ? ' al ' . $company['phone'] : '' }}.
                    @if ($order->warranty_days > 0)<br><span class="muted">Garantía de {{ $order->warranty_days }} días sobre el trabajo realizado.</span>@endif
                </td>
                @if ($onlineApproval && $order->tracking_token)
                    <td style="width:80pt;text-align:right"><img src="{{ \App\Support\QrCode::dataUri($order->trackingUrl(), 3) }}" alt="" style="width:72pt;height:72pt"></td>
                @endif
            </tr>
        </table>
    </div>

    <table class="sign">
        <tr><td>Firma del cliente</td><td style="border-left:20pt solid #fff">{{ $company['name'] }}</td></tr>
    </table>

    <div class="ftr">Presupuesto sujeto a cambios si al reparar aparecen daños adicionales; en ese caso se pedirá una nueva aprobación.</div>
</body>
</html>
