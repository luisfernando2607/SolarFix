<?php

namespace App\Http\Controllers;

use App\Models\DeviceBrand;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceBrandController extends Controller
{
    public function index()
    {
        $brands = DeviceBrand::with('models:brand_id,device_type')->withCount('models')->orderBy('name')->paginate(10);
        return view('brands.index', compact('brands'));
    }

    public function create()
    {
        return view('brands.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:device_brands,name'],
            'device_types' => ['nullable', 'array'],
            'device_types.*' => ['string', Rule::in(array_keys(DeviceBrand::deviceTypes()))],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $validated['device_types'] = $validated['device_types'] ?? [];

        DeviceBrand::create($validated);

        return redirect()->route('brands.index')
            ->with('success', 'Marca creada exitosamente.');
    }

    public function show(DeviceBrand $brand)
    {
        $brand->load('models');
        return view('brands.show', compact('brand'));
    }

    public function edit(DeviceBrand $brand)
    {
        return view('brands.edit', compact('brand'));
    }

    public function update(Request $request, DeviceBrand $brand)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('device_brands')->ignore($brand->id)],
            'device_types' => ['nullable', 'array'],
            'device_types.*' => ['string', Rule::in(array_keys(DeviceBrand::deviceTypes()))],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $validated['device_types'] = $validated['device_types'] ?? [];

        $brand->update($validated);

        return redirect()->route('brands.index')
            ->with('success', 'Marca actualizada exitosamente.');
    }

    public function destroy(DeviceBrand $brand)
    {
        $brand->delete();

        return redirect()->route('brands.index')
            ->with('success', 'Marca eliminada exitosamente.');
    }
}
