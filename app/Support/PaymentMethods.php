<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Métodos de pago configurables. "cash" (efectivo) es fijo: el cuadre de caja depende de él.
 */
class PaymentMethods
{
    public const CASH = 'cash';

    public const DEFAULTS = [
        'cash' => 'Efectivo',
        'transfer' => 'Transferencia',
        'card' => 'Tarjeta',
        'other' => 'Otro',
    ];

    /**
     * Métodos activos [clave => nombre], con efectivo siempre primero.
     */
    public static function all(): array
    {
        $configured = json_decode((string) Setting::get('payment_methods', ''), true);
        $methods = is_array($configured) && $configured ? $configured : self::DEFAULTS;

        return [self::CASH => $methods[self::CASH] ?? self::DEFAULTS[self::CASH]] + $methods;
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * Nombre para mostrar; los pagos con un método ya eliminado conservan un nombre legible.
     */
    public static function label(?string $key): string
    {
        return self::all()[$key] ?? self::DEFAULTS[$key] ?? ucfirst((string) $key);
    }

    /**
     * Métodos activos más los que aparecen en pagos históricos (para totales y filtros).
     */
    public static function withHistorical(iterable $usedKeys): array
    {
        $methods = self::all();
        foreach ($usedKeys as $key) {
            $methods[$key] ??= self::label($key);
        }

        return $methods;
    }
}
