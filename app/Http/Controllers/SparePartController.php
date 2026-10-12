<?php

namespace App\Http\Controllers;

use App\Models\SparePart;
use App\Services\InventoryService;
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
        $parts = SparePart::query()
            ->search($request->q)
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->boolean('low'), fn ($q) => $q->lowStock())
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('spare_parts.index', [
            'parts' => $parts,
            'categories' => $this->categories(),
            'lowCount' => SparePart::lowStock()->count(),
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
            $this->inventory->receive($part, $initialStock, (float) $part->cost_price, null, 'Stock inicial', Auth::id());
        }

        return redirect()->route('spare-parts.show', $part)->with('success', 'Repuesto creado.');
    }

    public function show(SparePart $sparePart)
    {
        $sparePart->load(['movements.registeredBy', 'movements.order']);

        return view('spare_parts.show', ['part' => $sparePart]);
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
        ]);

        $wasLow = $sparePart->isLowStock();

        $this->inventory->receive(
            $sparePart,
            (int) $validated['quantity'],
            (float) $validated['unit_cost'],
            isset($validated['sale_price']) && $validated['sale_price'] !== null && Auth::user()->can('manage-inventory')
                ? (float) $validated['sale_price'] : null,
            $validated['reason'] ?? null,
            Auth::id(),
        );

        $sparePart->refresh();
        $message = 'Entrada de ' . $validated['quantity'] . ' unidades registrada. Stock: ' . $sparePart->stock_quantity . '.';
        if ($wasLow && ! $sparePart->isLowStock()) {
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

        $movement = $this->inventory->adjust($sparePart, (int) $validated['counted_quantity'], $validated['reason'], Auth::id());

        return redirect()->route('spare-parts.show', $sparePart)
            ->with('success', $movement ? 'Stock ajustado a ' . $validated['counted_quantity'] . '.' : 'El stock ya coincidía con el conteo.');
    }

    /**
     * Búsqueda para agregar repuestos a una orden.
     */
    public function search(Request $request)
    {
        $request->validate(['q' => ['required', 'string', 'max:100']]);

        return SparePart::where('is_active', true)
            ->search($request->q)
            ->orderBy('name')
            ->take(15)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'label' => trim($p->name . ($p->code ? ' · ' . $p->code : '')) . ' — stock ' . $p->stock_quantity . ' — $' . number_format((float) $p->sale_price, 2),
                'stock' => $p->stock_quantity,
                'sale_price' => (float) $p->sale_price,
            ]);
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
