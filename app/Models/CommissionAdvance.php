<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CommissionAdvance extends Model
{
    use Auditable;

    protected $fillable = ['user_id', 'amount', 'period', 'notes', 'is_settled', 'settled_at', 'registered_by'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_settled' => 'boolean',
            'settled_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function auditDescription(): string
    {
        return 'Adelanto de comisión de $' . number_format((float) $this->amount, 2) . ' (' . $this->period . ')';
    }
}
