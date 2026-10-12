<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Warranty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WarrantyController extends Controller
{
    public function index(Request $request)
    {
        $warranties = Warranty::with(['order.client'])
            ->when($request->filled('status'), fn ($q) => $q->filterStatus($request->status))
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%' . $request->q . '%';
                $query->whereHas('order', fn ($o) => $o->where('order_number', 'like', $search)
                    ->orWhere('serial_imei', 'like', $search)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $search)
                        ->orWhere('id_document', 'like', $search)
                        ->orWhere('phone', 'like', $search)));
            })
            ->orderBy('end_date')
            ->paginate(15)
            ->withQueryString();

        return view('warranties.index', [
            'warranties' => $warranties,
            'statuses' => Warranty::statuses(),
            'currentStatus' => $request->status,
        ]);
    }

    public function update(Request $request, Warranty $warranty)
    {
        $validated = $request->validate([
            'warranty_type' => ['required', Rule::in(array_keys(Warranty::types()))],
            'conditions' => ['nullable', 'string', 'max:1000'],
        ]);

        $warranty->update($validated);

        return redirect()->route('orders.show', $warranty->order_id)->with('success', 'Garantía actualizada.');
    }

    public function createClaim(Order $order)
    {
        $order->load(['warranty', 'client', 'brand', 'deviceModel']);

        if ($error = $this->claimError($order)) {
            return redirect()->route('orders.show', $order)->with('error', $error);
        }

        return view('warranties.claim', ['order' => $order, 'warranty' => $order->warranty]);
    }

    /**
     * Abre una orden de garantía vinculada a la original, sin costo para el cliente.
     */
    public function storeClaim(Request $request, Order $order)
    {
        $order->load('warranty');

        if ($error = $this->claimError($order)) {
            return redirect()->route('orders.show', $order)->with('error', $error);
        }

        $validated = $request->validate([
            'declared_fault' => ['required', 'string', 'max:2000'],
            'physical_condition' => ['nullable', 'string', 'max:2000'],
        ]);

        $claim = DB::transaction(function () use ($order, $validated) {
            $claim = Order::createWithUniqueNumber([
                'branch_id' => $order->branch_id,
                'client_id' => $order->client_id,
                'user_id' => $order->user?->isTechnician() ? $order->user_id : null,
                'created_by' => Auth::id(),
                'parent_order_id' => $order->id,
                'device_type' => $order->device_type,
                'brand_id' => $order->brand_id,
                'model_id' => $order->model_id,
                'brand_text' => $order->brand_text,
                'model_text' => $order->model_text,
                'serial_imei' => $order->serial_imei,
                'unlock_type' => $order->unlock_type,
                'unlock_value' => $order->unlock_value,
                'physical_condition' => $validated['physical_condition'] ?? null,
                'declared_fault' => $validated['declared_fault'],
                'status' => 'warranty',
                'entry_date' => today(),
                'warranty_days' => 0,
                'notes' => 'Reclamo de garantía de la orden ' . $order->order_number,
            ]);

            OrderStatusHistory::create([
                'order_id' => $claim->id,
                'to_status' => 'warranty',
                'changed_by' => Auth::id(),
                'notes' => 'Reclamo de garantía de la orden ' . $order->order_number,
            ]);

            // Queda registrado en la orden original.
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $order->status,
                'to_status' => $order->status,
                'changed_by' => Auth::id(),
                'notes' => 'Reclamo de garantía: orden ' . $claim->order_number,
            ]);

            $order->warranty->update(['status' => 'claimed']);

            return $claim;
        });

        return redirect()->route('orders.show', $claim)
            ->with('success', 'Orden de garantía #' . $claim->order_number . ' creada.');
    }

    private function claimError(Order $order): ?string
    {
        if (! $order->warranty) {
            return 'Esta orden no tiene garantía emitida.';
        }

        if (! $order->warranty->isValid()) {
            return 'La garantía venció el ' . $order->warranty->end_date->format('d/m/Y') . '. Abre una orden normal con costo.';
        }

        return null;
    }
}
