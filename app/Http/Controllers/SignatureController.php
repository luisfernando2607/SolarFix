<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderSignature;
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

        $order->load(['client', 'brand', 'deviceModel', 'accessories']);

        return view('signatures.create', ['order' => $order, 'stage' => $stage]);
    }

    public function store(Request $request, Order $order, string $stage)
    {
        $this->authorizeOrder($order);

        if ($error = $this->stageError($order, $stage)) {
            return redirect()->route('orders.show', $order)->with('error', $error);
        }

        $request->validate(['signature' => ['required', 'string']]);

        $png = $this->decodePng($request->signature);
        if (! $png) {
            return back()->withErrors(['signature' => 'La firma no es válida. Vuelve a firmar.']);
        }

        // Almacenamiento privado: las firmas solo se ven a través de la app.
        $path = 'signatures/' . $order->id . '-' . $stage . '-' . now()->format('YmdHis') . '.png';
        Storage::disk('local')->put($path, $png);

        DB::transaction(function () use ($order, $stage, $path, $request) {
            $existing = OrderSignature::where('order_id', $order->id)->where('stage', $stage)->first();
            if ($existing) {
                Storage::disk('local')->delete($existing->signature_path);
            }

            OrderSignature::updateOrCreate(
                ['order_id' => $order->id, 'stage' => $stage],
                ['signature_path' => $path, 'signed_at' => now(), 'ip_address' => $request->ip()],
            );

            if ($stage === 'delivery') {
                $order->markDelivered(Auth::id(), 'Entrega firmada por el cliente');
            }
        });

        $message = $stage === 'delivery' ? 'Entrega firmada. La orden quedó como entregada.' : 'Firma de recepción registrada.';

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

        if ($stage === 'delivery' && ! in_array($order->status, ['ready', 'delivered'])) {
            return 'La orden debe estar "Lista" para firmar la entrega.';
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
