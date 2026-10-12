<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['checklist_id', 'label', 'sort_order', 'is_required', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function checklist()
    {
        return $this->belongsTo(Checklist::class);
    }
}
