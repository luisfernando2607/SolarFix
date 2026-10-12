<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderReview extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'rating', 'comment', 'created_at'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }
}
