<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use Auditable;

    public $timestamps = false;

    protected $fillable = ['branch_id', 'key', 'value', 'group'];

    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::$cache ??= self::whereNull('branch_id')->pluck('value', 'key')->all();

        $value = self::$cache[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public static function put(string $key, mixed $value, string $group = 'general'): void
    {
        self::updateOrCreate(
            ['branch_id' => null, 'key' => $key],
            ['value' => $value, 'group' => $group],
        );
        self::$cache = null;
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }

    public function auditDescription(): string
    {
        return 'Configuración: ' . $this->key;
    }
}
