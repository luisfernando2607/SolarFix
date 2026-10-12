<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderPart;
use App\Models\SparePart;
use App\Models\SparePartMovement;
use App\Models\SparePartStock;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Único punto que modifica el stock. Cada cambio:
 * - se hace en transacción, bloqueando primero el repuesto y luego el stock de la sucursal
 *   (siempre en ese orden, para que dos operaciones simultáneas no se bloqueen entre sí);
 * - actualiza el stock de la sucursal y el total del repuesto (spare_parts.stock_quantity);
 * - queda en spare_part_movements con su sucursal.
 */
class InventoryService
{
    /**
     * Entrada por compra en una sucursal: suma stock y recalcula el costo promedio ponderado
     * (y el precio de venta si se indica). El movimiento guarda el costo real de esta compra.
     */
    public function receive(SparePart $part, int $quantity, float $unitCost, ?float $salePrice, ?string $reason, ?int $userId, ?int $supplierId = null, ?int $branchId = null): SparePartMovement
    {
        $branchId ??= Branch::mainId();

        return DB::transaction(function () use ($part, $quantity, $unitCost, $salePrice, $reason, $userId, $supplierId, $branchId) {
            [$locked, $stock] = $this->lock($part, $branchId);

            $currentStock = max(0, $locked->stock_quantity);
            $locked->cost_price = $currentStock > 0
                ? round(($currentStock * (float) $locked->cost_price + $quantity * $unitCost) / ($currentStock + $quantity), 2)
                : $unitCost;
            if ($salePrice !== null) {
                $locked->sale_price = $salePrice;
            }
            $this->change($locked, $stock, $quantity);

            return $this->movement($locked, $branchId, 'in', $quantity, $reason ?: 'Compra', null, $unitCost, $userId, $supplierId);
        });
    }

    /**
     * Ajuste por conteo físico en una sucursal: deja su stock en la cantidad contada.
     */
    public function adjust(SparePart $part, int $countedQuantity, string $reason, ?int $userId, ?int $branchId = null): ?SparePartMovement
    {
        $branchId ??= Branch::mainId();

        return DB::transaction(function () use ($part, $countedQuantity, $reason, $userId, $branchId) {
            [$locked, $stock] = $this->lock($part, $branchId);
            $delta = $countedQuantity - $stock->quantity;
            if ($delta === 0) {
                return null;
            }

            $this->change($locked, $stock, $delta);

            return $this->movement($locked, $branchId, 'adjustment', $delta, $reason, null, null, $userId);
        });
    }

    /**
     * Traslado entre sucursales: sale del origen y entra al destino; el total no cambia.
     */
    public function transfer(SparePart $part, int $fromBranchId, int $toBranchId, int $quantity, ?int $userId, ?string $note = null): void
    {
        if ($fromBranchId === $toBranchId) {
            throw new DomainException('El origen y el destino deben ser sucursales distintas.');
        }

        DB::transaction(function () use ($part, $fromBranchId, $toBranchId, $quantity, $userId, $note) {
            $locked = SparePart::lockForUpdate()->findOrFail($part->id);
            // Filas de stock en orden fijo (por sucursal) para evitar bloqueos mutuos.
            $stocks = collect([$fromBranchId, $toBranchId])->sort()->mapWithKeys(fn ($branch) => [$branch => $this->stockRow($locked, $branch)]);
            $from = $stocks[$fromBranchId];
            $to = $stocks[$toBranchId];

            if ($from->quantity < $quantity) {
                throw new DomainException("Stock insuficiente de {$locked->name} en el origen: hay {$from->quantity}.");
            }

            $from->decrement('quantity', $quantity);
            $to->increment('quantity', $quantity);

            $names = Branch::whereIn('id', [$fromBranchId, $toBranchId])->pluck('name', 'id');
            $suffix = $note ? " ({$note})" : '';
            $this->movement($locked, $fromBranchId, 'out', $quantity, 'Traslado a ' . $names[$toBranchId] . $suffix, null, (float) $locked->cost_price, $userId);
            $this->movement($locked, $toBranchId, 'in', $quantity, 'Traslado desde ' . $names[$fromBranchId] . $suffix, null, (float) $locked->cost_price, $userId);
        });
    }

    /**
     * Agrega un repuesto a la orden al precio de venta (a $0 si la garantía cubre repuestos)
     * y lo descuenta del stock de la sucursal de la orden.
     */
    public function addToOrder(Order $order, SparePart $part, int $quantity, ?int $userId): OrderPart
    {
        $branchId = $order->branch_id ?? Branch::mainId();

        return DB::transaction(function () use ($order, $part, $quantity, $userId, $branchId) {
            [$locked, $stock] = $this->lock($part, $branchId);

            if (! $locked->is_active) {
                throw new DomainException('El repuesto está desactivado.');
            }
            if ($stock->quantity < $quantity) {
                throw new DomainException("Stock insuficiente de {$locked->name} en esta sucursal: hay {$stock->quantity}.");
            }

            $this->change($locked, $stock, -$quantity);

            $unitPrice = $order->warrantyCoversParts() ? 0 : (float) $locked->sale_price;

            $orderPart = OrderPart::create([
                'order_id' => $order->id,
                'spare_part_id' => $locked->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => round($unitPrice * $quantity, 2),
            ]);

            $this->movement($locked, $branchId, 'out', $quantity, 'Usado en orden ' . $order->order_number, $order->id, (float) $locked->cost_price, $userId);
            $order->recalculateTotals();

            return $orderPart;
        });
    }

    /**
     * Devuelve al stock todos los repuestos de una orden que no se reparó (cerrada sin
     * reparación o anulada): estaban reservados para el presupuesto.
     */
    public function releaseOrderParts(Order $order, string $reason, ?int $userId): int
    {
        $parts = $order->parts()->get();
        foreach ($parts as $orderPart) {
            $this->removeFromOrder($orderPart, $userId, $reason);
        }

        return $parts->count();
    }

    /**
     * Quita el repuesto de la orden y devuelve las unidades al stock de su sucursal.
     */
    public function removeFromOrder(OrderPart $orderPart, ?int $userId, ?string $reason = null): void
    {
        DB::transaction(function () use ($orderPart, $userId, $reason) {
            $order = $orderPart->order;
            $branchId = $order->branch_id ?? Branch::mainId();
            [$locked, $stock] = $this->lock($orderPart->sparePart()->firstOrFail(), $branchId);

            $this->change($locked, $stock, $orderPart->quantity);
            $this->movement($locked, $branchId, 'in', $orderPart->quantity, ($reason ?? 'Devuelto de orden') . ' ' . $order->order_number, $order->id, null, $userId);

            $orderPart->delete();
            if (! $order->parts()->exists()) {
                $order->parts_cost = 0;
            }
            $order->recalculateTotals();
        });
    }

    /**
     * Bloquea el repuesto y luego su stock en la sucursal (lo crea si no existía).
     */
    private function lock(SparePart $part, int $branchId): array
    {
        $locked = SparePart::lockForUpdate()->findOrFail($part->id);

        return [$locked, $this->stockRow($locked, $branchId)];
    }

    private function stockRow(SparePart $part, int $branchId): SparePartStock
    {
        SparePartStock::firstOrCreate(['spare_part_id' => $part->id, 'branch_id' => $branchId], ['quantity' => 0]);

        return SparePartStock::where('spare_part_id', $part->id)->where('branch_id', $branchId)->lockForUpdate()->firstOrFail();
    }

    /**
     * Suma (o resta) unidades al stock de la sucursal y al total del repuesto.
     */
    private function change(SparePart $part, SparePartStock $stock, int $delta): void
    {
        $stock->quantity += $delta;
        $stock->save();
        $part->stock_quantity += $delta;
        $part->save();
    }

    private function movement(SparePart $part, int $branchId, string $type, int $quantity, ?string $reason, ?int $orderId, ?float $unitCost, ?int $userId, ?int $supplierId = null): SparePartMovement
    {
        return SparePartMovement::create([
            'spare_part_id' => $part->id,
            'branch_id' => $branchId,
            'type' => $type,
            'quantity' => $quantity,
            'reason' => $reason,
            'order_id' => $orderId,
            'supplier_id' => $supplierId,
            'unit_cost' => $unitCost,
            'registered_by' => $userId,
            'moved_at' => now(),
        ]);
    }
}
