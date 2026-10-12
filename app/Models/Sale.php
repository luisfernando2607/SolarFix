<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\PaymentMethods;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Venta de mostrador (sin orden de servicio): sale del stock de la sucursal y entra a su caja.
 */
class Sale extends Model
{
    use Auditable;

    protected $fillable = [
        'branch_id', 'sale_number', 'client_id', 'customer_name', 'total', 'method', 'reference', 'notes',
        'sold_by', 'sold_at', 'cancelled_at', 'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'sold_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Crea la venta con número único por sucursal y día: VTA-COD-AAAAMMDD-0001.
     */
    public static function createWithUniqueNumber(array $attributes): self
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return self::create($attributes + ['sale_number' => self::nextNumber($attributes['branch_id'])]);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 5) {
                    throw $e;
                }
            }
        }
    }

    private static function nextNumber(int $branchId): string
    {
        $code = Branch::whereKey($branchId)->value('code');
        $prefix = 'VTA-' . ($code ? $code . '-' : '') . now()->format('Ymd') . '-';
        $last = self::where('sale_number', 'like', $prefix . '%')->orderByDesc('id')->value('sale_number');

        return $prefix . str_pad((string) ($last ? (int) substr($last, strlen($prefix)) + 1 : 1), 4, '0', STR_PAD_LEFT);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function scopeActive($query)
    {
        return $query->whereNull('cancelled_at');
    }

    public function getMethodLabelAttribute(): string
    {
        return PaymentMethods::label($this->method);
    }

    public function customerLabel(): string
    {
        return $this->client?->name ?? $this->customer_name ?? 'Cliente ocasional';
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function soldBy()
    {
        return $this->belongsTo(User::class, 'sold_by')->withTrashed();
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    public function auditDescription(): string
    {
        return 'Venta ' . $this->sale_number . ' por ' . money($this->total);
    }
}
