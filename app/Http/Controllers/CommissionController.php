<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Commission;
use App\Models\CommissionAdvance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $advances = CommissionAdvance::with('user')
            ->where('period', $period)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('id')
            ->get();

        // Un resumen por técnico con comisiones o adelantos en el mes.
        $summary = $commissions->pluck('user', 'user_id')->union($advances->pluck('user', 'user_id'))
            ->map(function ($technician, $id) use ($commissions, $advances) {
                $rows = $commissions->where('user_id', $id);
                $pending = round((float) $rows->where('is_paid', false)->sum('commission_amount'), 2);
                $openAdvances = round((float) $advances->where('user_id', $id)->where('is_settled', false)->sum('amount'), 2);

                return [
                    'user' => $technician,
                    'orders' => $rows->count(),
                    'pending' => $pending,
                    'paid' => round((float) $rows->where('is_paid', true)->sum('commission_amount'), 2),
                    'advances' => $openAdvances,
                    'to_pay' => round(max(0, $pending - $openAdvances), 2),
                ];
            });

        return view('commissions.index', [
            'commissions' => $commissions,
            'summary' => $summary,
            'advances' => $advances,
            'period' => $period,
            'canManage' => $canManage,
            'technicians' => $canManage
                ? User::withTrashed()->whereIn('id', Commission::select('user_id'))->orWhere('role', 'technician')->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    /**
     * Paga las comisiones pendientes de un técnico en un mes, descontando sus adelantos.
     * Si los adelantos superan lo ganado, el excedente pasa como adelanto al mes siguiente.
     */
    public function pay(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'period' => ['required', 'date_format:Y-m'],
        ]);

        $result = DB::transaction(function () use ($validated) {
            $pending = Commission::where('user_id', $validated['user_id'])
                ->where('period', $validated['period'])
                ->where('is_paid', false)
                ->lockForUpdate()
                ->get();
            $advances = CommissionAdvance::where('user_id', $validated['user_id'])
                ->where('period', $validated['period'])
                ->where('is_settled', false)
                ->lockForUpdate()
                ->get();

            if ($pending->isEmpty()) {
                return null;
            }

            foreach ($pending as $commission) {
                $commission->update(['is_paid' => true, 'paid_at' => now()]);
            }
            foreach ($advances as $advance) {
                $advance->update(['is_settled' => true, 'settled_at' => now()]);
            }

            $earned = round((float) $pending->sum('commission_amount'), 2);
            $advanced = round((float) $advances->sum('amount'), 2);

            if ($advanced > $earned) {
                CommissionAdvance::create([
                    'user_id' => $validated['user_id'],
                    'amount' => round($advanced - $earned, 2),
                    'period' => Carbon::createFromFormat('!Y-m', $validated['period'])->addMonthNoOverflow()->format('Y-m'),
                    'notes' => 'Saldo de adelantos de ' . $validated['period'],
                    'registered_by' => Auth::id(),
                ]);
            }

            return ['count' => $pending->count(), 'earned' => $earned, 'advanced' => $advanced, 'paid' => round(max(0, $earned - $advanced), 2)];
        });

        $name = User::withTrashed()->find($validated['user_id'])->name;
        $message = $result
            ? "Pago a {$name}: " . money($result['paid']) . " ({$result['count']} comisiones por " . money($result['earned'])
                . ($result['advanced'] > 0 ? ' menos adelantos por ' . money($result['advanced']) : '') . ').'
            : 'No había comisiones pendientes.';

        return redirect()->route('commissions.index', ['period' => $validated['period']])->with('success', $message);
    }

    public function storeAdvance(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'period' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        CommissionAdvance::create($validated + ['registered_by' => Auth::id()]);

        return redirect()->route('commissions.index', ['period' => $validated['period']])
            ->with('success', 'Adelanto de ' . money($validated['amount']) . ' registrado.');
    }
}
