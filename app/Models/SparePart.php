<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class SparePart extends Model
{
    use Auditable;

    protected $fillable = [
        'branch_id', 'code', 'name', 'category', 'compatible_brand', 'compatible_model',
        'cost_price', 'sale_price', 'min_stock', 'location', 'is_active',
    ];

    // stock_quantity no es asignable: solo cambia a través de InventoryService (con su movimiento).

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->min_stock;
    }

    public function scopeLowStock($query)
    {
        return $query->where('is_active', true)->whereColumn('stock_quantity', '<=', 'min_stock');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $search = '%' . $term . '%';

        return $query->where(fn ($q) => $q->where('name', 'like', $search)
            ->orWhere('code', 'like', $search)
            ->orWhere('category', 'like', $search)
            ->orWhere('compatible_brand', 'like', $search)
            ->orWhere('compatible_model', 'like', $search));
    }

    public function movements()
    {
        return $this->hasMany(SparePartMovement::class)->latest('moved_at')->latest('id');
    }

    public function orderParts()
    {
        return $this->hasMany(OrderPart::class);
    }

    public function auditDescription(): string
    {
        return 'Repuesto ' . $this->name . ($this->code ? ' (' . $this->code . ')' : '');
    }
}
