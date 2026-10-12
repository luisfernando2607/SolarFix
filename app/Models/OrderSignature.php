<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderSignature extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'stage', 'signature_path', 'signed_at', 'ip_address'];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    public static function stages(): array
    {
        return [
            'reception' => 'Recepción',
            'delivery' => 'Entrega',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
