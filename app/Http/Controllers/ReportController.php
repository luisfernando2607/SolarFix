<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderPart;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    private const METHODS = ['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'card' => 'Tarjeta', 'other' => 'Otro'];

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
            'byMethod' => collect(self::METHODS)->map(fn ($label, $key) => [
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
