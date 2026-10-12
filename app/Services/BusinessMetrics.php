<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use App\Models\SparePartStock;
use Illuminate\Support\Carbon;

/**
 * Indicadores del negocio para un período (por defecto, el mes) y su comparación
 * con el período anterior.
 */
class BusinessMetrics
{
    public function forMonth(Carbon $month, ?int $branchId = null): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $previousFrom = $from->copy()->subMonthNoOverflow()->startOfMonth();
        $previousTo = $previousFrom->copy()->endOfMonth();

        return [
            'current' => $this->period($from, $to),
            'previous' => $this->period($previousFrom, $previousTo),
            'receivables' => $this->receivables(),
            'inventory_value' => round((float) SparePartStock::query()
                ->join('spare_parts', 'spare_parts.id', '=', 'spare_part_stocks.spare_part_id')
                ->where('spare_parts.is_active', true)
                ->where('spare_part_stocks.quantity', '>', 0)
                ->when($branchId, fn ($q) => $q->where('spare_part_stocks.branch_id', $branchId))
                ->get(['spare_part_stocks.quantity', 'spare_parts.cost_price'])
                ->sum(fn ($row) => $row->quantity * (float) $row->cost_price), 2),
        ];
    }

    private function period(Carbon $from, Carbon $to): array
    {
        $deliveredIds = $this->ordersReaching('delivered', $from, $to);
        $delivered = Order::whereIn('id', $deliveredIds)->get(['id', 'total_amount', 'entry_date', 'delivery_date']);
        $repairDays = $delivered->filter(fn ($o) => $o->entry_date && $o->delivery_date)
            ->map(fn ($o) => $o->entry_date->diffInDays($o->delivery_date));

        // Presupuestos resueltos en el período: aprobados (pasan a reparación) o rechazados (cerrados).
        $decisions = OrderStatusHistory::where('from_status', 'waiting_approval')
            ->whereIn('to_status', ['repairing', 'closed_no_repair'])
            ->whereBetween('changed_at', [$from, $to])
            ->get(['to_status']);
        $approved = $decisions->where('to_status', 'repairing')->count();

        $warrantyClaims = Order::whereNotNull('parent_order_id')->whereBetween('created_at', [$from, $to])->count();

        return [
            'revenue' => round((float) OrderPayment::whereBetween('paid_at', [$from, $to])->sum('amount'), 2),
            'received' => Order::whereBetween('created_at', [$from, $to])->count(),
            'delivered' => $delivered->count(),
            'avg_ticket' => $delivered->count() ? round((float) $delivered->avg('total_amount'), 2) : null,
            'avg_days' => $repairDays->count() ? round($repairDays->avg(), 1) : null,
            'approval_rate' => $decisions->count() ? round($approved / $decisions->count() * 100) : null,
            'approved' => $approved,
            'rejected' => $decisions->count() - $approved,
            'rework_rate' => $delivered->count() ? round($warrantyClaims / $delivered->count() * 100) : null,
            'warranty_claims' => $warrantyClaims,
        ];
    }

    /**
     * Órdenes que pasaron a un estado dentro del período.
     */
    private function ordersReaching(string $status, Carbon $from, Carbon $to)
    {
        return OrderStatusHistory::where('to_status', $status)
            ->whereBetween('changed_at', [$from, $to])
            ->distinct()
            ->pluck('order_id');
    }

    /**
     * Cuentas por cobrar: equipos ya entregados que quedaron con saldo.
     */
    private function receivables(): array
    {
        $orders = Order::with('client')
            ->where('status', 'delivered')
            ->where('balance_due', '>', 0)
            ->orderBy('delivery_date')
            ->get();

        return [
            'total' => round((float) $orders->sum('balance_due'), 2),
            'count' => $orders->count(),
            'orders' => $orders->take(10),
        ];
    }
}
