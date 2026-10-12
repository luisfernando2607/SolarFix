<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use Auditable;

    protected $fillable = [
        'branch_id', 'client_id', 'user_id', 'order_id', 'type', 'title', 'address',
        'scheduled_at', 'duration_min', 'status', 'notes', 'reminder_sent',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'duration_min' => 'integer',
            'reminder_sent' => 'boolean',
        ];
    }

    public static function types(): array
    {
        return [
            'home_visit' => 'Visita técnica',
            'installation' => 'Instalación',
            'maintenance' => 'Mantenimiento',
            'followup' => 'Seguimiento / segunda visita',
            'reception' => 'Recepción en taller',
        ];
    }

    public static function statuses(): array
    {
        return [
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmada',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
        ];
    }

    public function endsAt()
    {
        return $this->scheduled_at->copy()->addMinutes($this->duration_min);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    /**
     * Citas del técnico que se superponen con el horario indicado (sin contar canceladas).
     */
    public static function overlapping(int $userId, $start, int $durationMin, ?int $exceptId = null)
    {
        $end = $start->copy()->addMinutes($durationMin);

        // Se filtra en PHP: el cálculo de la hora de fin con minutos varía entre MySQL y SQLite.
        return self::where('user_id', $userId)
            ->where('status', '!=', 'cancelled')
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->whereBetween('scheduled_at', [$start->copy()->subDay(), $end])
            ->get()
            ->filter(fn ($a) => $a->scheduled_at->lt($end) && $a->endsAt()->gt($start));
    }

    public function scopeVisibleTo($query, User $user)
    {
        return $user->isTechnician() ? $query->where('user_id', $user->id) : $query;
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function order()
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function auditDescription(): string
    {
        return 'Cita ' . $this->title . ' · ' . $this->scheduled_at?->format('d/m/Y H:i');
    }
}
