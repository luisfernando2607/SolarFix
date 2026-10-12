<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'id_document',
        'client_type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function maintenancePlans()
    {
        return $this->hasMany(MaintenancePlan::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function auditDescription(): string
    {
        return 'Cliente ' . $this->name;
    }
}
