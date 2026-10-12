<?php

namespace App\Http\Controllers;

use App\Models\CashClosing;
use App\Models\Client;
use App\Models\Sale;
use App\Models\Setting;
use App\Services\InventoryService;
use App\Support\BranchContext;
use App\Support\PaymentMethods;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Ventas de mostrador: accesorios y repuestos que se venden sin orden de servicio.
 */
class SaleController extends Controller
{
    public function __construct(private InventoryService $inventory)
    {
    }

    public function index(Request $request)
    {
        $request->validate(['date' => ['nullable', 'date']]);
        $date = $request->filled('date') ? Carbon::parse($request->date) : today();

        $sales = BranchContext::apply(Sale::with(['items', 'soldBy', 'client', 'branch']))
            ->whereBetween('sold_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->latest('sold_at')
            ->get();

        return view('sales.index', [
            'sales' => $sales,
            'date' => $date,
            'total' => round((float) $sales->whereNull('cancelled_at')->sum('total'), 2),
        ]);
    }

    public function create()
    {
        return view('sales.create', ['methods' => PaymentMethods::all()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.spare_part_id' => ['required', 'integer', 'exists:spare_parts,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'method' => ['required', Rule::in(PaymentMethods::keys())],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [], ['items' => 'productos', 'method' => 'método de pago']);

        try {
            $sale = $this->inventory->sell(BranchContext::forCreate(), $validated['items'], [
                'client_id' => $validated['client_id'] ?? null,
                'customer_name' => $validated['customer_name'] ?? null,
                'method' => $validated['method'],
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ], Auth::id());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.show', $sale)->with('success', 'Venta ' . $sale->sale_number . ' por ' . money($sale->total) . ' registrada.');
    }

    public function show(Sale $sale)
    {
        $this->authorizeSale($sale);

        return view('sales.show', ['sale' => $sale->load(['items.sparePart', 'soldBy', 'cancelledBy', 'client', 'branch'])]);
    }

    /**
     * Comprobante de 80 mm para la impresora de tickets.
     */
    public function receipt(Sale $sale)
    {
        $this->authorizeSale($sale);

        return view('sales.receipt', [
            'sale' => $sale->load(['items', 'soldBy', 'client', 'branch']),
            'company' => \App\Support\Company::info($sale->branch_id),
        ]);
    }

    public function cancel(Sale $sale)
    {
        $this->authorizeSale($sale);
        if ($error = CashClosing::lockedDayError($sale->branch_id, $sale->sold_at)) {
            return back()->with('error', $error);
        }

        try {
            $this->inventory->cancelSale($sale, Auth::id());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.show', $sale)->with('success', 'Venta anulada; los productos volvieron al stock.');
    }

    private function authorizeSale(Sale $sale): void
    {
        abort_unless(BranchContext::allows($sale->branch_id), 403, 'La venta es de otra sucursal.');
    }
}
