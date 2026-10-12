<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Caché por petición: se consultan en cada página (menú, contexto, formularios). */
    private static array $cache = [];

    protected static function booted(): void
    {
        static::saved(fn () => self::flushCache());
        static::deleted(fn () => self::flushCache());
    }

    public static function flushCache(): void
    {
        self::$cache = [];
    }

    /**
     * Sucursal principal: la primera creada.
     */
    public static function mainId(): ?int
    {
        return self::$cache['main'] ??= self::orderBy('id')->value('id');
    }

    /**
     * Sucursales activas (id, nombre y código), ordenadas por nombre.
     */
    public static function activeList()
    {
        return self::$cache['active'] ??= self::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }

    /**
     * ¿Hay más de una sucursal? (para mostrar códigos y selectores solo cuando hacen falta).
     */
    public static function isMultiple(): bool
    {
        return self::$cache['multiple'] ??= self::count() > 1;
    }

    /**
     * ¿Existe la sucursal? (memorizado: el contexto lo pregunta muchas veces por página).
     */
    public static function existsId(int $id): bool
    {
        return self::$cache['exists'][$id] ??= self::whereKey($id)->exists();
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function auditDescription(): string
    {
        return 'Sucursal ' . $this->name;
    }
}
