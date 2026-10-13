<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Auditable;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'name',
        'email',
        'password',
        'must_change_password',
        'phone',
        'role',
        'commission_type',
        'commission_value',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'commission_value' => 'decimal:2',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && filled($this->two_factor_secret);
    }

    /**
     * Valida un código de la app (sin aceptar dos veces el mismo) o un código de respaldo (de un solo uso).
     */
    public function verifyTwoFactor(string $code): bool
    {
        if (! $this->hasTwoFactor()) {
            return false;
        }

        if ($step = \App\Support\Totp::verify($this->two_factor_secret, $code, $this->two_factor_last_step)) {
            $this->forceFill(['two_factor_last_step' => $step])->save();

            return true;
        }

        $codes = $this->two_factor_recovery_codes ?? [];
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        foreach ($codes as $i => $recovery) {
            if (hash_equals(str_replace('-', '', $recovery), $normalized)) {
                unset($codes[$i]);
                $this->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    public static function newRecoveryCodes(): array
    {
        return array_map(fn () => strtoupper(\Illuminate\Support\Str::random(5) . '-' . \Illuminate\Support\Str::random(5)), range(1, 8));
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTechnician(): bool
    {
        return $this->role === 'technician';
    }

    public function isReceptionist(): bool
    {
        return $this->role === 'receptionist';
    }

    public function auditDescription(): string
    {
        return 'Usuario ' . $this->email;
    }
}
