<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::withCount(['users', 'orders'])->orderBy('id')->get();

        return view('branches.index', compact('branches'));
    }

    public function create()
    {
        return view('branches.form', ['branch' => new Branch(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        Branch::create($this->validated($request));

        return redirect()->route('branches.index')->with('success', 'Sucursal creada.');
    }

    public function edit(Branch $branch)
    {
        return view('branches.form', compact('branch'));
    }

    public function update(Request $request, Branch $branch)
    {
        $branch->update($this->validated($request, $branch));

        return redirect()->route('branches.index')->with('success', 'Sucursal actualizada.');
    }

    /**
     * Selector de sucursal de los administradores (vacío = todas).
     */
    public function switch(Request $request)
    {
        abort_unless(BranchContext::seesAllBranches(), 403);
        $validated = $request->validate(['branch_id' => ['nullable', 'integer', 'exists:branches,id']]);

        session([BranchContext::SESSION_KEY => $validated['branch_id'] ?? null]);

        return back();
    }

    private function validated(Request $request, ?Branch $branch = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('branches', 'name')->ignore($branch?->id)],
            'code' => ['required', 'regex:/^[A-Z0-9]{2,6}$/', Rule::unique('branches', 'code')->ignore($branch?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'is_active' => ['boolean'],
        ], ['code.regex' => 'El código debe tener de 2 a 6 letras o números (ej. GYE, QTO, NORTE).']);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
