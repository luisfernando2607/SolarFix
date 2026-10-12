<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SparePartStock extends Model
{
    protected $fillable = ['spare_part_id', 'branch_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function sparePart()
    {
        return $this->belongsTo(SparePart::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
