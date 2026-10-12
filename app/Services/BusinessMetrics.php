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
    private ?int $branchId = null;

    /**
     * Órdenes de la sucursal (todas, si no hay una elegida).
     */
    private function orders()
    {
        return Order::query()->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId));
    }

    private function throughOrder($query)
    {
        return $query->when($this->branchId, fn ($q) => $q->whereHas('order', fn ($o) => $o->withTrashed()->where('branch_id', $this->branchId)));
    }

    public function forMonth(Carbon $month, ?int $branchId = null): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $previousFrom = $from->copy()->subMonthNoOverflow()->startOfMonth();
        $previousTo = $previousFrom->copy()->endOfMonth();

        $this->branchId = $branchId;

        return [
            'current' => $this->period($from, $to),
            'previous' => $this->period($previousFrom, $previousTo),
            'receivables' => $this->receivables(),
            'inventory_value' => round((float) SparePartStock::query()
                ->join('spare_parts', 'spare_parts.id', '=', 'spare_part_stocks.spare_part_id')
                ->where('spare_parts.is_active', true)
                ->where('spare_part_stocks.quantity', '>', 0)
                ->when($branchId, fn ($q) => $q->where('spare_part_stocks.branch_id', $branchId))
                ->sum(\Illuminate\Support\Facades\DB::raw('spare_part_stocks.quantity * spare_parts.cost_price')), 2),
        ];
    }

    private function period(Carbon $from, Carbon $to): array
    {
        $delivered = $this->orders()->whereIn('id', $this->ordersReaching('delivered', $from, $to))
            ->get(['id', 'total_amount', 'entry_date', 'delivery_date']);
        $repairDays = $delivered->filter(fn ($o) => $o->entry_date && $o->delivery_date)
            ->map(fn ($o) => $o->entry_date->diffInDays($o->delivery_date));

        // Presupuestos resueltos en el período: aprobados (pasan a reparación) o rechazados (cerrados).
        $decisions = $this->throughOrder(OrderStatusHistory::query())->where('from_status', 'waiting_approval')
            ->whereIn('to_status', ['repairing', 'closed_no_repair'])
            ->whereBetween('changed_at', [$from, $to])
            ->selectRaw('to_status, count(*) as total')
            ->groupBy('to_status')
            ->pluck('total', 'to_status');
        $approved = (int) ($decisions['repairing'] ?? 0);
        $decisionCount = (int) $decisions->sum();

        $warrantyClaims = $this->orders()->whereNotNull('parent_order_id')->whereBetween('created_at', [$from, $to])->count();

        return [
            'revenue' => round((float) $this->throughOrder(OrderPayment::query())->whereBetween('paid_at', [$from, $to])->sum('amount')
                + (float) \App\Models\Sale::active()->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
                    ->whereBetween('sold_at', [$from, $to])->sum('total'), 2),
            'received' => $this->orders()->whereBetween('created_at', [$from, $to])->count(),
            'delivered' => $delivered->count(),
            'avg_ticket' => $delivered->count() ? round((float) $delivered->avg('total_amount'), 2) : null,
            'avg_days' => $repairDays->count() ? round($repairDays->avg(), 1) : null,
            'approval_rate' => $decisionCount ? round($approved / $decisionCount * 100) : null,
            'approved' => $approved,
            'rejected' => $decisionCount - $approved,
            'rework_rate' => $delivered->count() ? round($warrantyClaims / $delivered->count() * 100) : null,
            'warranty_claims' => $warrantyClaims,
        ];
    }

    /**
     * Órdenes que pasaron a un estado dentro del período.
     */
    private function ordersReaching(string $status, Carbon $from, Carbon $to)
    {
        // Subconsulta (no una lista de IDs en memoria): crece bien con muchas órdenes.
        return OrderStatusHistory::select('order_id')
            ->where('to_status', $status)
            ->whereBetween('changed_at', [$from, $to]);
    }

    /**
     * Cuentas por cobrar: equipos ya entregados que quedaron con saldo.
     */
    private function receivables(): array
    {
        $pending = fn () => $this->orders()->where('status', 'delivered')->where('balance_due', '>', 0);

        return [
            'total' => round((float) $pending()->sum('balance_due'), 2),
            'count' => $pending()->count(),
            'orders' => $pending()->with('client')->orderBy('delivery_date')->take(10)->get(),
        ];
    }
}
