<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warranty extends Model
{
    protected $fillable = ['order_id', 'warranty_type', 'start_date', 'end_date', 'status', 'conditions'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public static function types(): array
    {
        return [
            'full' => 'Servicio completo',
            'labor' => 'Solo mano de obra',
            'parts' => 'Solo repuestos',
        ];
    }

    public static function statuses(): array
    {
        return [
            'active' => 'Vigente',
            'expiring' => 'Por vencer',
            'expired' => 'Vencida',
            'claimed' => 'Reclamada',
        ];
    }

    public static function alertDays(): int
    {
        return (int) Setting::get('warranty_alert_days', 15);
    }

    /**
     * Estado según la fecha de hoy; en la base solo se guarda active/claimed.
     */
    public function currentStatus(): string
    {
        if ($this->end_date->lt(today())) {
            return 'expired';
        }
        if ($this->status === 'claimed') {
            return 'claimed';
        }

        return $this->end_date->lte(today()->addDays(self::alertDays())) ? 'expiring' : 'active';
    }

    public function isValid(): bool
    {
        return $this->end_date->gte(today());
    }

    public function scopeFilterStatus($query, string $status)
    {
        $alertLimit = today()->addDays(self::alertDays());

        return match ($status) {
            'expired' => $query->whereDate('end_date', '<', today()),
            'claimed' => $query->where('status', 'claimed')->whereDate('end_date', '>=', today()),
            'expiring' => $query->where('status', '!=', 'claimed')->whereDate('end_date', '>=', today())->whereDate('end_date', '<=', $alertLimit),
            'active' => $query->where('status', '!=', 'claimed')->whereDate('end_date', '>', $alertLimit),
            default => $query,
        };
    }

    public function order()
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }
}
