<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderChecklistResult extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'checklist_item_id', 'result', 'notes'];

    public function item()
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }
}
