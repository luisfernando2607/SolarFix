<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checklist extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['device_type', 'stage', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function stages(): array
    {
        return [
            'reception' => 'Recepción',
            'diagnosis' => 'Diagnóstico',
            'delivery' => 'Control de calidad',
        ];
    }

    public static function results(): array
    {
        return [
            'ok' => 'OK',
            'damaged' => 'Con daño',
            'not_applicable' => 'No aplica',
        ];
    }

    public static function for(string $deviceType, string $stage): ?self
    {
        return self::where('device_type', $deviceType)->where('stage', $stage)->where('is_active', true)->first();
    }

    public function items()
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeItems()
    {
        return $this->items()->where('is_active', true);
    }
}
