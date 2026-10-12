<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = Supplier::withCount('purchases')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%' . $request->q . '%';
                $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('ruc', 'like', $search)->orWhere('contact_name', 'like', $search));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.form', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $supplier = Supplier::create($this->validated($request));

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Proveedor creado.');
    }

    public function show(Supplier $supplier)
    {
        $purchases = $supplier->purchases()->with(['sparePart', 'registeredBy'])->paginate(25);

        return view('suppliers.show', [
            'supplier' => $supplier,
            'purchases' => $purchases,
            'total' => round((float) $supplier->purchases()->selectRaw('SUM(quantity * unit_cost) as total')->value('total'), 2),
        ]);
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request));

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Proveedor actualizado.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'ruc' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
