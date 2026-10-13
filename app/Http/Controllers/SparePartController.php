<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\SparePart;
use App\Models\Supplier;
use App\Services\InventoryService;
use App\Support\BranchContext;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SparePartController extends Controller
{
    public function __construct(private InventoryService $inventory)
    {
    }

    public function index(Request $request)
    {
        $branch = BranchContext::current();
        $parts = SparePart::with('stocks.branch:id,code')
            ->search($request->q)
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->boolean('low'), fn ($q) => $q->lowStock($branch))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('spare_parts.index', [
            'parts' => $parts,
            'categories' => $this->categories(),
            'lowCount' => SparePart::lowStock($branch)->count(),
            'branch' => $branch,
        ]);
    }

    public function create()
    {
        return view('spare_parts.create', ['categories' => $this->categories()]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePart($request);
        $initialStock = (int) ($request->validate(['initial_stock' => ['nullable', 'integer', 'min:0', 'max:100000']])['initial_stock'] ?? 0);

        $part = SparePart::create($validated);

        if ($initialStock > 0) {
            $this->inventory->receive($part, $initialStock, (float) $part->cost_price, null, 'Stock inicial', Auth::id(), null, BranchContext::forCreate());
        }

        return redirect()->route('spare-parts.show', $part)->with('success', 'Repuesto creado.');
    }

    public function show(SparePart $sparePart)
    {
        $sparePart->load(['stocks.branch', 'movements.registeredBy', 'movements.order', 'movements.supplier', 'movements.branch']);
        $seesAll = BranchContext::seesAllBranches();
        $branch = BranchContext::current();

        return view('spare_parts.show', [
            'part' => $sparePart,
            'branch' => $branch,
            'operatingBranch' => BranchContext::forCreate(),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'seesAll' => $seesAll,
            // Movimientos: los de la sucursal activa (o todos, para un admin con "todas").
            'movements' => $sparePart->movements->when($branch, fn ($m) => $m->where('branch_id', $branch))->take(100),
            'reserved' => (int) $sparePart->orderParts()
                ->whereHas('order', fn ($q) => $q->whereNotIn('status', ['delivered', 'closed_no_repair']))
                ->sum('quantity'),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(SparePart $sparePart)
    {
        return view('spare_parts.edit', ['part' => $sparePart, 'categories' => $this->categories()]);
    }

    public function update(Request $request, SparePart $sparePart)
    {
        $sparePart->update($this->validatePart($request, $sparePart));

        return redirect()->route('spare-parts.show', $sparePart)->with('success', 'Repuesto actualizado.');
    }

    public function destroy(SparePart $sparePart)
    {
        if ($sparePart->orderParts()->exists() || $sparePart->movements()->exists()) {
            return back()->with('error', 'El repuesto tiene movimientos u órdenes. Desactívalo en lugar de eliminarlo.');
        }

        $sparePart->delete();

        return redirect()->route('spare-parts.index')->with('success', 'Repuesto eliminado.');
    }

    /**
     * Entrada por compra (Flujo 7).
     */
    public function receive(Request $request, SparePart $sparePart)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'reason' => ['nullable', 'string', 'max:200'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
        ]);

        $branchId = $this->operatingBranch($request);
        $wasLow = $sparePart->isLowStock($branchId);

        $this->inventory->receive(
            $sparePart,
            (int) $validated['quantity'],
            (float) $validated['unit_cost'],
            isset($validated['sale_price']) && $validated['sale_price'] !== null && Auth::user()->can('manage-inventory')
                ? (float) $validated['sale_price'] : null,
            $validated['reason'] ?? null,
            Auth::id(),
            isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
            $branchId,
        );

        $sparePart->refresh();
        $message = 'Entrada de ' . $validated['quantity'] . ' unidades registrada. Stock en la sucursal: ' . $sparePart->stockIn($branchId) . '.';
        if ($wasLow && ! $sparePart->isLowStock($branchId)) {
            $message .= ' Ya no está en stock bajo.';
        }

        return redirect()->route('spare-parts.show', $sparePart)->with('success', $message);
    }

    public function adjust(Request $request, SparePart $sparePart)
    {
        $validated = $request->validate([
            'counted_quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $movement = $this->inventory->adjust($sparePart, (int) $validated['counted_quantity'], $validated['reason'], Auth::id(), $this->operatingBranch($request));

        return redirect()->route('spare-parts.show', $sparePart)
            ->with('success', $movement ? 'Stock ajustado a ' . $validated['counted_quantity'] . '.' : 'El stock ya coincidía con el conteo.');
    }

    /**
     * Traslado de unidades entre sucursales.
     */
    public function transfer(Request $request, SparePart $sparePart)
    {
        $validated = $request->validate([
            'from_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'to_branch_id' => ['required', 'integer', 'different:from_branch_id', Rule::exists('branches', 'id')->where('is_active', true)],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'note' => ['nullable', 'string', 'max:100'],
        ], ['to_branch_id.different' => 'Elige una sucursal de destino distinta al origen.']);

        // Un técnico solo envía desde su propia sucursal.
        abort_unless(BranchContext::allows((int) $validated['from_branch_id']), 403, 'Solo puedes trasladar desde tu sucursal.');

        try {
            $this->inventory->transfer($sparePart, (int) $validated['from_branch_id'], (int) $validated['to_branch_id'], (int) $validated['quantity'], Auth::id(), $validated['note'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('spare-parts.show', $sparePart)->with('success', 'Traslado de ' . $validated['quantity'] . ' unidades registrado.');
    }

    /**
     * Búsqueda para agregar repuestos a una orden: muestra el stock de la sucursal de la orden.
     */
    /**
     * Sugerencia de compra por proveedor (consumo de los últimos 90 días); ?export=csv para Excel.
     */
    public function reorder(Request $request)
    {
        $rows = app(\App\Services\PurchaseSuggestion::class)->for(BranchContext::current());

        if ($request->query('export') === 'csv') {
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Proveedor', 'Código', 'Repuesto', 'Stock', 'Mínimo', 'Consumo mensual', 'Pedir', 'Costo unitario', 'Total'], ';');
                foreach ($rows as $row) {
                    fputcsv($out, [$row['supplier'], $row['part']->code, $row['part']->name, $row['stock'], $row['min_stock'],
                        number_format($row['monthly'], 1, ',', ''), $row['quantity'], number_format($row['unit_cost'], 2, ',', ''), number_format($row['total'], 2, ',', '')], ';');
                }
                fclose($out);
            }, 'compras-sugeridas-' . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('spare_parts.reorder', [
            'groups' => $rows->groupBy('supplier'),
            'total' => round((float) $rows->sum('total'), 2),
            'days' => \App\Services\PurchaseSuggestion::DAYS,
        ]);
    }

    public function search(Request $request)
    {
        $request->validate(['q' => ['required', 'string', 'max:100'], 'branch_id' => ['nullable', 'integer']]);
        $branchId = $request->filled('branch_id') && BranchContext::allows((int) $request->branch_id)
            ? (int) $request->branch_id
            : BranchContext::forCreate();

        return SparePart::with('stocks')->where('is_active', true)
            ->search($request->q)
            ->orderBy('name')
            ->take(15)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'label' => trim($p->name . ($p->code ? ' · ' . $p->code : '')) . ' — stock ' . $p->stockIn($branchId) . ' — ' . money($p->sale_price),
                'stock' => $p->stockIn($branchId),
                'sale_price' => (float) $p->sale_price,
            ]);
    }

    /**
     * Sucursal en la que se registra una entrada o ajuste: la propia; un admin puede elegir.
     */
    private function operatingBranch(Request $request): int
    {
        if (BranchContext::seesAllBranches() && $request->filled('branch_id') && Branch::whereKey($request->branch_id)->exists()) {
            return (int) $request->branch_id;
        }

        return BranchContext::forCreate();
    }

    private function validatePart(Request $request, ?SparePart $part = null): array
    {
        return $request->validate([
            'code' => ['nullable', 'string', 'max:60', Rule::unique('spare_parts', 'code')->ignore($part?->id)],
            'name' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:100'],
            'compatible_brand' => ['nullable', 'string', 'max:100'],
            'compatible_model' => ['nullable', 'string', 'max:100'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'min_stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'location' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);
    }

    private function categories()
    {
        return SparePart::whereNotNull('category')->distinct()->orderBy('category')->pluck('category');
    }
}
