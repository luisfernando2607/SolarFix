<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use Auditable;

    protected $fillable = ['name', 'ruc', 'phone', 'email', 'contact_name', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function purchases()
    {
        return $this->hasMany(SparePartMovement::class)->where('type', 'in')->latest('moved_at')->latest('id');
    }

    public function auditDescription(): string
    {
        return 'Proveedor ' . $this->name;
    }
}
