<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\PaymentMethods;
use Illuminate\Database\Eloquent\Model;

class OrderPayment extends Model
{
    use Auditable;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'invoice_id',
        'amount',
        'method',
        'reference',
        'notes',
        'paid_at',
        'registered_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Registra un cobro de la orden: lo asocia a su factura vigente y recalcula el saldo.
     */
    public static function register(Order $order, float $amount, string $method, ?int $userId, ?string $reference = null, ?string $notes = null, $paidAt = null): self
    {
        $payment = self::create([
            'order_id' => $order->id,
            'invoice_id' => $order->invoices()->where('status', '!=', 'anulada')->value('id'),
            'amount' => $amount,
            'method' => $method,
            'reference' => $reference,
            'notes' => $notes,
            'paid_at' => $paidAt ?? now(),
            'registered_by' => $userId,
        ]);

        $order->recalculateTotals();

        return $payment;
    }

    public function getMethodLabelAttribute(): string
    {
        return PaymentMethods::label($this->method);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function auditDescription(): string
    {
        return 'Pago de ' . money($this->amount) . ($this->order_id ? ' · orden ' . Order::withTrashed()->whereKey($this->order_id)->value('order_number') : '');
    }
}
