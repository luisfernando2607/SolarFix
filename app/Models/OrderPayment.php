<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\PaymentMethods;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
     * Registra un cobro (o una devolución, con monto negativo) de la orden: bloquea la orden para
     * que dos cobros simultáneos no superen el saldo, lo asocia a su factura vigente, recalcula
     * el saldo y marca la factura como pagada cuando la orden queda saldada.
     *
     * @throws DomainException si el monto supera el saldo (o lo devuelto, el saldo a favor)
     *                         o si el día de la caja ya está cerrado.
     */
    public static function register(Order $order, float $amount, string $method, ?int $userId, ?string $reference = null, ?string $notes = null, $paidAt = null): self
    {
        $paidAt = $paidAt ? Carbon::parse($paidAt) : now();
        if ($error = CashClosing::lockedDayError($order->branch_id, $paidAt)) {
            throw new DomainException($error);
        }

        return DB::transaction(function () use ($order, $amount, $method, $userId, $reference, $notes, $paidAt) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            if ($amount > 0 && $amount > (float) $locked->balance_due + 0.004) {
                throw new DomainException('El pago (' . money($amount) . ') supera el saldo pendiente (' . money($locked->balance_due) . ').');
            }
            if ($amount < 0 && -$amount > $locked->creditDue() + 0.004) {
                throw new DomainException('La devolución (' . money(-$amount) . ') supera el saldo a favor del cliente (' . money($locked->creditDue()) . ').');
            }

            $invoice = $locked->activeInvoice();
            $payment = self::create([
                'order_id' => $locked->id,
                'invoice_id' => $invoice?->id,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'notes' => $notes,
                'paid_at' => $paidAt,
                'registered_by' => $userId,
            ]);

            $locked->recalculateTotals();
            $invoice?->syncPaidStatus($locked);
            $order->setRawAttributes($locked->getAttributes(), true);

            return $payment;
        });
    }

    public function isRefund(): bool
    {
        return (float) $this->amount < 0;
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
