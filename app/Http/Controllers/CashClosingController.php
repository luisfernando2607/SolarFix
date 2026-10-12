<?php

namespace App\Http\Controllers;

use App\Models\CashClosing;
use App\Models\CashMovement;
use App\Models\OrderPayment;
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
        $payments = $this->paymentsOn($date);

        $totals = $this->totalsByMethod($payments);

        return view('cash.index', [
            'date' => $date,
            'payments' => $payments,
            'totals' => $totals,
            'movements' => CashMovement::with('registeredBy')->whereDate('movement_date', $date)->orderBy('id')->get(),
            'cash' => $this->cashSummary($date, $totals['cash']),
            'closing' => CashClosing::with('closedBy')->whereDate('closing_date', $date)->first(),
            'history' => CashClosing::with('closedBy')->orderByDesc('closing_date')->take(10)->get(),
            'methods' => PaymentMethods::withHistorical($payments->pluck('method')->unique()),
        ]);
    }

    public function store(Request $request)
    {
        $date = $this->date($request);
        $totals = $this->totalsByMethod($this->paymentsOn($date));
        $cash = $this->cashSummary($date, $totals['cash']);

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

        $closing = CashClosing::whereDate('closing_date', $date)->first()
            ?? new CashClosing(['closing_date' => $date]);

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

        return redirect()->route('cash.index', ['date' => $date->toDateString()])
            ->with('success', 'Cierre de caja del ' . $date->format('d/m/Y') . ' registrado.');
    }

    /**
     * Fondo inicial, gastos, retiros u otros ingresos del día.
     */
    public function storeMovement(Request $request)
    {
        $date = $this->date($request);
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(CashMovement::types()))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        CashMovement::create($validated + ['movement_date' => $date, 'registered_by' => Auth::id()]);

        return redirect()->route('cash.index', ['date' => $date->toDateString()])
            ->with('success', CashMovement::types()[$validated['type']] . ' registrado.');
    }

    public function destroyMovement(CashMovement $movement)
    {
        abort_unless(Auth::user()->isAdmin() || Auth::user()->isSuperAdmin(), 403, 'Solo un administrador puede eliminar movimientos de caja.');

        $date = $movement->movement_date->toDateString();
        $movement->delete();

        return redirect()->route('cash.index', ['date' => $date])->with('success', 'Movimiento eliminado.');
    }

    /**
     * Efectivo esperado en caja = fondo inicial + cobros en efectivo + otros ingresos − gastos − retiros.
     */
    private function cashSummary(Carbon $date, float $cashPayments): array
    {
        $movements = CashMovement::whereDate('movement_date', $date)->get();
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

    private function paymentsOn(Carbon $date)
    {
        return OrderPayment::with(['order.client', 'registeredBy'])
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
