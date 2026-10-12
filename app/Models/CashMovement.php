<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CashMovement extends Model
{
    use Auditable;

    protected $fillable = ['branch_id', 'movement_date', 'type', 'amount', 'description', 'registered_by'];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public static function types(): array
    {
        return [
            'opening' => 'Fondo inicial',
            'income' => 'Otro ingreso',
            'expense' => 'Gasto',
            'withdrawal' => 'Retiro / depósito al banco',
        ];
    }

    /**
     * Efecto en el efectivo de la caja: suma o resta.
     */
    public function signedAmount(): float
    {
        return in_array($this->type, ['expense', 'withdrawal']) ? -(float) $this->amount : (float) $this->amount;
    }

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by')->withTrashed();
    }

    public function auditDescription(): string
    {
        return (self::types()[$this->type] ?? $this->type) . ' de ' . money($this->amount) . ': ' . $this->description;
    }
}
