<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPhoto extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'stage',
        'file_path',
        'caption',
        'taken_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function takenBy()
    {
        return $this->belongsTo(User::class, 'taken_by');
    }
}
