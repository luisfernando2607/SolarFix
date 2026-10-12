<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with(['client', 'order'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('q')) {
            $search = '%' . $request->q . '%';
            $query->where(fn ($q) => $q->where('invoice_number', 'like', $search)
                ->orWhere('client_name', 'like', $search)
                ->orWhere('client_document', 'like', $search));
        }

        $invoices = $query->paginate(15);

        $recentPayments = OrderPayment::with(['order.client', 'registeredBy'])
            ->orderBy('paid_at', 'desc')
            ->take(10)
            ->get();

        return view('invoices.index', [
            'invoices' => $invoices,
            'recentPayments' => $recentPayments,
            'statuses' => Invoice::statuses(),
            'currentStatus' => $request->status,
        ]);
    }

    public function create(Request $request)
    {
        $order = null;
        $client = null;

        if ($request->filled('order_id')) {
            $order = Order::with(['client', 'brand', 'deviceModel'])->findOrFail($request->order_id);
            $client = $order->client;
        }

        $clients = Client::orderBy('name')->get();

        return view('invoices.create', [
            'order' => $order,
            'client' => $client,
            'clients' => $clients,
            'statuses' => Invoice::statuses(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'exists:orders,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'client_name' => ['required', 'string', 'max:150'],
            'client_document' => ['nullable', 'string', 'max:20'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'client_address' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'string', 'email', 'max:180'],
            'device_type' => ['nullable', 'string', 'max:40'],
            'device_brand' => ['nullable', 'string', 'max:80'],
            'device_model' => ['nullable', 'string', 'max:120'],
            'device_serial' => ['nullable', 'string', 'max:100'],
            'iva_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'issued_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        if (!empty($validated['order_id'])) {
            $existing = Invoice::where('order_id', $validated['order_id'])
                ->where('status', '!=', 'anulada')
                ->first();

            if ($existing) {
                return redirect()->route('invoices.show', $existing)
                    ->with('info', 'La orden ya tiene una factura vigente: #' . $existing->invoice_number);
            }
        }

        // El subtotal se calcula desde los ítems; no se confía en el valor enviado por el formulario.
        $items = collect($validated['items'])->map(fn ($item) => [
            'description' => $item['description'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'subtotal' => round((float) $item['quantity'] * (float) $item['unit_price'], 2),
        ]);
        $subtotal = round($items->sum('subtotal'), 2);
        $ivaAmount = round($subtotal * (float) $validated['iva_percent'] / 100, 2);

        $invoice = DB::transaction(function () use ($validated, $items, $subtotal, $ivaAmount) {
            $invoice = Invoice::createWithUniqueNumber([
                'order_id' => $validated['order_id'] ?? null,
                'client_id' => $validated['client_id'] ?? null,
                'client_name' => $validated['client_name'],
                'client_document' => $validated['client_document'] ?? null,
                'client_phone' => $validated['client_phone'] ?? null,
                'client_address' => $validated['client_address'] ?? null,
                'client_email' => $validated['client_email'] ?? null,
                'device_type' => $validated['device_type'] ?? null,
                'device_brand' => $validated['device_brand'] ?? null,
                'device_model' => $validated['device_model'] ?? null,
                'device_serial' => $validated['device_serial'] ?? null,
                'subtotal' => $subtotal,
                'iva_percent' => $validated['iva_percent'],
                'iva_amount' => $ivaAmount,
                'total' => round($subtotal + $ivaAmount, 2),
                'status' => 'emitida',
                'issued_at' => $validated['issued_at'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $invoice->items()->createMany($items->all());

            if ($invoice->order_id) {
                OrderPayment::where('order_id', $invoice->order_id)
                    ->whereNull('invoice_id')
                    ->update(['invoice_id' => $invoice->id]);
            }

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Factura #' . $invoice->invoice_number . ' emitida exitosamente.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['client', 'order', 'items', 'createdBy', 'payments']);

        $payments = $invoice->payments;
        if ($invoice->order) {
            $invoice->order->load('payments');
            $payments = $invoice->order->payments;
        }

        return view('invoices.show', [
            'invoice' => $invoice,
            'invoicePayments' => $payments,
            'statuses' => Invoice::statuses(),
        ]);
    }

    public function payments(Request $request)
    {
        $query = OrderPayment::with(['order.client', 'registeredBy'])
            ->orderBy('paid_at', 'desc');

        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('paid_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('paid_at', '<=', $request->date_to);
        }

        if ($request->filled('client')) {
            $search = $request->client;
            $query->whereHas('order.client', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('id_document', 'like', "%{$search}%");
            });
        }

        if ($request->filled('order_number')) {
            $search = $request->order_number;
            $query->whereHas('order', function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('reference')) {
            $query->where('reference', 'like', "%{$request->reference}%");
        }

        if ($request->filled('amount_min')) {
            $query->where('amount', '>=', (float) $request->amount_min);
        }

        if ($request->filled('amount_max')) {
            $query->where('amount', '<=', (float) $request->amount_max);
        }

        if ($request->filled('registered_by')) {
            $query->where('registered_by', $request->registered_by);
        }

        $payments = $query->paginate(20);

        return view('invoices.payments', [
            'payments' => $payments,
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function pdf(Invoice $invoice)
    {
        $invoice->load(['client', 'order', 'items', 'createdBy', 'payments']);

        $payments = $invoice->payments;
        if ($invoice->order) {
            $invoice->order->load('payments');
            $payments = $invoice->order->payments;
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'invoicePayments' => $payments,
            'statuses' => Invoice::statuses(),
        ]);

        return $pdf->stream('factura-' . $invoice->invoice_number . '.pdf');
    }

    public function destroy(Invoice $invoice)
    {
        DB::transaction(function () use ($invoice) {
            // Los pagos quedan libres para asociarse a una nueva factura de la orden.
            $invoice->payments()->update(['invoice_id' => null]);
            $invoice->delete();
        });

        return redirect()->route('invoices.index')
            ->with('success', 'Factura #' . $invoice->invoice_number . ' eliminada.');
    }

    public function cancel(Invoice $invoice)
    {
        if ($invoice->status === 'anulada') {
            return back()->with('error', 'La factura ya está anulada.');
        }

        DB::transaction(function () use ($invoice) {
            $invoice->update([
                'status' => 'anulada',
                'cancelled_at' => now(),
            ]);
            // Los pagos quedan libres para asociarse a una nueva factura de la orden.
            $invoice->payments()->update(['invoice_id' => null]);
        });

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Factura #' . $invoice->invoice_number . ' anulada.');
    }

    public function markPaid(Invoice $invoice)
    {
        if ($invoice->status !== 'emitida') {
            return back()->with('error', 'Solo se puede marcar como pagada una factura emitida.');
        }

        $invoice->update([
            'status' => 'pagada',
            'paid_at' => now(),
        ]);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Factura #' . $invoice->invoice_number . ' marcada como pagada.');
    }

    public function generateFromOrder(Order $order)
    {
        $order->load(['client', 'payments']);

        if ($order->total_amount <= 0) {
            return back()->with('error', 'La orden no tiene costos registrados para facturar.');
        }

        $existing = Invoice::where('order_id', $order->id)
            ->where('status', '!=', 'anulada')
            ->first();

        if ($existing) {
            return redirect()->route('invoices.show', $existing)
                ->with('info', 'La orden ya tiene una factura vigente: #' . $existing->invoice_number);
        }

        $items = [];
        $subtotal = 0;

        if ((float) $order->diagnosis_cost > 0) {
            $items[] = ['description' => 'Diagnóstico', 'quantity' => 1, 'unit_price' => (float) $order->diagnosis_cost];
            $subtotal += (float) $order->diagnosis_cost;
        }
        if ((float) $order->labor_cost > 0) {
            $items[] = ['description' => 'Mano de obra', 'quantity' => 1, 'unit_price' => (float) $order->labor_cost];
            $subtotal += (float) $order->labor_cost;
        }
        // Repuestos del inventario: una línea por repuesto; si no hay, el monto manual en una sola línea.
        $order->loadMissing('parts.sparePart');
        if ($order->parts->isNotEmpty()) {
            foreach ($order->parts->where('subtotal', '>', 0) as $part) {
                $items[] = ['description' => $part->sparePart?->name ?? 'Repuesto', 'quantity' => $part->quantity, 'unit_price' => (float) $part->unit_price];
                $subtotal += (float) $part->subtotal;
            }
        } elseif ((float) $order->parts_cost > 0) {
            $items[] = ['description' => 'Repuestos', 'quantity' => 1, 'unit_price' => (float) $order->parts_cost];
            $subtotal += (float) $order->parts_cost;
        }
        if ((float) $order->surcharge_amount > 0) {
            $items[] = ['description' => 'Recargo (' . $order->surcharge_percent . '%)', 'quantity' => 1, 'unit_price' => (float) $order->surcharge_amount];
            $subtotal += (float) $order->surcharge_amount;
        }

        // IVA según la configuración. Si los precios de la orden ya incluyen IVA, se desglosa
        // la base imponible para que el total de la factura coincida con el de la orden.
        $ivaPercent = (float) Setting::get('iva_percent', 0);
        if ($ivaPercent > 0 && Setting::get('prices_include_iva', '1') === '1') {
            $orderTotal = $subtotal;
            foreach ($items as &$item) {
                $item['unit_price'] = round($item['unit_price'] / (1 + $ivaPercent / 100), 2);
            }
            unset($item);
            $subtotal = round(array_sum(array_map(fn ($i) => round($i['quantity'] * $i['unit_price'], 2), $items)), 2);
            $ivaAmount = round($orderTotal - $subtotal, 2);
        } else {
            $ivaAmount = round($subtotal * $ivaPercent / 100, 2);
        }

        $deviceTypeLabel = Order::deviceTypes()[$order->device_type] ?? $order->device_type;

        $invoice = DB::transaction(function () use ($order, $items, $subtotal, $ivaPercent, $ivaAmount, $deviceTypeLabel) {
            $invoice = Invoice::createWithUniqueNumber([
                'order_id' => $order->id,
                'client_id' => $order->client?->id,
                'client_name' => $order->client?->name ?? 'Cliente ocasional',
                'client_document' => $order->client?->id_document,
                'client_phone' => $order->client?->phone,
                'client_address' => $order->client?->address,
                'client_email' => $order->client?->email,
                'device_type' => $deviceTypeLabel,
                'device_brand' => $order->brand?->name ?? $order->brand_text,
                'device_model' => $order->deviceModel?->name ?? $order->model_text,
                'device_serial' => $order->serial_imei,
                'subtotal' => $subtotal,
                'iva_percent' => $ivaPercent,
                'iva_amount' => $ivaAmount,
                'total' => round($subtotal + $ivaAmount, 2),
                'status' => 'emitida',
                'issued_at' => now(),
                'created_by' => Auth::id(),
            ]);

            foreach ($items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => round($item['quantity'] * $item['unit_price'], 2),
                ]);
            }

            OrderPayment::where('order_id', $order->id)
                ->whereNull('invoice_id')
                ->update(['invoice_id' => $invoice->id]);

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Factura #' . $invoice->invoice_number . ' generada desde la orden.');
    }
}
