<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'client_id', 'trigger_event', 'phone_to', 'message_body', 'sent_by', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by')->withTrashed();
    }
}
