<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Orden #{{ $order->order_number }}</title>
    <style>
        @page { margin: 8mm 10mm; size: A4 portrait; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #000; margin: 0; padding: 0; line-height: 1.15; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 7pt; }
        table th { border: 0.5pt solid #000; padding: 2.5pt 5pt; text-align: left; font-size: 8pt; background: #d9d9d9; font-weight: bold; }
        table td { border: 0.5pt solid #000; padding: 1pt 5pt; text-align: left; font-size: 8pt; }
        .w50 { width: 49%; display: inline-block; vertical-align: top; }
        .r { text-align: right; }
        .c { text-align: center; }
        .b { font-weight: bold; }
        .hdr { text-align: center; border-bottom: 2pt solid #000; padding-bottom: 5pt; margin-bottom: 8pt; }
        .hdr h1 { font-size: 13pt; margin: 0 0 2pt 0; letter-spacing: 1pt; }
        .hdr p { margin: 1pt 0; font-size: 8.5pt; }
        .ftr { text-align: center; font-size: 7pt; color: #888; border-top: 0.5pt solid #000; padding-top: 3pt; margin-top: 7pt; }
    </style>
</head>
<body>

<div class="hdr">
    <h1>ORDEN DE SERVICIO #{{ $order->order_number }}</h1>
    <p><strong>Ingreso:</strong> {{ $order->entry_date?->format('d/m/Y') ?? '—' }} &nbsp;|&nbsp; <strong>Estado:</strong> {{ $statuses[$order->status] ?? $order->status }} &nbsp;|&nbsp; <strong>Técnico:</strong> {{ $order->user->name ?? '—' }}</p>
</div>

<table>
    <tr>
        <th colspan="4">Cliente</th>
        <th colspan="4">Dispositivo</th>
    </tr>
    <tr>
        <td style="width:9%" class="b">Nombre</td>
        <td style="width:29%">{{ $order->client->name ?? '—' }}</td>
        <td style="width:8%" class="b">Documento</td>
        <td style="width:14%">{{ $order->client->id_document ?? '—' }}</td>
        <td style="width:7%" class="b">Tipo</td>
        <td style="width:15%">{{ \App\Models\Order::deviceTypes()[$order->device_type] ?? $order->device_type }}</td>
        <td style="width:7%" class="b">Serial</td>
        <td style="width:11%">{{ $order->serial_imei ?? '—' }}</td>
    </tr>
    <tr>
        <td class="b">Teléfono</td>
        <td>{{ $order->client->phone ?? '—' }}</td>
        <td class="b">Email</td>
        <td>{{ $order->client->email ?? '—' }}</td>
        <td class="b">Marca</td>
        <td>{{ $order->brand?->name ?? $order->brand_text ?? '—' }}</td>
        <td class="b">Modelo</td>
        <td>{{ $order->deviceModel?->name ?? $order->model_text ?? '—' }}</td>
    </tr>
    <tr>
        <td class="b">Falla</td>
        <td colspan="3">{{ $order->declared_fault ?? '—' }}</td>
        <td class="b">Condición</td>
        <td colspan="3">{{ $order->physical_condition ?? '—' }}</td>
    </tr>
    <tr>
        <td class="b">Diagnóstico</td>
        <td colspan="3">{{ $order->diagnosis ?? '—' }}</td>
        @if ($order->work_done)
        <td class="b">Trabajo</td>
        <td colspan="3">{{ $order->work_done }}</td>
        @else
        <td colspan="4"></td>
        @endif
    </tr>
</table>

<table>
    <tr>
        <th style="width:16%">Ingreso</th>
        <th style="width:16%">Entrega Est.</th>
        <th style="width:16%">Entrega Real</th>
        <th colspan="2">Accesorios Entregados</th>
    </tr>
    <tr>
        <td class="c">{{ $order->entry_date?->format('d/m/Y') ?? '—' }}</td>
        <td class="c">{{ $order->estimated_delivery?->format('d/m/Y') ?? '—' }}</td>
        <td class="c">{{ $order->delivery_date?->format('d/m/Y') ?? '—' }}</td>
        <td colspan="2">
            @if ($order->accessories->count())
                {{ $order->accessories->pluck('name')->implode(', ') }}
            @else
                <span style="color:#999">Ninguno</span>
            @endif
        </td>
    </tr>
</table>

<div>
    <div class="w50">
        <table>
            <tr><th style="width:10%">#</th><th style="width:60%">Concepto</th><th style="width:30%">Valor</th></tr>
            <tr><td class="c">1</td><td>Diagnóstico</td><td class="r">${{ number_format($order->diagnosis_cost, 2) }}</td></tr>
            <tr><td class="c">2</td><td>Mano de Obra</td><td class="r">${{ number_format($order->labor_cost, 2) }}</td></tr>
            <tr><td class="c">3</td><td>Repuestos</td><td class="r">${{ number_format($order->parts_cost, 2) }}</td></tr>
            @if ($order->surcharge_amount > 0)
            <tr><td class="c">4</td><td>Recargo ({{ $order->surcharge_percent }}%)</td><td class="r">${{ number_format($order->surcharge_amount, 2) }}</td></tr>
            @endif
            <tr><td></td><td class="b">TOTAL</td><td class="r b">${{ number_format($order->total_amount, 2) }}</td></tr>
            <tr><td></td><td>Pagado</td><td class="r">${{ number_format($order->amount_paid, 2) }}</td></tr>
            <tr><td></td><td class="b">SALDO</td><td class="r b">${{ number_format($order->balance_due, 2) }}</td></tr>
        </table>
    </div>
    <div class="w50">
        <table>
            <tr><th colspan="2">Pagos</th></tr>
            <tr><th style="width:55%">Monto</th><th style="width:45%">Método</th></tr>
            @if ($order->payments->count())
                @foreach ($order->payments as $payment)
                <tr>
                    <td class="r">${{ number_format($payment->amount, 2) }}</td>
                    <td>{{ $payment->method === 'cash' ? 'Efectivo' : ($payment->method === 'transfer' ? 'Transferencia' : ($payment->method === 'card' ? 'Tarjeta' : 'Otro')) }}</td>
                </tr>
                @endforeach
            @else
            <tr><td colspan="2" style="text-align:center;color:#999;padding:4pt">Sin pagos registrados</td></tr>
            @endif
        </table>
    </div>
</div>

<table>
    <tr>
        <th style="width:20%">Historial de Estados</th>
        <th style="width:18%">Fecha</th>
        <th style="width:62%">Notas</th>
    </tr>
    <tr>
        <td colspan="3" style="border:none;padding:0">
            <table style="margin:0;width:100%">
                @if ($order->statusHistory->count())
                    @foreach ($order->statusHistory->sortByDesc('changed_at') as $history)
                    <tr>
                        <td style="width:20%;border:0.5pt solid #000;padding:2pt 5pt;font-size:8pt">{{ $statuses[$history->to_status] ?? $history->to_status }}</td>
                        <td style="width:18%;border:0.5pt solid #000;padding:2pt 5pt;font-size:8pt;text-align:center">{{ $history->changed_at?->format('d/m/Y') ?? '' }}</td>
                        @if ($loop->first)
                        <td style="width:62%;border:0.5pt solid #000;padding:2pt 5pt;font-size:8pt" rowspan="{{ $order->statusHistory->count() }}">{{ $order->notes ?? '—' }}</td>
                        @endif
                    </tr>
                    @endforeach
                @else
                    <tr>
                        <td style="width:20%;border:0.5pt solid #000;padding:2pt 5pt;color:#999" colspan="2">Sin cambios de estado</td>
                        <td style="width:62%;border:0.5pt solid #000;padding:2pt 5pt">{{ $order->notes ?? '—' }}</td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<div class="ftr">
    Generado el {{ now()->format('d/m/Y \a \l\a\s H:i') }} — SolarFix Sistema de Reparaciones
</div>

</body>
</html>
