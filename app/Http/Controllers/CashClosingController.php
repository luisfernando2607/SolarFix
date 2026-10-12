<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashClosing;
use App\Models\CashMovement;
use App\Models\OrderPayment;
use App\Support\BranchContext;
use App\Support\PaymentMethods;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CashClosingController extends Controller
{
    public function index(Request $request)
    {
        $date = $this->date($request);
        $branch = $this->branch($request);
        $payments = $this->paymentsOn($date, $branch);

        $totals = $this->totalsByMethod($payments);

        return view('cash.index', [
            'date' => $date,
            'payments' => $payments,
            'totals' => $totals,
            'movements' => CashMovement::with('registeredBy')->where('branch_id', $branch)->whereDate('movement_date', $date)->orderBy('id')->get(),
            'cash' => $this->cashSummary($date, $totals['cash'], $branch),
            'closing' => CashClosing::with('closedBy')->where('branch_id', $branch)->whereDate('closing_date', $date)->first(),
            'history' => CashClosing::with('closedBy')->where('branch_id', $branch)->orderByDesc('closing_date')->take(10)->get(),
            'branch' => Branch::find($branch),
            'branches' => BranchContext::seesAllBranches() ? Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']) : collect(),
            'methods' => PaymentMethods::withHistorical($payments->pluck('method')->unique()),
        ]);
    }

    public function store(Request $request)
    {
        $date = $this->date($request);
        $branch = $this->branch($request);
        $totals = $this->totalsByMethod($this->paymentsOn($date, $branch));
        $cash = $this->cashSummary($date, $totals['cash'], $branch);

        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $difference = round((float) $validated['counted_cash'] - $cash['expected'], 2);

        if ($difference != 0 && blank($validated['notes'] ?? null)) {
            return back()->withInput()->withErrors([
                'notes' => 'Hay una diferencia de ' . money($difference) . '. Escribe la justificación.',
            ]);
        }

        $closing = CashClosing::where('branch_id', $branch)->whereDate('closing_date', $date)->first()
            ?? new CashClosing(['closing_date' => $date, 'branch_id' => $branch]);

        $closing->fill([
            'opening_cash' => $cash['opening'],
            'cash_payments' => $cash['payments'],
            'other_income' => $cash['income'],
            'expenses' => $cash['expenses'],
            'withdrawals' => $cash['withdrawals'],
            'expected_cash' => $cash['expected'],
            // Las columnas fijas guardan transferencia y tarjeta; el resto de métodos (no efectivo) va en "otros".
            'expected_transfer' => $totals['transfer'] ?? 0,
            'expected_card' => $totals['card'] ?? 0,
            'expected_other' => round($totals['total'] - $totals['cash'] - ($totals['transfer'] ?? 0) - ($totals['card'] ?? 0), 2),
            'counted_cash' => $validated['counted_cash'],
            'difference' => $difference,
            'notes' => $validated['notes'] ?? null,
            'closed_by' => Auth::id(),
        ])->save();

        return redirect()->route('cash.index', ['date' => $date->toDateString(), 'branch' => $branch])
            ->with('success', 'Cierre de caja del ' . $date->format('d/m/Y') . ' registrado.');
    }

    /**
     * Fondo inicial, gastos, retiros u otros ingresos del día.
     */
    public function storeMovement(Request $request)
    {
        $date = $this->date($request);
        $branch = $this->branch($request);
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(CashMovement::types()))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        if ($error = CashClosing::lockedDayError($branch, $date)) {
            return back()->withInput()->with('error', $error);
        }

        CashMovement::create($validated + ['movement_date' => $date, 'branch_id' => $branch, 'registered_by' => Auth::id()]);

        return redirect()->route('cash.index', ['date' => $date->toDateString(), 'branch' => $branch])
            ->with('success', CashMovement::types()[$validated['type']] . ' registrado.');
    }

    public function destroyMovement(CashMovement $movement)
    {
        abort_unless(Auth::user()->isAdmin() || Auth::user()->isSuperAdmin(), 403, 'Solo un administrador puede eliminar movimientos de caja.');

        $date = $movement->movement_date->toDateString();
        if ($error = CashClosing::lockedDayError($movement->branch_id, $date)) {
            return back()->with('error', $error);
        }
        $movement->delete();

        return redirect()->route('cash.index', ['date' => $date, 'branch' => $movement->branch_id])->with('success', 'Movimiento eliminado.');
    }

    /**
     * Efectivo esperado en caja = fondo inicial + cobros en efectivo + otros ingresos − gastos − retiros.
     */
    private function cashSummary(Carbon $date, float $cashPayments, int $branch): array
    {
        $movements = CashMovement::where('branch_id', $branch)->whereDate('movement_date', $date)->get();
        $sum = fn ($type) => round((float) $movements->where('type', $type)->sum('amount'), 2);

        $summary = [
            'opening' => $sum('opening'),
            'payments' => round($cashPayments, 2),
            'income' => $sum('income'),
            'expenses' => $sum('expense'),
            'withdrawals' => $sum('withdrawal'),
        ];
        $summary['expected'] = round($summary['opening'] + $summary['payments'] + $summary['income'] - $summary['expenses'] - $summary['withdrawals'], 2);

        return $summary;
    }

    private function date(Request $request): Carbon
    {
        $request->validate(['date' => ['nullable', 'date', 'before_or_equal:today']]);

        return $request->filled('date') ? Carbon::parse($request->date)->startOfDay() : today();
    }

    /**
     * Caja de la sucursal: la propia; los administradores pueden elegir otra (?branch=).
     */
    private function branch(Request $request): int
    {
        if (BranchContext::seesAllBranches() && $request->filled('branch') && Branch::whereKey($request->branch)->exists()) {
            return (int) $request->branch;
        }

        return BranchContext::forCreate();
    }

    /**
     * Cobros del día de las órdenes de la sucursal.
     */
    private function paymentsOn(Carbon $date, int $branch)
    {
        return OrderPayment::with(['order.client', 'registeredBy'])
            ->whereHas('order', fn ($q) => $q->withTrashed()->where('branch_id', $branch))
            ->whereDate('paid_at', $date)
            ->orderBy('paid_at')
            ->get();
    }

    private function totalsByMethod($payments): array
    {
        $totals = [];
        foreach (array_keys(PaymentMethods::withHistorical($payments->pluck('method')->unique())) as $method) {
            $totals[$method] = round((float) $payments->where('method', $method)->sum('amount'), 2);
        }
        $totals['total'] = round(array_sum($totals), 2);

        return $totals;
    }
}
