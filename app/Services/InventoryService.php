<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderPart;
use App\Models\SparePart;
use App\Models\SparePartMovement;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Único punto que modifica el stock: cada cambio queda en spare_part_movements y se hace
 * con la fila del repuesto bloqueada, para que dos usuarios no descuenten la misma unidad.
 */
class InventoryService
{
    /**
     * Entrada por compra: suma stock y recalcula el costo promedio ponderado
     * (y el precio de venta si se indica). El movimiento guarda el costo real de esta compra.
     */
    public function receive(SparePart $part, int $quantity, float $unitCost, ?float $salePrice, ?string $reason, ?int $userId): SparePartMovement
    {
        return DB::transaction(function () use ($part, $quantity, $unitCost, $salePrice, $reason, $userId) {
            $locked = SparePart::lockForUpdate()->findOrFail($part->id);
            $currentStock = max(0, $locked->stock_quantity);
            $locked->cost_price = $currentStock > 0
                ? round(($currentStock * (float) $locked->cost_price + $quantity * $unitCost) / ($currentStock + $quantity), 2)
                : $unitCost;
            $locked->stock_quantity += $quantity;
            if ($salePrice !== null) {
                $locked->sale_price = $salePrice;
            }
            $locked->save();

            return $this->movement($locked, 'in', $quantity, $reason ?: 'Compra', null, $unitCost, $userId);
        });
    }

    /**
     * Ajuste por conteo físico: deja el stock en la cantidad contada.
     */
    public function adjust(SparePart $part, int $countedQuantity, string $reason, ?int $userId): ?SparePartMovement
    {
        return DB::transaction(function () use ($part, $countedQuantity, $reason, $userId) {
            $locked = SparePart::lockForUpdate()->findOrFail($part->id);
            $delta = $countedQuantity - $locked->stock_quantity;
            if ($delta === 0) {
                return null;
            }

            $locked->stock_quantity = $countedQuantity;
            $locked->save();

            return $this->movement($locked, 'adjustment', $delta, $reason, null, null, $userId);
        });
    }

    /**
     * Agrega un repuesto a la orden al precio de venta (a $0 en órdenes de garantía) y descuenta el stock.
     */
    public function addToOrder(Order $order, SparePart $part, int $quantity, ?int $userId): OrderPart
    {
        return DB::transaction(function () use ($order, $part, $quantity, $userId) {
            $locked = SparePart::lockForUpdate()->findOrFail($part->id);

            if (! $locked->is_active) {
                throw new DomainException('El repuesto está desactivado.');
            }
            if ($locked->stock_quantity < $quantity) {
                throw new DomainException("Stock insuficiente de {$locked->name}: hay {$locked->stock_quantity}.");
            }

            $locked->stock_quantity -= $quantity;
            $locked->save();

            $unitPrice = $order->isWarrantyOrder() ? 0 : (float) $locked->sale_price;

            $orderPart = OrderPart::create([
                'order_id' => $order->id,
                'spare_part_id' => $locked->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => round($unitPrice * $quantity, 2),
            ]);

            $this->movement($locked, 'out', $quantity, 'Usado en orden ' . $order->order_number, $order->id, (float) $locked->cost_price, $userId);
            $order->recalculateTotals();

            return $orderPart;
        });
    }

    /**
     * Quita el repuesto de la orden y devuelve las unidades al stock.
     */
    public function removeFromOrder(OrderPart $orderPart, ?int $userId): void
    {
        DB::transaction(function () use ($orderPart, $userId) {
            $locked = SparePart::lockForUpdate()->findOrFail($orderPart->spare_part_id);
            $locked->stock_quantity += $orderPart->quantity;
            $locked->save();

            $order = $orderPart->order;
            $this->movement($locked, 'in', $orderPart->quantity, 'Devuelto de orden ' . $order->order_number, $order->id, null, $userId);

            $orderPart->delete();
            if (! $order->parts()->exists()) {
                $order->parts_cost = 0;
            }
            $order->recalculateTotals();
        });
    }

    private function movement(SparePart $part, string $type, int $quantity, ?string $reason, ?int $orderId, ?float $unitCost, ?int $userId): SparePartMovement
    {
        return SparePartMovement::create([
            'spare_part_id' => $part->id,
            'type' => $type,
            'quantity' => $quantity,
            'reason' => $reason,
            'order_id' => $orderId,
            'unit_cost' => $unitCost,
            'registered_by' => $userId,
            'moved_at' => now(),
        ]);
    }
}
