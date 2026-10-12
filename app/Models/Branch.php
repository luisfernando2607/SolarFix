<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Sucursal principal: la primera creada.
     */
    public static function mainId(): ?int
    {
        return self::orderBy('id')->value('id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function auditDescription(): string
    {
        return 'Sucursal ' . $this->name;
    }
}
