<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
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
