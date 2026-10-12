<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderPayment;
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
            'subtotal' => ['required', 'numeric', 'min:0'],
            'iva_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'issued_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $ivaAmount = round((float) $validated['subtotal'] * (float) $validated['iva_percent'] / 100, 2);
        $total = round((float) $validated['subtotal'] + $ivaAmount, 2);

        $invoice = Invoice::create([
            'order_id' => $validated['order_id'],
            'client_id' => $validated['client_id'],
            'client_name' => $validated['client_name'],
            'client_document' => $validated['client_document'],
            'client_phone' => $validated['client_phone'],
            'client_address' => $validated['client_address'],
            'client_email' => $validated['client_email'],
            'device_type' => $validated['device_type'],
            'device_brand' => $validated['device_brand'],
            'device_model' => $validated['device_model'],
            'device_serial' => $validated['device_serial'],
            'subtotal' => $validated['subtotal'],
            'iva_percent' => $validated['iva_percent'],
            'iva_amount' => $ivaAmount,
            'total' => $total,
            'status' => 'emitida',
            'issued_at' => $validated['issued_at'],
            'notes' => $validated['notes'],
            'created_by' => Auth::id(),
        ]);

        foreach ($validated['items'] as $item) {
            $itemSubtotal = round((float) $item['quantity'] * (float) $item['unit_price'], 2);
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $itemSubtotal,
            ]);
        }

        if ($invoice->order_id) {
            \App\Models\OrderPayment::where('order_id', $invoice->order_id)
                ->whereNull('invoice_id')
                ->update(['invoice_id' => $invoice->id]);
        }

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
        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('success', 'Factura #' . $invoice->invoice_number . ' eliminada.');
    }

    public function cancel(Invoice $invoice)
    {
        $invoice->update([
            'status' => 'anulada',
            'cancelled_at' => now(),
        ]);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Factura #' . $invoice->invoice_number . ' anulada.');
    }

    public function markPaid(Invoice $invoice)
    {
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
        if ((float) $order->parts_cost > 0) {
            $items[] = ['description' => 'Repuestos', 'quantity' => 1, 'unit_price' => (float) $order->parts_cost];
            $subtotal += (float) $order->parts_cost;
        }
        if ((float) $order->surcharge_amount > 0) {
            $items[] = ['description' => 'Recargo (' . $order->surcharge_percent . '%)', 'quantity' => 1, 'unit_price' => (float) $order->surcharge_amount];
            $subtotal += (float) $order->surcharge_amount;
        }

        $deviceTypeLabel = Order::deviceTypes()[$order->device_type] ?? $order->device_type;

        $invoice = DB::transaction(function () use ($order, $items, $subtotal, $deviceTypeLabel) {
            $invoice = Invoice::create([
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
                'iva_percent' => 0,
                'iva_amount' => 0,
                'total' => $subtotal,
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
