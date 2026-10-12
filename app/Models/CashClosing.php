<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashClosing extends Model
{
    protected $fillable = [
        'closing_date', 'expected_cash', 'expected_transfer', 'expected_card', 'expected_other',
        'counted_cash', 'difference', 'notes', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'expected_cash' => 'decimal:2',
            'expected_transfer' => 'decimal:2',
            'expected_card' => 'decimal:2',
            'expected_other' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }
}
