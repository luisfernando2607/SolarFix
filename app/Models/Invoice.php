<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'order_id',
        'client_id',
        'client_name',
        'client_document',
        'client_phone',
        'client_address',
        'client_email',
        'device_type',
        'device_brand',
        'device_model',
        'device_serial',
        'subtotal',
        'iva_percent',
        'iva_amount',
        'total',
        'status',
        'issued_at',
        'paid_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'iva_percent' => 'decimal:2',
            'iva_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'issued_at' => 'date',
            'paid_at' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function statuses(): array
    {
        return [
            'emitida' => 'Emitida',
            'pagada' => 'Pagada',
            'anulada' => 'Anulada',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments()
    {
        return $this->hasMany(\App\Models\OrderPayment::class, 'invoice_id');
    }

    private static function generateInvoiceNumber(): string
    {
        $prefix = 'FAC-';
        $last = self::withTrashed()->where('invoice_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->value('invoice_number');

        if ($last) {
            $num = (int) substr($last, strlen($prefix)) + 1;
        } else {
            $num = 1;
        }

        return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
    }

    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (!$invoice->invoice_number) {
                $invoice->invoice_number = self::generateInvoiceNumber();
            }
            if (!$invoice->created_by) {
                $invoice->created_by = auth()->id();
            }
            $invoice->total = round((float) $invoice->subtotal + (float) $invoice->iva_amount, 2);
        });
    }
}
