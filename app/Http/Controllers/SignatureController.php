<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderSignature;
use App\Support\PaymentMethods;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SignatureController extends Controller
{
    private const MAX_BYTES = 512 * 1024;

    public function create(Order $order, string $stage)
    {
        $this->authorizeOrder($order);

        if ($error = $this->stageError($order, $stage)) {
            return redirect()->route('orders.show', $order)->with('error', $error);
        }

        $order->load(['client', 'brand', 'deviceModel', 'accessories', 'checklistResults.item']);

        return view('signatures.create', [
            'order' => $order,
            'stage' => $stage,
            'damagedItems' => $order->checklistResults->where('result', 'damaged')->filter(fn ($r) => $r->item),
        ]);
    }

    public function store(Request $request, Order $order, string $stage)
    {
        $this->authorizeOrder($order);

        if ($error = $this->stageError($order, $stage)) {
            return redirect()->route('orders.show', $order)->with('error', $error);
        }

        $request->validate([
            'signature' => ['required', 'string'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', Rule::in(PaymentMethods::keys())],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        // Cobro del saldo en el mismo paso de la entrega (solo quien puede registrar pagos).
        $paymentAmount = round((float) $request->input('payment_amount', 0), 2);
        if ($stage === 'delivery' && $paymentAmount > 0) {
            abort_unless(Auth::user()->can('register-payments'), 403, 'No puedes registrar pagos.');
            if (! $request->filled('payment_method')) {
                return back()->withErrors(['payment_method' => 'Elige el método de pago.'])->withInput();
            }
            if ($paymentAmount > (float) $order->balance_due + 0.004) {
                return back()->withErrors(['payment_amount' => 'El pago supera el saldo pendiente (' . money($order->balance_due) . ').'])->withInput();
            }
        }

        $png = $this->decodePng($request->signature);
        if (! $png) {
            return back()->withErrors(['signature' => 'La firma no es válida. Vuelve a firmar.']);
        }

        // Almacenamiento privado: las firmas solo se ven a través de la app.
        $path = 'signatures/' . $order->id . '-' . $stage . '-' . now()->format('YmdHis') . '.png';
        Storage::disk('local')->put($path, $png);

        try {
            DB::transaction(function () use ($order, $stage, $path, $request, $paymentAmount) {
                if ($stage === 'delivery' && $paymentAmount > 0) {
                    OrderPayment::register($order, $paymentAmount, $request->payment_method, Auth::id(), $request->payment_reference, 'Cobrado al entregar');
                }

                $existing = OrderSignature::where('order_id', $order->id)->where('stage', $stage)->first();
                if ($existing) {
                    Storage::disk('local')->delete($existing->signature_path);
                }

                OrderSignature::updateOrCreate(
                    ['order_id' => $order->id, 'stage' => $stage],
                    ['signature_path' => $path, 'signed_at' => now(), 'ip_address' => $request->ip()],
                );

                if ($stage === 'delivery') {
                    $order->isReturnWithoutRepair()
                        ? $order->markReturnedWithoutRepair(Auth::id())
                        : $order->markDelivered(Auth::id(), 'Entrega firmada por el cliente');
                }
            });
        } catch (\DomainException $e) {
            // Cobro rechazado (saldo cambiado o caja cerrada): no queda la firma suelta.
            Storage::disk('local')->delete($path);

            return back()->withErrors(['payment_amount' => $e->getMessage()])->withInput();
        }

        $message = match (true) {
            $stage === 'reception' => 'Firma de recepción registrada.',
            $order->isReturnWithoutRepair() => 'Devolución sin reparación firmada.',
            default => 'Entrega firmada. La orden quedó como entregada.',
        };

        return redirect()->route('orders.show', $order)->with('success', $message);
    }

    public function image(Order $order, string $stage)
    {
        $this->authorizeOrder($order);

        $signature = OrderSignature::where('order_id', $order->id)->where('stage', $stage)->firstOrFail();

        abort_unless(Storage::disk('local')->exists($signature->signature_path), 404);

        return Storage::disk('local')->response($signature->signature_path, null, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function authorizeOrder(Order $order): void
    {
        abort_unless($order->isVisibleTo(Auth::user()), 403, 'Esta orden está asignada a otro técnico.');
    }

    private function stageError(Order $order, string $stage): ?string
    {
        $exists = $order->signatures()->where('stage', $stage)->exists();

        if ($exists && ! Auth::user()->isAdmin() && ! Auth::user()->isSuperAdmin()) {
            return 'Esta firma ya fue registrada. Solo un administrador puede reemplazarla.';
        }

        if ($stage === 'delivery' && ! in_array($order->status, ['ready', 'delivered', 'closed_no_repair'])) {
            return 'La orden debe estar "Lista" (o "Cerrada sin reparación") para firmar la entrega.';
        }

        return null;
    }

    private function decodePng(string $dataUrl): ?string
    {
        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            return null;
        }

        $binary = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);

        if ($binary === false || strlen($binary) > self::MAX_BYTES || ! str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return null;
        }

        return $binary;
    }
}
