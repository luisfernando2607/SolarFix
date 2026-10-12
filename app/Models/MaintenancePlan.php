<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class MaintenancePlan extends Model
{
    use Auditable;

    public const DUE_SOON_DAYS = 15;

    protected $fillable = ['client_id', 'title', 'address', 'interval_months', 'next_due_date', 'last_done_at', 'is_active', 'notes'];

    protected function casts(): array
    {
        return [
            'interval_months' => 'integer',
            'next_due_date' => 'date',
            'last_done_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public static function dueStatuses(): array
    {
        return [
            'overdue' => 'Vencido',
            'due_soon' => 'Por vencer',
            'ok' => 'Al día',
        ];
    }

    public function dueStatus(): string
    {
        if ($this->next_due_date->lt(today())) {
            return 'overdue';
        }

        return $this->next_due_date->lte(today()->addDays(self::DUE_SOON_DAYS)) ? 'due_soon' : 'ok';
    }

    public function scopeDue($query)
    {
        return $query->where('is_active', true)->whereDate('next_due_date', '<=', today()->addDays(self::DUE_SOON_DAYS));
    }

    /**
     * Registra un mantenimiento hecho y programa el siguiente según el intervalo.
     */
    public function registerService(Carbon $doneOn): void
    {
        $this->update([
            'last_done_at' => $doneOn->toDateString(),
            'next_due_date' => $doneOn->copy()->addMonthsNoOverflow($this->interval_months)->toDateString(),
        ]);
    }

    /**
     * Cita abierta ya agendada para este plan (para no agendar dos veces).
     */
    public function openAppointment(): ?Appointment
    {
        return $this->appointments()->whereIn('status', ['pending', 'confirmed'])->orderBy('scheduled_at')->first();
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function auditDescription(): string
    {
        return 'Plan de mantenimiento: ' . $this->title;
    }
}
