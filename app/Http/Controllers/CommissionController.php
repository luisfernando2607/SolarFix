<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Commission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'period' => ['nullable', 'date_format:Y-m'],
            'user_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:pending,paid'],
        ]);

        $user = Auth::user();
        $canManage = $user->can('manage-commissions');
        $period = $request->input('period', now()->format('Y-m'));
        // Un técnico solo ve sus propias comisiones.
        $userId = $canManage ? $request->input('user_id') : $user->id;

        $query = Commission::with(['user', 'order.client'])
            ->where('period', $period)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($request->status === 'pending', fn ($q) => $q->where('is_paid', false))
            ->when($request->status === 'paid', fn ($q) => $q->where('is_paid', true));

        $commissions = (clone $query)->orderBy('user_id')->orderBy('id')->get();

        $summary = $commissions->groupBy('user_id')->map(fn ($rows) => [
            'user' => $rows->first()->user,
            'orders' => $rows->count(),
            'pending' => round((float) $rows->where('is_paid', false)->sum('commission_amount'), 2),
            'paid' => round((float) $rows->where('is_paid', true)->sum('commission_amount'), 2),
        ]);

        return view('commissions.index', [
            'commissions' => $commissions,
            'summary' => $summary,
            'period' => $period,
            'canManage' => $canManage,
            'technicians' => $canManage
                ? User::withTrashed()->whereIn('id', Commission::select('user_id'))->orWhere('role', 'technician')->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    /**
     * Marca como pagadas las comisiones pendientes de un técnico en un mes.
     */
    public function pay(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'period' => ['required', 'date_format:Y-m'],
        ]);

        $count = DB::transaction(function () use ($validated) {
            $pending = Commission::where('user_id', $validated['user_id'])
                ->where('period', $validated['period'])
                ->where('is_paid', false)
                ->lockForUpdate()
                ->get();

            foreach ($pending as $commission) {
                $commission->update(['is_paid' => true, 'paid_at' => now()]);
            }

            return $pending->count();
        });

        $name = User::withTrashed()->find($validated['user_id'])->name;

        return redirect()->route('commissions.index', ['period' => $validated['period']])
            ->with('success', $count ? "{$count} comisiones de {$name} marcadas como pagadas." : 'No había comisiones pendientes.');
    }
}
