<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceModel extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'brand_id',
        'name',
        'device_type',
        'is_active',
    ];

    public static function deviceTypes(): array
    {
        return [
            'celular' => 'Celular',
            'tablet' => 'Tablet',
            'pc' => 'PC / Laptop',
            'aire_split' => 'Aire Split',
            'aire_central' => 'Aire Central',
            'televisor' => 'Televisor',
            'lavador' => 'Lavador',
            'otro' => 'Otro',
        ];
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function brand()
    {
        return $this->belongsTo(DeviceBrand::class, 'brand_id');
    }
}
