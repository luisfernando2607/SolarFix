<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SparePartMovement extends Model
{
    public $timestamps = false;

    protected $fillable = ['spare_part_id', 'branch_id', 'type', 'quantity', 'reason', 'order_id', 'supplier_id', 'unit_cost', 'registered_by', 'moved_at'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'moved_at' => 'datetime',
        ];
    }

    public static function types(): array
    {
        return [
            'in' => 'Entrada',
            'out' => 'Salida',
            'adjustment' => 'Ajuste',
        ];
    }

    public function sparePart()
    {
        return $this->belongsTo(SparePart::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by')->withTrashed();
    }
}
