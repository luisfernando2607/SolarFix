<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CashClosing extends Model
{
    use Auditable;

    protected $fillable = [
        'branch_id', 'closing_date', 'opening_cash', 'cash_payments', 'other_income', 'expenses', 'withdrawals', 'expected_cash', 'expected_transfer', 'expected_card', 'expected_other',
        'counted_cash', 'difference', 'notes', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'opening_cash' => 'decimal:2',
            'cash_payments' => 'decimal:2',
            'other_income' => 'decimal:2',
            'expenses' => 'decimal:2',
            'withdrawals' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'expected_transfer' => 'decimal:2',
            'expected_card' => 'decimal:2',
            'expected_other' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    /**
     * Mensaje de error si se quiere mover dinero en un día anterior ya cerrado de la sucursal.
     * El día de hoy puede seguir recibiendo cobros: el cierre se vuelve a hacer.
     */
    public static function lockedDayError(?int $branchId, $date): ?string
    {
        $day = \Illuminate\Support\Carbon::parse($date)->startOfDay();
        if (! $day->lt(today())) {
            return null;
        }

        $closed = self::where('branch_id', $branchId ?? Branch::mainId())->whereDate('closing_date', $day)->exists();

        return $closed ? 'La caja del ' . $day->format('d/m/Y') . ' ya está cerrada; registra el movimiento con fecha de hoy.' : null;
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    public function auditDescription(): string
    {
        return 'Cierre de caja del ' . $this->closing_date?->format('d/m/Y');
    }
}
