<?php

namespace App\Http\Controllers;

use App\Models\CashClosing;
use App\Models\Order;
use App\Models\OrderPayment;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\PaymentMethods;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function store(Request $request, Order $order)
    {
        abort_unless($order->isVisibleTo(Auth::user()), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(PaymentMethods::keys())],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date', 'before:tomorrow'],
        ], [], ['paid_at' => 'fecha de pago']);

        try {
            OrderPayment::register(
                $order,
                (float) $validated['amount'],
                $validated['method'],
                Auth::id(),
                $validated['reference'] ?? null,
                $validated['notes'] ?? null,
                $validated['paid_at'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $order)
            ->with('success', 'Pago de ' . money($validated['amount']) . ' registrado.');
    }

    /**
     * Devuelve al cliente lo cobrado de más (se registra como pago negativo y sale de la caja).
     */
    public function refund(Request $request, Order $order)
    {
        abort_unless($order->isVisibleTo(Auth::user()), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(PaymentMethods::keys())],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            OrderPayment::register($order, -(float) $validated['amount'], $validated['method'], Auth::id(), null,
                'Devolución al cliente' . (filled($validated['notes'] ?? null) ? ': ' . $validated['notes'] : ''));
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $order)
            ->with('success', 'Devolución de ' . money($validated['amount']) . ' registrada.');
    }

    public function destroy(Order $order, OrderPayment $payment)
    {
        if ((int) $payment->order_id !== (int) $order->id) {
            abort(404);
        }
        abort_unless($order->isVisibleTo(Auth::user()), 403);
        if ($error = CashClosing::lockedDayError($order->branch_id, $payment->paid_at)) {
            return back()->with('error', $error);
        }

        DB::transaction(function () use ($order, $payment) {
            $payment->delete();
            $order->recalculateTotals();
            $order->activeInvoice()?->syncPaidStatus($order);
        });

        return redirect()->route('orders.show', $order)
            ->with('success', 'Pago eliminado.');
    }

    public function receiptPdf(Order $order, OrderPayment $payment)
    {
        if ((int) $payment->order_id !== (int) $order->id) {
            abort(404);
        }
        abort_unless($order->isVisibleTo(Auth::user()), 403);

        $payment->load('registeredBy');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('orders.receipt', [
            'order' => $order,
            'payment' => $payment,
        ]);

        return $pdf->stream('comprobante-' . $payment->id . '.pdf');
    }
}
