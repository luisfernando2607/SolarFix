<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'client_id', 'trigger_event', 'channel', 'direction', 'status', 'wamid', 'error', 'status_at',
        'phone_to', 'message_body', 'sent_by', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'status_at' => 'datetime'];
    }

    public const STATUS_LABELS = [
        'sent' => 'Enviado',
        'delivered' => 'Entregado',
        'read' => 'Leído',
        'failed' => 'Falló',
    ];

    public function isInbound(): bool
    {
        return $this->direction === 'in';
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by')->withTrashed();
    }
}
