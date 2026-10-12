<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderPart;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use App\Models\Sale;
use App\Support\BranchContext;
use App\Support\PaymentMethods;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Totales por método sumados en la base (no se cargan los pagos uno por uno).
        $byMethod = BranchContext::applyThroughOrder(OrderPayment::whereBetween('paid_at', [$from, $to]))
            ->selectRaw('method, sum(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method');
        // Ventas de mostrador vigentes del período (también son ingresos cobrados).
        $salesByMethod = BranchContext::apply(Sale::active()->whereBetween('sold_at', [$from, $to]))
            ->selectRaw('method, sum(total) as total')
            ->groupBy('method')
            ->pluck('total', 'method');
        $salesTotal = round((float) $salesByMethod->sum(), 2);
        foreach ($salesByMethod as $method => $amount) {
            $byMethod[$method] = (float) ($byMethod[$method] ?? 0) + (float) $amount;
        }

        // Órdenes entregadas en el rango: según la fecha en que pasaron a "Entregado".
        $deliveredIds = OrderStatusHistory::select('order_id')
            ->where('to_status', 'delivered')
            ->whereBetween('changed_at', [$from, $to]);
        $delivered = BranchContext::apply(Order::with('user:id,name'))->whereIn('id', $deliveredIds)->where('status', 'delivered')
            ->get(['id', 'user_id', 'total_amount', 'entry_date', 'delivery_date']);

        $received = BranchContext::apply(Order::whereBetween('created_at', [$from, $to]))
            ->selectRaw('device_type, count(*) as orders, sum(total_amount) as total')
            ->groupBy('device_type')
            ->get();

        $repairDays = $delivered->filter(fn ($o) => $o->entry_date && $o->delivery_date)
            ->map(fn ($o) => $o->entry_date->diffInDays($o->delivery_date));

        $commissions = Commission::whereIn('order_id', $delivered->pluck('id'))->get()->groupBy('user_id');

        // Satisfacción: calificaciones recibidas en el período (por la sucursal de la orden).
        $reviews = BranchContext::applyThroughOrder(\App\Models\OrderReview::query())->whereBetween('created_at', [$from, $to]);
        $satisfaction = [
            'count' => (clone $reviews)->count(),
            'average' => ($avg = (clone $reviews)->avg('rating')) !== null ? round((float) $avg, 1) : null,
            'latest' => (clone $reviews)->with('order:id,order_number')->whereNotNull('comment')->latest('created_at')->take(5)->get(),
        ];

        return view('reports.index', [
            'satisfaction' => $satisfaction,
            'branchName' => ($branch = BranchContext::current()) ? Branch::find($branch)?->name : null,
            'from' => $from,
            'to' => $to,
            'kpis' => [
                'revenue' => round((float) $byMethod->sum(), 2),
                'sales' => $salesTotal,
                'received' => (int) $received->sum('orders'),
                'delivered' => $delivered->count(),
                'avg_ticket' => $delivered->count() ? round((float) $delivered->avg('total_amount'), 2) : null,
                'avg_days' => $repairDays->count() ? round($repairDays->avg(), 1) : null,
            ],
            'byMethod' => collect(PaymentMethods::withHistorical($byMethod->keys()))->map(fn ($label, $key) => [
                'label' => $label,
                'amount' => round((float) ($byMethod[$key] ?? 0), 2),
            ])->values(),
            'monthly' => $this->monthlyRevenue(),
            'byTechnician' => $delivered->groupBy('user_id')->map(fn ($orders, $userId) => [
                'name' => $orders->first()->user?->name ?? 'Sin asignar',
                'orders' => $orders->count(),
                'total' => round((float) $orders->sum('total_amount'), 2),
                'commission' => round((float) ($commissions[$userId] ?? collect())->sum('commission_amount'), 2),
            ])->sortByDesc('total')->values(),
            'byDevice' => $received->map(fn ($row) => [
                'label' => Order::deviceTypes()[$row->device_type] ?? $row->device_type,
                'orders' => (int) $row->orders,
                'total' => round((float) $row->total, 2),
            ])->sortByDesc('orders')->values(),
            'topParts' => OrderPart::with('sparePart:id,name')
                ->whereIn('order_id', BranchContext::apply(Order::whereBetween('created_at', [$from, $to]))->select('id'))
                ->selectRaw('spare_part_id, sum(quantity) as quantity, sum(subtotal) as total')
                ->groupBy('spare_part_id')
                ->orderByDesc('quantity')
                ->take(10)
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->sparePart?->name ?? '—',
                    'quantity' => (int) $row->quantity,
                    'total' => round((float) $row->total, 2),
                ]),
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
        // lazy(): se escribe por bloques, sin cargar todo el período en memoria.
        $orders = BranchContext::apply(Order::with(['client', 'user', 'brand', 'deviceModel', 'branch']))
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id')
            ->lazy(500);

        return [
            ['Orden', 'Sucursal', 'Fecha ingreso', 'Cliente', 'Cédula', 'Teléfono', 'Equipo', 'Marca', 'Modelo', 'Estado', 'Técnico',
                'Diagnóstico', 'Mano de obra', 'Repuestos', 'Recargo', 'Total', 'Pagado', 'Saldo', 'Fecha entrega'],
            $orders->map(fn ($o) => [
                $o->order_number, $o->branch?->name, $o->entry_date?->format('d/m/Y'), $o->client?->name, $o->client?->id_document, $o->client?->phone,
                Order::deviceTypes()[$o->device_type] ?? $o->device_type, $o->brand?->name ?? $o->brand_text, $o->deviceModel?->name ?? $o->model_text,
                $statuses[$o->status] ?? $o->status, $o->user?->name,
                $this->decimal($o->diagnosis_cost), $this->decimal($o->labor_cost), $this->decimal($o->parts_cost), $this->decimal($o->surcharge_amount),
                $this->decimal($o->total_amount), $this->decimal($o->amount_paid), $this->decimal($o->balance_due), $o->delivery_date?->format('d/m/Y'),
            ]),
        ];
    }

    private function paymentRows(Carbon $from, Carbon $to): array
    {
        $payments = BranchContext::applyThroughOrder(OrderPayment::with(['order.client', 'order.branch', 'registeredBy']))
            ->whereBetween('paid_at', [$from, $to])
            ->orderBy('paid_at')
            ->orderBy('id')
            ->lazy(500);

        return [
            ['Fecha', 'Hora', 'Sucursal', 'Orden', 'Cliente', 'Método', 'Referencia', 'Monto', 'Registrado por'],
            $payments->map(fn ($p) => [
                $p->paid_at->format('d/m/Y'), $p->paid_at->format('H:i'), $p->order?->branch?->name, $p->order?->order_number, $p->order?->client?->name,
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
        $month = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', paid_at)" : "DATE_FORMAT(paid_at, '%Y-%m')";
        $totals = BranchContext::applyThroughOrder(OrderPayment::where('paid_at', '>=', $start))
            ->selectRaw("{$month} as month, sum(amount) as total")
            ->groupByRaw($month)
            ->pluck('total', 'month');
        $saleMonth = str_replace('paid_at', 'sold_at', $month);
        $sales = BranchContext::apply(Sale::active()->where('sold_at', '>=', $start))
            ->selectRaw("{$saleMonth} as month, sum(total) as total")
            ->groupByRaw($saleMonth)
            ->pluck('total', 'month');
        $totals = $totals->keys()->merge($sales->keys())->unique()
            ->mapWithKeys(fn ($key) => [$key => round((float) ($totals[$key] ?? 0) + (float) ($sales[$key] ?? 0), 2)]);

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
