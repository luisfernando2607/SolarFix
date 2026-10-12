<?php

namespace App\Http\Controllers;

use App\Models\CashClosing;
use App\Models\OrderPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CashClosingController extends Controller
{
    private const METHODS = [
        'cash' => 'Efectivo',
        'transfer' => 'Transferencia',
        'card' => 'Tarjeta',
        'other' => 'Otro',
    ];

    public function index(Request $request)
    {
        $date = $this->date($request);
        $payments = $this->paymentsOn($date);

        return view('cash.index', [
            'date' => $date,
            'payments' => $payments,
            'totals' => $this->totalsByMethod($payments),
            'closing' => CashClosing::with('closedBy')->whereDate('closing_date', $date)->first(),
            'history' => CashClosing::with('closedBy')->orderByDesc('closing_date')->take(10)->get(),
            'methods' => self::METHODS,
        ]);
    }

    public function store(Request $request)
    {
        $date = $this->date($request);
        $totals = $this->totalsByMethod($this->paymentsOn($date));

        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $difference = round((float) $validated['counted_cash'] - $totals['cash'], 2);

        if ($difference != 0 && blank($validated['notes'] ?? null)) {
            return back()->withInput()->withErrors([
                'notes' => 'Hay una diferencia de $' . number_format($difference, 2) . '. Escribe la justificación.',
            ]);
        }

        $closing = CashClosing::whereDate('closing_date', $date)->first()
            ?? new CashClosing(['closing_date' => $date]);

        $closing->fill([
            'expected_cash' => $totals['cash'],
            'expected_transfer' => $totals['transfer'],
            'expected_card' => $totals['card'],
            'expected_other' => $totals['other'],
            'counted_cash' => $validated['counted_cash'],
            'difference' => $difference,
            'notes' => $validated['notes'] ?? null,
            'closed_by' => Auth::id(),
        ])->save();

        return redirect()->route('cash.index', ['date' => $date->toDateString()])
            ->with('success', 'Cierre de caja del ' . $date->format('d/m/Y') . ' registrado.');
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
        foreach (array_keys(self::METHODS) as $method) {
            $totals[$method] = round((float) $payments->where('method', $method)->sum('amount'), 2);
        }
        $totals['total'] = round(array_sum($totals), 2);

        return $totals;
    }
}
