<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function store(Request $request, Order $order)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(['cash', 'transfer', 'card', 'other'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $payment = OrderPayment::create([
            'order_id' => $order->id,
            'amount' => $validated['amount'],
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'paid_at' => $validated['paid_at'] ?? now(),
            'registered_by' => Auth::id(),
        ]);

        $totalPaid = $order->payments()->sum('amount');
        $balanceDue = round(max(0, (float) $order->total_amount - (float) $totalPaid), 2);
        $order->update([
            'amount_paid' => $totalPaid,
            'balance_due' => $balanceDue,
        ]);

        return redirect()->route('orders.show', $order)
            ->with('success', 'Pago de $' . number_format($validated['amount'], 2) . ' registrado.');
    }

    public function destroy(Order $order, OrderPayment $payment)
    {
        if ($payment->order_id !== $order->id) {
            abort(404);
        }

        $payment->delete();

        $totalPaid = $order->payments()->sum('amount');
        $balanceDue = round(max(0, (float) $order->total_amount - (float) $totalPaid), 2);
        $order->update([
            'amount_paid' => $totalPaid,
            'balance_due' => $balanceDue,
        ]);

        return redirect()->route('orders.show', $order)
            ->with('success', 'Pago eliminado.');
    }
}
