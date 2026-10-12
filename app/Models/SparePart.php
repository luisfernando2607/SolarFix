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

    // stock_quantity (total de todas las sucursales) no es asignable: solo cambia a través de
    // InventoryService, junto con el stock de la sucursal y su movimiento.

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

    /**
     * Stock en una sucursal; con null (todas las sucursales), el total.
     */
    public function stockIn(?int $branchId): int
    {
        if ($branchId === null) {
            return (int) $this->stock_quantity;
        }

        return (int) ($this->relationLoaded('stocks')
            ? $this->stocks->firstWhere('branch_id', $branchId)?->quantity
            : $this->stocks()->where('branch_id', $branchId)->value('quantity'));
    }

    /**
     * Stock bajo en la sucursal (o en alguna, con null). Solo cuenta donde el repuesto se maneja
     * (tiene registro de stock), para no alertar por repuestos que una sucursal nunca tuvo.
     */
    public function isLowStock(?int $branchId = null): bool
    {
        $stocks = $this->relationLoaded('stocks') ? $this->stocks : $this->stocks()->get();

        return $stocks->when($branchId !== null, fn ($s) => $s->where('branch_id', $branchId))
            ->contains(fn ($stock) => $stock->quantity <= $this->min_stock);
    }

    public function scopeLowStock($query, ?int $branchId = null)
    {
        return $query->where('is_active', true)->whereHas('stocks', fn ($q) => $q
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->whereColumn('spare_part_stocks.quantity', '<=', 'spare_parts.min_stock'));
    }

    /**
     * Fija el stock de una sucursal sin registrar movimiento (cargas iniciales y pruebas).
     * Para cambios del día a día usar InventoryService.
     */
    public function setBranchStock(int $branchId, int $quantity): void
    {
        $this->stocks()->updateOrCreate(['branch_id' => $branchId], ['quantity' => $quantity]);
        $this->stock_quantity = (int) $this->stocks()->sum('quantity');
        $this->save();
    }

    public function stocks()
    {
        return $this->hasMany(SparePartStock::class);
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
