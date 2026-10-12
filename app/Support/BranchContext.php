<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Qué sucursal está viendo el usuario.
 * - Recepción y técnicos: siempre su propia sucursal.
 * - Super admin y admin: la que eligieron en el selector, o todas (null).
 */
class BranchContext
{
    public const SESSION_KEY = 'branch_context';

    public static function seesAllBranches(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user && ($user->isSuperAdmin() || $user->isAdmin());
    }

    /**
     * Sucursal activa; null = todas (solo administradores).
     */
    public static function current(?User $user = null): ?int
    {
        $user ??= Auth::user();
        if (! $user) {
            return null;
        }

        if (! self::seesAllBranches($user)) {
            return $user->branch_id ?? Branch::mainId();
        }

        $selected = session(self::SESSION_KEY);

        return $selected && Branch::whereKey($selected)->exists() ? (int) $selected : null;
    }

    /**
     * Sucursal para un registro nuevo: la activa; si se ven todas, la del usuario o la principal.
     */
    public static function forCreate(?User $user = null): int
    {
        $user ??= Auth::user();

        return self::current($user) ?? $user?->branch_id ?? Branch::mainId();
    }

    /**
     * Filtra una consulta por la sucursal activa (no filtra si se ven todas).
     */
    public static function apply($query, string $column = 'branch_id')
    {
        $branch = self::current();

        return $branch ? $query->where($column, $branch) : $query;
    }

    /**
     * Filtra por la sucursal activa a través de la orden (pagos, garantías, comisiones).
     */
    public static function applyThroughOrder($query, string $relation = 'order')
    {
        $branch = self::current();

        return $branch ? $query->whereHas($relation, fn ($q) => $q->withTrashed()->where('branch_id', $branch)) : $query;
    }

    /**
     * ¿Puede el usuario ver un registro de esta sucursal? (los administradores, siempre).
     */
    public static function allows(?int $branchId, ?User $user = null): bool
    {
        $user ??= Auth::user();

        return self::seesAllBranches($user) || $branchId === null || (int) $branchId === (int) ($user->branch_id ?? Branch::mainId());
    }
}
