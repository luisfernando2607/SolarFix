<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\InventoryService;
use App\Services\PurchaseSuggestion;
use App\Services\WhatsAppMessage;
use App\Support\BranchContext;
use App\Support\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Órdenes de compra a proveedores: borrador (desde las compras sugeridas o a mano),
 * envío por PDF o WhatsApp y recepción, que suma el stock con el costo de compra.
 */
class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = BranchContext::apply(PurchaseOrder::with(['supplier', 'items', 'branch']))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('purchase_orders.index', ['orders' => $orders, 'statuses' => PurchaseOrder::statuses()]);
    }

    /**
     * Nueva orden: con "from_suggestion", toma las compras sugeridas de ese proveedor.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'from_suggestion' => ['nullable', 'boolean'],
        ]);
        $branchId = BranchContext::forCreate();
        $supplierId = $validated['supplier_id'] ?? null;

        $order = DB::transaction(function () use ($branchId, $supplierId, $request) {
            $order = PurchaseOrder::createWithUniqueNumber([
                'branch_id' => $branchId, 'supplier_id' => $supplierId, 'status' => 'draft', 'created_by' => Auth::id(),
            ]);

            if ($request->boolean('from_suggestion')) {
                app(PurchaseSuggestion::class)->for($branchId)
                    ->where('supplier_id', $supplierId)
                    ->each(fn ($row) => $order->items()->create([
                        'spare_part_id' => $row['part']->id, 'quantity' => $row['quantity'], 'unit_cost' => $row['unit_cost'],
                    ]));
            }

            return $order;
        });

        return redirect()->route('purchase-orders.show', $order)->with('success', "Orden de compra {$order->number} creada como borrador.");
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeOrder($purchaseOrder);

        return view('purchase_orders.show', [
            'order' => $purchaseOrder->load(['items.sparePart', 'supplier', 'branch', 'createdBy', 'receivedBy']),
            'statuses' => PurchaseOrder::statuses(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Guarda el borrador: proveedor, notas, cantidades y costos; agrega o quita repuestos.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorizeOrder($purchaseOrder);
        abort_unless($purchaseOrder->status === 'draft', 422, 'Solo se edita una orden en borrador.');

        $validated = $request->validate([
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'add_part_id' => ['nullable', 'integer', Rule::exists('spare_parts', 'id')],
            'add_quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ], [], ['items.*.quantity' => 'cantidad', 'items.*.unit_cost' => 'costo']);

        DB::transaction(function () use ($purchaseOrder, $validated) {
            $purchaseOrder->update(['supplier_id' => $validated['supplier_id'] ?? null, 'notes' => $validated['notes'] ?? null]);

            foreach ($validated['items'] ?? [] as $itemId => $values) {
                $item = $purchaseOrder->items()->find($itemId);
                if (! $item) {
                    continue;
                }
                (int) $values['quantity'] === 0
                    ? $item->delete()
                    : $item->update(['quantity' => (int) $values['quantity'], 'unit_cost' => $values['unit_cost']]);
            }

            if (! empty($validated['add_part_id'])) {
                $part = \App\Models\SparePart::find($validated['add_part_id']);
                $item = $purchaseOrder->items()->firstOrNew(['spare_part_id' => $part->id]);
                $item->quantity = ($item->exists ? $item->quantity : 0) + (int) ($validated['add_quantity'] ?? 1);
                $item->unit_cost = $item->exists ? $item->unit_cost : $part->cost_price;
                $item->save();
            }
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Orden de compra guardada.');
    }

    public function send(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeOrder($purchaseOrder);
        if ($purchaseOrder->status !== 'draft' || $purchaseOrder->items()->doesntExist()) {
            return back()->with('error', 'Solo se envía un borrador con al menos un repuesto.');
        }

        $purchaseOrder->update(['status' => 'sent', 'sent_at' => now()]);

        return back()->with('success', 'Orden marcada como enviada. Descarga el PDF o envíala por WhatsApp al proveedor.');
    }

    public function pdf(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeOrder($purchaseOrder);
        $purchaseOrder->load(['items.sparePart', 'supplier', 'branch']);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('purchase_orders.pdf', [
            'order' => $purchaseOrder,
            'company' => Company::info($purchaseOrder->branch_id),
        ])->stream($purchaseOrder->number . '.pdf');
    }

    public function whatsapp(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeOrder($purchaseOrder);
        $purchaseOrder->load(['items.sparePart', 'supplier']);
        $phone = WhatsAppMessage::normalizePhone($purchaseOrder->supplier?->phone);
        if (! $phone) {
            return back()->with('error', 'El proveedor no tiene un teléfono válido para WhatsApp.');
        }

        $company = Company::info($purchaseOrder->branch_id);
        $lines = $purchaseOrder->items->map(fn ($i) => '- ' . $i->quantity . ' × ' . $i->sparePart?->name . ($i->sparePart?->code ? " ({$i->sparePart->code})" : ''))->join("\n");
        $message = "Hola" . ($purchaseOrder->supplier?->contact_name ? ' ' . $purchaseOrder->supplier->contact_name : '') . ", le escribimos de *{$company['name']}*.\n"
            . "Quisiéramos hacer el siguiente pedido (orden {$purchaseOrder->number}):\n\n{$lines}\n\n"
            . ($purchaseOrder->notes ? $purchaseOrder->notes . "\n\n" : '') . '¿Nos confirma disponibilidad y precios? ¡Gracias!';

        return redirect()->away(WhatsAppMessage::url($phone, $message));
    }

    /**
     * Recepción: lo que llegó entra al stock de la sucursal con el costo de compra.
     */
    public function receive(Request $request, PurchaseOrder $purchaseOrder, InventoryService $inventory)
    {
        $this->authorizeOrder($purchaseOrder);
        abort_unless($purchaseOrder->isOpen(), 422, 'La orden ya fue recibida o anulada.');

        $validated = $request->validate([
            'received' => ['required', 'array'],
            'received.*.quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'received.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ], [], ['received.*.quantity' => 'cantidad recibida', 'received.*.unit_cost' => 'costo']);

        DB::transaction(function () use ($purchaseOrder, $validated, $inventory) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            abort_unless($order->isOpen(), 422, 'La orden ya fue recibida o anulada.');

            foreach ($order->items()->with('sparePart')->get() as $item) {
                $values = $validated['received'][$item->id] ?? ['quantity' => 0, 'unit_cost' => $item->unit_cost];
                $quantity = (int) $values['quantity'];
                $item->update(['received_quantity' => $quantity, 'unit_cost' => $values['unit_cost']]);
                if ($quantity > 0) {
                    $inventory->receive($item->sparePart, $quantity, (float) $values['unit_cost'], null,
                        'Orden de compra ' . $order->number, Auth::id(), $order->supplier_id, $order->branch_id);
                }
            }

            $order->update(['status' => 'received', 'received_at' => now(), 'received_by' => Auth::id()]);
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Mercadería recibida: el stock y los costos se actualizaron.');
    }

    public function cancel(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeOrder($purchaseOrder);
        if (! $purchaseOrder->isOpen()) {
            return back()->with('error', 'Solo se anula una orden en borrador o enviada.');
        }
        $purchaseOrder->update(['status' => 'cancelled']);

        return back()->with('success', 'Orden de compra anulada.');
    }

    private function authorizeOrder(PurchaseOrder $order): void
    {
        abort_unless(BranchContext::allows($order->branch_id), 403, 'La orden de compra es de otra sucursal.');
    }
}
