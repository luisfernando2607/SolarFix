<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderPart;
use App\Models\OrderStatusHistory;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Utilidad estimada del período:
 *   órdenes entregadas (sin IVA) − costo de sus repuestos − comisiones
 * + ventas de mostrador − costo de lo vendido
 * − gastos registrados en caja.
 * Es una estimación de gestión (costo promedio al momento de usar cada repuesto), no contable.
 */
class ProfitReport
{
    public function forPeriod(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $delivered = Order::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'delivered')
            ->whereIn('id', OrderStatusHistory::select('order_id')->where('to_status', 'delivered')->whereBetween('changed_at', [$from, $to]));

        $ordersRevenue = (float) (clone $delivered)->sum(DB::raw('total_amount - tax_amount'));
        $partsCost = (float) OrderPart::whereIn('order_id', (clone $delivered)->select('id'))->sum(DB::raw('quantity * unit_cost'));
        $commissions = (float) Commission::whereIn('order_id', (clone $delivered)->select('id'))->sum('commission_amount');

        $sales = Sale::active()->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->whereBetween('sold_at', [$from, $to]);
        $salesRevenue = (float) (clone $sales)->sum('total');
        $salesCost = (float) SaleItem::whereIn('sale_id', (clone $sales)->select('id'))->sum(DB::raw('quantity * unit_cost'));

        $expenses = (float) CashMovement::where('type', 'expense')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            // Día completo (la fecha puede guardarse con hora 00:00 según el motor).
            ->where('movement_date', '>=', $from->toDateString())
            ->where('movement_date', '<', $to->copy()->addDay()->toDateString())
            ->sum('amount');

        $revenue = $ordersRevenue + $salesRevenue;
        $profit = $revenue - $partsCost - $commissions - $salesCost - $expenses;

        return [
            'orders_revenue' => round($ordersRevenue, 2),
            'parts_cost' => round($partsCost, 2),
            'commissions' => round($commissions, 2),
            'sales_revenue' => round($salesRevenue, 2),
            'sales_cost' => round($salesCost, 2),
            'expenses' => round($expenses, 2),
            'revenue' => round($revenue, 2),
            'profit' => round($profit, 2),
            'margin' => $revenue > 0 ? round($profit / $revenue * 100, 1) : null,
        ];
    }
}
