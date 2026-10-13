<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Orden de compra a un proveedor. Al recibirla, cada ítem entra al stock de la sucursal
 * con su costo (actualiza el costo promedio) y queda el movimiento con el proveedor.
 */
class PurchaseOrder extends Model
{
    use Auditable;

    protected $fillable = ['branch_id', 'supplier_id', 'number', 'status', 'notes', 'created_by', 'sent_at', 'received_at', 'received_by'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public static function statuses(): array
    {
        return [
            'draft' => 'Borrador',
            'sent' => 'Enviada',
            'received' => 'Recibida',
            'cancelled' => 'Anulada',
        ];
    }

    /**
     * Número OC-COD-AAAAMMDD-001, reintentando si dos se crean a la vez.
     */
    public static function createWithUniqueNumber(array $attributes): self
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $code = Branch::whereKey($attributes['branch_id'])->value('code');
                $prefix = 'OC-' . ($code ? $code . '-' : '') . now()->format('Ymd') . '-';
                $last = self::where('number', 'like', $prefix . '%')->orderByDesc('id')->value('number');

                return self::create($attributes + ['number' => $prefix . str_pad((string) ($last ? (int) substr($last, strlen($prefix)) + 1 : 1), 3, '0', STR_PAD_LEFT)]);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 5) {
                    throw $e;
                }
            }
        }
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['draft', 'sent'], true);
    }

    public function total(): float
    {
        return round((float) $this->items->sum(fn ($i) => $i->quantity * (float) $i->unit_cost), 2);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by')->withTrashed();
    }

    public function auditDescription(): string
    {
        return 'Orden de compra ' . $this->number;
    }
}
