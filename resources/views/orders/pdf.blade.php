<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Orden #{{ $order->order_number }}</title>
    <style>
        @page { margin: 12px; size: A4 portrait; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #000; margin: 0; padding: 0; line-height: 1.25; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table th, table td { border: 1px solid #000; padding: 2px 4px; text-align: left; font-size: 8px; }
        table th { background: #ddd; font-weight: bold; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 6px; }
        .header h1 { font-size: 13px; margin: 0; }
        .header p { margin: 1px 0; font-size: 8px; }
        .footer { text-align: center; font-size: 7px; color: #666; border-top: 1px solid #000; padding-top: 3px; margin-top: 6px; }
        .row { margin-bottom: 2px; }
        .label { display: inline; }
        .label:after { content: ': '; }
        .value { font-weight: bold; }
        .inline-block { display: inline-block; }
        .w-50 { width: 49%; display: inline-block; vertical-align: top; }
        .mt-1 { margin-top: 3px; }
        .col-3 { width: 32%; display: inline-block; vertical-align: top; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Orden de Servicio #{{ $order->order_number }}</h1>
        <p>{{ $order->entry_date ? $order->entry_date->format('d/m/Y') : '' }} — {{ $statuses[$order->status] ?? $order->status }} — Técnico: {{ $order->user->name ?? '—' }}</p>
    </div>

    <table>
        <tr>
            <th colspan="4">Cliente</th>
        </tr>
        <tr>
            <td class="bold" style="width:12%">Nombre</td>
            <td style="width:38%">{{ $order->client->name ?? '—' }}</td>
            <td class="bold" style="width:12%">Documento</td>
            <td style="width:38%">{{ $order->client->id_document ?? '—' }}</td>
        </tr>
        <tr>
            <td class="bold">Teléfono</td>
            <td>{{ $order->client->phone ?? '—' }}</td>
            <td class="bold">Email</td>
            <td>{{ $order->client->email ?? '—' }}</td>
        </tr>
    </table>

    <table>
        <tr>
            <th colspan="4">Dispositivo</th>
        </tr>
        <tr>
            <td class="bold" style="width:12%">Tipo</td>
            <td style="width:25%">{{ \App\Models\Order::deviceTypes()[$order->device_type] ?? $order->device_type }}</td>
            <td class="bold" style="width:12%">Marca</td>
            <td style="width:25%">{{ $order->brand?->name ?? $order->brand_text ?? '—' }}</td>
            <td class="bold" style="width:10%">Modelo</td>
            <td style="width:16%">{{ $order->deviceModel?->name ?? $order->model_text ?? '—' }}</td>
        </tr>
        <tr>
            <td class="bold">Serial/IMEI</td>
            <td>{{ $order->serial_imei ?? '—' }}</td>
            <td class="bold">Falla</td>
            <td colspan="3">{{ $order->declared_fault ?? '—' }}</td>
        </tr>
        <tr>
            <td class="bold">Condición</td>
            <td>{{ $order->physical_condition ?? '—' }}</td>
            <td class="bold">Diagnóstico</td>
            <td colspan="3">{{ $order->diagnosis ?? '—' }}</td>
        </tr>
        @if ($order->work_done)
        <tr>
            <td class="bold">Trabajo Realizado</td>
            <td colspan="5">{{ $order->work_done }}</td>
        </tr>
        @endif
    </table>

    <table>
        <tr>
            <th colspan="6">Fechas</th>
        </tr>
        <tr>
            <td class="bold" style="width:12%">Ingreso</td>
            <td style="width:20%">{{ $order->entry_date ? $order->entry_date->format('d/m/Y') : '—' }}</td>
            <td class="bold" style="width:15%">Entrega Est.</td>
            <td style="width:20%">{{ $order->estimated_delivery ? $order->estimated_delivery->format('d/m/Y') : '—' }}</td>
            <td class="bold" style="width:15%">Entrega Real</td>
            <td style="width:18%">{{ $order->delivery_date ? $order->delivery_date->format('d/m/Y') : '—' }}</td>
        </tr>
    </table>

    <table>
        <tr>
            <th style="width:50%">Costos</th>
            <th style="width:25%">Valor</th>
            <th style="width:25%">Pagos</th>
        </tr>
        <tr>
            <td>Diagnóstico</td>
            <td class="right">${{ number_format($order->diagnosis_cost, 2) }}</td>
            <td rowspan="{{ 4 + ($order->payments->count() ?: 1) }}" style="vertical-align:top;padding:0">
                @if ($order->payments->count())
                <table style="margin:0;border:none">
                    <tr><th style="border:none;border-bottom:1px solid #000;background:transparent;font-size:7px;padding:1px 3px">Monto</th><th style="border:none;border-bottom:1px solid #000;background:transparent;font-size:7px;padding:1px 3px">Método</th></tr>
                    @foreach ($order->payments as $payment)
                    <tr>
                        <td style="border:none;padding:1px 3px;font-size:8px">${{ number_format($payment->amount, 2) }}</td>
                        <td style="border:none;padding:1px 3px;font-size:8px">{{ $payment->method === 'cash' ? 'Efectivo' : ($payment->method === 'transfer' ? 'Transf.' : ($payment->method === 'card' ? 'Tarjeta' : 'Otro')) }}</td>
                    </tr>
                    @endforeach
                </table>
                @else
                <div style="padding:4px;text-align:center;color:#666;font-size:7px">Sin pagos</div>
                @endif
            </td>
        </tr>
        <tr><td>Mano de Obra</td><td class="right">${{ number_format($order->labor_cost, 2) }}</td></tr>
        <tr><td>Repuestos</td><td class="right">${{ number_format($order->parts_cost, 2) }}</td></tr>
        @if ($order->surcharge_amount > 0)
        <tr><td>Recargo ({{ $order->surcharge_percent }}%)</td><td class="right">${{ number_format($order->surcharge_amount, 2) }}</td></tr>
        @endif
        <tr class="bold"><td>Total</td><td class="right">${{ number_format($order->total_amount, 2) }}</td></tr>
        <tr><td>Pagado</td><td class="right">${{ number_format($order->amount_paid, 2) }}</td></tr>
        <tr class="bold"><td>Saldo</td><td class="right">${{ number_format($order->balance_due, 2) }}</td></tr>
    </table>

    <table>
        <tr>
            <th style="width:50%">Accesorios Entregados</th>
            <th style="width:50%">Historial de Estados</th>
        </tr>
        <tr>
            <td style="vertical-align:top;padding:4px">
                @if ($order->accessories->count())
                    @foreach ($order->accessories as $acc)
                        <div style="font-size:8px">• {{ $acc->name }}</div>
                    @endforeach
                @else
                    <div style="color:#666;font-size:7px;text-align:center">Ninguno</div>
                @endif
            </td>
            <td style="vertical-align:top;padding:0">
                @if ($order->statusHistory->count())
                <table style="margin:0;border:none">
                    <tr><th style="border:none;border-bottom:1px solid #000;background:transparent;font-size:7px;padding:1px 3px">Estado</th><th style="border:none;border-bottom:1px solid #000;background:transparent;font-size:7px;padding:1px 3px">Fecha</th></tr>
                    @foreach ($order->statusHistory->sortByDesc('changed_at') as $history)
                    <tr>
                        <td style="border:none;padding:1px 3px;font-size:8px">{{ $statuses[$history->to_status] ?? $history->to_status }}</td>
                        <td style="border:none;padding:1px 3px;font-size:8px">{{ $history->changed_at ? $history->changed_at->format('d/m/Y') : '' }}</td>
                    </tr>
                    @endforeach
                </table>
                @else
                <div style="padding:4px;text-align:center;color:#666;font-size:7px">Sin cambios</div>
                @endif
            </td>
        </tr>
    </table>

    @if ($order->notes)
    <table>
        <tr><th>Notas</th></tr>
        <tr><td style="padding:3px 4px">{{ $order->notes }}</td></tr>
    </table>
    @endif

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }} — SolarFix Sistema de Reparaciones
    </div>
</body>
</html>
