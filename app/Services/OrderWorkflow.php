<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Setting;

/**
 * Reglas al cambiar el estado de una orden, compartidas por el formulario de edición
 * y las acciones rápidas del técnico.
 */
class OrderWorkflow
{
    /**
     * Siguientes pasos que se ofrecen como botones según el estado actual.
     * La entrega no está aquí: requiere cobro y firma.
     */
    public const NEXT_ACTIONS = [
        'received' => ['diagnosing' => 'Iniciar diagnóstico'],
        'diagnosing' => ['waiting_approval' => 'Enviar a aprobación', 'repairing' => 'Aprobado: iniciar reparación', 'closed_no_repair' => 'Cerrar sin reparación'],
        'waiting_approval' => ['repairing' => 'Cliente aprobó: iniciar reparación', 'closed_no_repair' => 'Cliente rechazó: cerrar sin reparación'],
        'repairing' => ['ready' => 'Marcar lista', 'waiting_approval' => 'Daño adicional: pedir aprobación'],
        'ready' => ['repairing' => 'Volver a reparación'],
        'warranty' => ['diagnosing' => 'Iniciar diagnóstico', 'repairing' => 'Iniciar reparación'],
    ];

    public function __construct(private InventoryService $inventory)
    {
    }

    public function nextActions(Order $order): array
    {
        return self::NEXT_ACTIONS[$order->status] ?? [];
    }

    /**
     * Mensaje de error si la configuración exige el control de calidad para pasar a "Listo".
     */
    public function qualityCheckError(Order $order, ?string $newStatus): ?string
    {
        if ($newStatus !== 'ready' || $order->status === 'ready' || Setting::get('require_quality_check') !== '1') {
            return null;
        }

        $qualityCheck = $order->checklistProgress('delivery');

        return $qualityCheck && ! $qualityCheck['complete']
            ? 'Completa el checklist de control de calidad antes de marcar la orden como lista.'
            : null;
    }

    /**
     * Cambio de estado desde una acción rápida.
     */
    public function transition(Order $order, string $newStatus, ?int $userId, ?string $note = null): void
    {
        $oldStatus = $order->status;
        $order->update(['status' => $newStatus]);
        $this->afterUpdate($order, $oldStatus, $userId, $note);
    }

    /**
     * Efectos de un cambio de estado o de costos: historial, repuestos, garantía y comisión.
     */
    public function afterUpdate(Order $order, string $oldStatus, ?int $userId, ?string $note = null): void
    {
        if ($order->status !== $oldStatus) {
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $oldStatus,
                'to_status' => $order->status,
                'changed_by' => $userId,
                'notes' => $note ?: null,
            ]);
        }

        // Sin reparación: los repuestos reservados para el presupuesto vuelven al stock.
        if ($order->status === 'closed_no_repair' && $oldStatus !== 'closed_no_repair') {
            $this->inventory->releaseOrderParts($order, 'Devuelto: cerrada sin reparación la orden', $userId);
        }

        // Al entregar se emite la garantía; si ya existe, se ajustan sus fechas.
        if ($order->status === 'delivered') {
            if (! $order->delivery_date) {
                $order->update(['delivery_date' => today()]);
            }
            $order->issueWarranty();
        }

        // Crea, ajusta o elimina la comisión pendiente según el estado y los costos actuales.
        Commission::syncFor($order);
    }
}
