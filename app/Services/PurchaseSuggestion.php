<?php

namespace App\Services;

use App\Models\SparePart;
use App\Models\SparePartMovement;
use App\Models\Supplier;
use Illuminate\Support\Collection;

/**
 * Qué comprar: repuestos por debajo del stock mínimo más un mes de consumo, según lo usado
 * en los últimos 90 días (en órdenes y ventas; los traslados no cuentan).
 * Cantidad sugerida = stock mínimo + consumo de un mes − stock actual.
 */
class PurchaseSuggestion
{
    public const DAYS = 90;

    public function for(?int $branchId): Collection
    {
        $since = now()->subDays(self::DAYS);
        $branch = fn ($q) => $q->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        // Consumo neto: salidas a órdenes/ventas menos lo devuelto (orden anulada, venta anulada).
        $consumed = SparePartMovement::query()->tap($branch)
            ->where('moved_at', '>=', $since)
            ->where(fn ($q) => $q->whereNotNull('order_id')->orWhereNotNull('sale_id'))
            ->selectRaw("spare_part_id, sum(case when type = 'out' then quantity else -quantity end) as qty")
            ->groupBy('spare_part_id')
            ->pluck('qty', 'spare_part_id');

        // Último proveedor y costo de compra de cada repuesto.
        $lastPurchases = SparePartMovement::whereIn('id', SparePartMovement::selectRaw('max(id)')
                ->where('type', 'in')->whereNotNull('supplier_id')->groupBy('spare_part_id'))
            ->get(['spare_part_id', 'supplier_id', 'unit_cost'])
            ->keyBy('spare_part_id');
        $suppliers = Supplier::whereIn('id', $lastPurchases->pluck('supplier_id'))->pluck('name', 'id');

        return SparePart::with('stocks')->where('is_active', true)
            ->where(fn ($q) => $q->whereIn('id', $consumed->filter(fn ($qty) => $qty > 0)->keys())->orWhere(fn ($q) => $q->lowStock($branchId)))
            ->orderBy('name')
            ->get()
            ->map(function (SparePart $part) use ($branchId, $consumed, $lastPurchases, $suppliers) {
                $stock = $part->stockIn($branchId);
                $monthly = max(0, (int) ($consumed[$part->id] ?? 0)) / (self::DAYS / 30);
                $target = (int) $part->min_stock + (int) ceil($monthly);
                // Se repone hasta el nivel objetivo (mínimo + un mes de consumo).
                $quantity = max(0, $target - $stock);
                $purchase = $lastPurchases[$part->id] ?? null;
                $unitCost = (float) ($purchase?->unit_cost ?? $part->cost_price);

                return [
                    'part' => $part,
                    'stock' => $stock,
                    'min_stock' => (int) $part->min_stock,
                    'monthly' => round($monthly, 1),
                    'days_left' => $monthly > 0 ? (int) floor($stock / ($monthly / 30)) : null,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'total' => round($quantity * $unitCost, 2),
                    'supplier' => $purchase ? ($suppliers[$purchase->supplier_id] ?? 'Sin proveedor') : 'Sin proveedor',
                    'supplier_id' => $purchase && isset($suppliers[$purchase->supplier_id]) ? (int) $purchase->supplier_id : null,
                ];
            })
            ->filter(fn ($row) => $row['quantity'] > 0)
            ->sortBy([['supplier', 'asc'], ['days_left', 'asc']])
            ->values();
    }
}
