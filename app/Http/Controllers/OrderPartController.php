<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPart;
use App\Models\SparePart;
use App\Services\InventoryService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderPartController extends Controller
{
    public function __construct(private InventoryService $inventory)
    {
    }

    public function store(Request $request, Order $order)
    {
        if ($error = $this->guard($order)) {
            return back()->with('error', $error);
        }

        $validated = $request->validate([
            'spare_part_id' => ['required', 'exists:spare_parts,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        try {
            $this->inventory->addToOrder($order, SparePart::findOrFail($validated['spare_part_id']), (int) $validated['quantity'], Auth::id());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $order)->with('success', 'Repuesto agregado a la orden.');
    }

    public function destroy(Order $order, OrderPart $part)
    {
        if ($part->order_id !== $order->id) {
            abort(404);
        }
        if ($error = $this->guard($order)) {
            return back()->with('error', $error);
        }

        $this->inventory->removeFromOrder($part, Auth::id());

        return redirect()->route('orders.show', $order)->with('success', 'Repuesto quitado; las unidades volvieron al stock.');
    }

    private function guard(Order $order): ?string
    {
        abort_unless($order->isVisibleTo(Auth::user()), 403, 'Esta orden está asignada a otro técnico.');

        if (in_array($order->status, ['delivered', 'closed_no_repair'])) {
            return 'La orden está cerrada; no se pueden cambiar sus repuestos.';
        }

        return null;
    }
}
