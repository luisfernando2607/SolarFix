<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\PaymentMethods;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function store(Request $request, Order $order)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(PaymentMethods::keys())],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
        ]);

        // No se cobra más que el saldo de la orden.
        if ((float) $validated['amount'] > (float) $order->balance_due + 0.004) {
            return back()->withInput()->with('error', 'El pago (' . money($validated['amount'])
                . ') supera el saldo pendiente (' . money($order->balance_due) . ').');
        }

        OrderPayment::register(
            $order,
            (float) $validated['amount'],
            $validated['method'],
            Auth::id(),
            $validated['reference'] ?? null,
            $validated['notes'] ?? null,
            $validated['paid_at'] ?? null,
        );

        return redirect()->route('orders.show', $order)
            ->with('success', 'Pago de ' . money($validated['amount']) . ' registrado.');
    }

    public function destroy(Order $order, OrderPayment $payment)
    {
        if ($payment->order_id !== $order->id) {
            abort(404);
        }

        $payment->delete();

        $order->recalculateTotals();

        return redirect()->route('orders.show', $order)
            ->with('success', 'Pago eliminado.');
    }

    public function receiptPdf(Order $order, OrderPayment $payment)
    {
        if ($payment->order_id !== $order->id) {
            abort(404);
        }

        $payment->load('registeredBy');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('orders.receipt', [
            'order' => $order,
            'payment' => $payment,
        ]);

        return $pdf->stream('comprobante-' . $payment->id . '.pdf');
    }
}
