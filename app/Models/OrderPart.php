<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class OrderPart extends Model
{
    use Auditable;

    public $timestamps = false;

    protected $fillable = ['order_id', 'spare_part_id', 'quantity', 'unit_price', 'subtotal'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function sparePart()
    {
        return $this->belongsTo(SparePart::class);
    }

    public function auditDescription(): string
    {
        return 'Repuesto ' . ($this->sparePart?->name ?? '#' . $this->spare_part_id) . ' x' . $this->quantity;
    }
}
