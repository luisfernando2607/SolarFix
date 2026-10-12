<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    use Auditable;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'order_id', 'order_total', 'commission_type', 'commission_value',
        'commission_amount', 'is_paid', 'paid_at', 'period',
    ];

    protected function casts(): array
    {
        return [
            'order_total' => 'decimal:2',
            'commission_value' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'is_paid' => 'boolean',
            'paid_at' => 'datetime',
        ];
    }

    public static function bases(): array
    {
        return [
            'service' => 'Solo servicio (diagnóstico + mano de obra)',
            'total' => 'Total de la orden (incluye repuestos y recargo)',
        ];
    }

    /**
     * Crea, actualiza o elimina la comisión de la orden según su estado actual.
     * Las comisiones ya pagadas no se tocan.
     */
    public static function syncFor(Order $order): ?self
    {
        $commission = self::where('order_id', $order->id)->first();
        if ($commission?->is_paid) {
            return $commission;
        }

        $technician = $order->user_id ? User::withTrashed()->find($order->user_id) : null;

        // Una comisión pendiente conserva la tarifa vigente al entregar, aunque luego cambie la del técnico.
        $keepRate = $commission && $technician && $commission->user_id === $technician->id;
        $type = $keepRate ? $commission->commission_type : $technician?->commission_type;
        $value = (float) ($keepRate ? $commission->commission_value : $technician?->commission_value);

        $applies = $order->status === 'delivered'
            && ! $order->trashed()
            && ! $order->isWarrantyOrder()
            && $type
            && $value > 0;

        $base = Setting::get('commission_base', 'service') === 'total'
            ? (float) $order->total_amount
            : (float) $order->diagnosis_cost + (float) $order->labor_cost;

        $amount = $applies
            ? ($type === 'fixed' ? $value : round($base * $value / 100, 2))
            : 0;

        if ($amount <= 0) {
            $commission?->delete();

            return null;
        }

        $attributes = [
            'user_id' => $technician->id,
            'order_total' => round($base, 2),
            'commission_type' => $type,
            'commission_value' => $value,
            'commission_amount' => $amount,
            'period' => ($order->delivery_date ?? today())->format('Y-m'),
        ];

        if ($commission) {
            $commission->update($attributes);

            return $commission;
        }

        return self::create($attributes + ['order_id' => $order->id]);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function order()
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function auditDescription(): string
    {
        return 'Comisión de $' . number_format((float) $this->commission_amount, 2)
            . ' · orden ' . Order::withTrashed()->whereKey($this->order_id)->value('order_number');
    }
}
