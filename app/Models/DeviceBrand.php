<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceBrand extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'device_types',
        'device_type',
        'logo_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'device_types' => 'array',
            'is_active' => 'boolean',
        ];
    }

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

    public function models()
    {
        return $this->hasMany(DeviceModel::class, 'brand_id');
    }
}
