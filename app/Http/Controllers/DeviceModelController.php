<?php

namespace App\Http\Controllers;

use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceModelController extends Controller
{
    public function index()
    {
        $models = DeviceModel::with('brand')->orderBy('name')->paginate(10);
        return view('models.index', compact('models'));
    }

    public function create()
    {
        $brands = DeviceBrand::where('is_active', true)->orderBy('name')->get();
        return view('models.create', compact('brands'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'brand_id' => ['required', 'exists:device_brands,id'],
            'name' => ['required', 'string', 'max:120'],
            'device_type' => ['required', Rule::in(array_keys(DeviceModel::deviceTypes()))],
            'is_active' => ['boolean'],
        ]);

        DeviceModel::create($validated);

        return redirect()->route('models.index')
            ->with('success', 'Modelo creado exitosamente.');
    }

    public function show(DeviceModel $model)
    {
        $model->load('brand');
        return view('models.show', compact('model'));
    }

    public function edit(DeviceModel $model)
    {
        $brands = DeviceBrand::where('is_active', true)->orderBy('name')->get();
        return view('models.edit', compact('model', 'brands'));
    }

    public function update(Request $request, DeviceModel $model)
    {
        $validated = $request->validate([
            'brand_id' => ['required', 'exists:device_brands,id'],
            'name' => ['required', 'string', 'max:120'],
            'device_type' => ['required', Rule::in(array_keys(DeviceModel::deviceTypes()))],
            'is_active' => ['boolean'],
        ]);

        $model->update($validated);

        return redirect()->route('models.index')
            ->with('success', 'Modelo actualizado exitosamente.');
    }

    public function destroy(DeviceModel $model)
    {
        $model->delete();

        return redirect()->route('models.index')
            ->with('success', 'Modelo eliminado exitosamente.');
    }
}
