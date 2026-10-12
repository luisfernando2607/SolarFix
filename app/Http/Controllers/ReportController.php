<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderPart;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use App\Support\PaymentMethods;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{

    public function index(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : now()->endOfDay();

        $payments = OrderPayment::whereBetween('paid_at', [$from, $to])->get(['amount', 'method']);

        // Órdenes entregadas en el rango: según la fecha en que pasaron a "Entregado".
        $deliveredIds = OrderStatusHistory::where('to_status', 'delivered')
            ->whereBetween('changed_at', [$from, $to])
            ->distinct()
            ->pluck('order_id');
        $delivered = Order::with('user')->whereIn('id', $deliveredIds)->where('status', 'delivered')->get();

        $received = Order::whereBetween('created_at', [$from, $to])->get(['id', 'device_type', 'total_amount']);

        $repairDays = $delivered->filter(fn ($o) => $o->entry_date && $o->delivery_date)
            ->map(fn ($o) => $o->entry_date->diffInDays($o->delivery_date));

        $commissions = Commission::whereIn('order_id', $delivered->pluck('id'))->get()->groupBy('user_id');

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'kpis' => [
                'revenue' => round((float) $payments->sum('amount'), 2),
                'received' => $received->count(),
                'delivered' => $delivered->count(),
                'avg_ticket' => $delivered->count() ? round((float) $delivered->avg('total_amount'), 2) : null,
                'avg_days' => $repairDays->count() ? round($repairDays->avg(), 1) : null,
            ],
            'byMethod' => collect(PaymentMethods::withHistorical($payments->pluck('method')->unique()))->map(fn ($label, $key) => [
                'label' => $label,
                'amount' => round((float) $payments->where('method', $key)->sum('amount'), 2),
            ])->values(),
            'monthly' => $this->monthlyRevenue(),
            'byTechnician' => $delivered->groupBy('user_id')->map(fn ($orders, $userId) => [
                'name' => $orders->first()->user?->name ?? 'Sin asignar',
                'orders' => $orders->count(),
                'total' => round((float) $orders->sum('total_amount'), 2),
                'commission' => round((float) ($commissions[$userId] ?? collect())->sum('commission_amount'), 2),
            ])->sortByDesc('total')->values(),
            'byDevice' => $received->groupBy('device_type')->map(fn ($orders, $type) => [
                'label' => Order::deviceTypes()[$type] ?? $type,
                'orders' => $orders->count(),
                'total' => round((float) $orders->sum('total_amount'), 2),
            ])->sortByDesc('orders')->values(),
            'topParts' => OrderPart::with('sparePart')
                ->whereIn('order_id', Order::whereBetween('created_at', [$from, $to])->select('id'))
                ->get()
                ->groupBy('spare_part_id')
                ->map(fn ($rows) => [
                    'name' => $rows->first()->sparePart?->name ?? '—',
                    'quantity' => (int) $rows->sum('quantity'),
                    'total' => round((float) $rows->sum('subtotal'), 2),
                ])
                ->sortByDesc('quantity')
                ->take(10)
                ->values(),
        ]);
    }

    /**
     * Exporta órdenes o pagos del período a CSV (separador ";" y BOM UTF-8 para Excel en español).
     */
    public function export(Request $request, string $dataset)
    {
        abort_unless(in_array($dataset, ['orders', 'payments']), 404);
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);

        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : now()->endOfDay();

        [$header, $rows] = $dataset === 'orders' ? $this->orderRows($from, $to) : $this->paymentRows($from, $to);
        $filename = ($dataset === 'orders' ? 'ordenes' : 'pagos') . '-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');
            foreach ($rows as $row) {
                fputcsv($out, array_map([$this, 'safeCell'], $row), ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function orderRows(Carbon $from, Carbon $to): array
    {
        $statuses = Order::statuses();
        $orders = Order::with(['client', 'user', 'brand', 'deviceModel'])
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id')
            ->get();

        return [
            ['Orden', 'Fecha ingreso', 'Cliente', 'Cédula', 'Teléfono', 'Equipo', 'Marca', 'Modelo', 'Estado', 'Técnico',
                'Diagnóstico', 'Mano de obra', 'Repuestos', 'Recargo', 'Total', 'Pagado', 'Saldo', 'Fecha entrega'],
            $orders->map(fn ($o) => [
                $o->order_number, $o->entry_date?->format('d/m/Y'), $o->client?->name, $o->client?->id_document, $o->client?->phone,
                Order::deviceTypes()[$o->device_type] ?? $o->device_type, $o->brand?->name ?? $o->brand_text, $o->deviceModel?->name ?? $o->model_text,
                $statuses[$o->status] ?? $o->status, $o->user?->name,
                $this->decimal($o->diagnosis_cost), $this->decimal($o->labor_cost), $this->decimal($o->parts_cost), $this->decimal($o->surcharge_amount),
                $this->decimal($o->total_amount), $this->decimal($o->amount_paid), $this->decimal($o->balance_due), $o->delivery_date?->format('d/m/Y'),
            ]),
        ];
    }

    private function paymentRows(Carbon $from, Carbon $to): array
    {
        $payments = OrderPayment::with(['order.client', 'registeredBy'])
            ->whereBetween('paid_at', [$from, $to])
            ->orderBy('paid_at')
            ->get();

        return [
            ['Fecha', 'Hora', 'Orden', 'Cliente', 'Método', 'Referencia', 'Monto', 'Registrado por'],
            $payments->map(fn ($p) => [
                $p->paid_at->format('d/m/Y'), $p->paid_at->format('H:i'), $p->order?->order_number, $p->order?->client?->name,
                PaymentMethods::label($p->method), $p->reference, $this->decimal($p->amount), $p->registeredBy?->name,
            ]),
        ];
    }

    /**
     * Decimal con coma, como lo espera Excel en español.
     */
    private function decimal($value): string
    {
        return number_format((float) $value, 2, ',', '');
    }

    /**
     * Evita inyección de fórmulas: un texto que empieza con = + - @ se abriría como fórmula en Excel.
     */
    private function safeCell($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) && ! preg_match('/^-?\d+(,\d+)?$/', $value) ? "'" . $value : $value;
    }

    /**
     * Ingresos cobrados por mes en los últimos 12 meses (incluye meses sin ingresos).
     */
    private function monthlyRevenue()
    {
        $start = now()->startOfMonth()->subMonths(11);
        $totals = OrderPayment::where('paid_at', '>=', $start)->get(['amount', 'paid_at'])
            ->groupBy(fn ($p) => $p->paid_at->format('Y-m'))
            ->map(fn ($rows) => round((float) $rows->sum('amount'), 2));

        return collect(range(0, 11))->map(function ($i) use ($start, $totals) {
            $month = $start->copy()->addMonths($i);

            return [
                'key' => $month->format('Y-m'),
                'label' => $month->translatedFormat('M y'),
                'full' => $month->translatedFormat('F Y'),
                'amount' => $totals[$month->format('Y-m')] ?? 0,
            ];
        });
    }
}
