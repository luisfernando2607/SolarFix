<?php

namespace App\Http\Controllers;

use App\Models\Accessory;
use Illuminate\Http\Request;

class AccessoryController extends Controller
{
    public function index()
    {
        $accessories = Accessory::orderBy('name')->paginate(10);
        return view('accessories.index', compact('accessories'));
    }

    public function create()
    {
        return view('accessories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:accessories,name'],
            'is_active' => ['boolean'],
        ]);

        Accessory::create($validated);

        return redirect()->route('accessories.index')
            ->with('success', 'Accesorio creado exitosamente.');
    }

    public function show(Accessory $accessory)
    {
        return view('accessories.show', compact('accessory'));
    }

    public function edit(Accessory $accessory)
    {
        return view('accessories.edit', compact('accessory'));
    }

    public function update(Request $request, Accessory $accessory)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:accessories,name,' . $accessory->id],
            'is_active' => ['boolean'],
        ]);

        $accessory->update($validated);

        return redirect()->route('accessories.index')
            ->with('success', 'Accesorio actualizado exitosamente.');
    }

    public function destroy(Accessory $accessory)
    {
        $accessory->delete();

        return redirect()->route('accessories.index')
            ->with('success', 'Accesorio eliminado exitosamente.');
    }
}
