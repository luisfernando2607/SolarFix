<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'client_id', 'type', 'to', 'subject', 'status', 'error', 'sent_by', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by')->withTrashed();
    }
}
