<?php

namespace App\Http\Controllers;

use App\Models\Accessory;
use App\Models\Client;
use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderPhoto;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['client', 'brand', 'deviceModel', 'user'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate(15);

        return view('orders.index', [
            'orders' => $orders,
            'statuses' => Order::statuses(),
            'currentStatus' => $request->status,
        ]);
    }

    public function create()
    {
        $modelsByBrand = DeviceModel::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->groupBy('brand_id')
            ->map(fn ($models) => $models->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'device_type' => $m->device_type]));

        return view('orders.create', [
            'clients' => Client::orderBy('name')->get(),
            'brands' => DeviceBrand::where('is_active', true)->orderBy('name')->get(),
            'accessories' => Accessory::where('is_active', true)->orderBy('name')->get(),
            'statuses' => Order::statuses(),
            'deviceTypes' => Order::deviceTypes(),
            'unlockTypes' => Order::unlockTypes(),
            'modelsByBrand' => $modelsByBrand,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'device_type' => ['required', Rule::in(array_keys(Order::deviceTypes()))],
            'brand_id' => ['nullable', 'exists:device_brands,id'],
            'model_id' => ['nullable', 'exists:device_models,id'],
            'brand_text' => ['nullable', 'string', 'max:100'],
            'model_text' => ['nullable', 'string', 'max:100'],
            'serial_imei' => ['nullable', 'string', 'max:100'],
            'physical_condition' => ['nullable', 'string'],
            'declared_fault' => ['required', 'string'],
            'unlock_type' => ['required', Rule::in(array_keys(Order::unlockTypes()))],
            'unlock_value' => ['nullable', 'string', 'max:255'],
            'accessories' => ['nullable', 'array'],
            'accessories.*' => ['exists:accessories,id'],
            'entry_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240'],
        ]);

        $validated['order_number'] = $this->generateOrderNumber();
        $validated['status'] = 'received';
        $validated['created_by'] = Auth::id();
        $validated['user_id'] = Auth::id();

        $order = Order::create($validated);

        if (!empty($validated['accessories'])) {
            $order->accessories()->attach($validated['accessories']);
        }

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('order_photos', 'public');
                OrderPhoto::create([
                    'order_id' => $order->id,
                    'stage' => 'reception',
                    'file_path' => $path,
                    'taken_by' => Auth::id(),
                ]);
            }
        }

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => 'received',
            'changed_by' => Auth::id(),
        ]);

        return redirect()->route('orders.show', $order)
            ->with('success', 'Orden #' . $order->order_number . ' creada exitosamente.');
    }

    public function show(Order $order)
    {
        $order->load([
            'client',
            'brand',
            'deviceModel',
            'user',
            'createdBy',
            'payments.registeredBy',
            'statusHistory.changedBy',
            'accessories',
            'photos.takenBy',
        ]);

        return view('orders.show', [
            'order' => $order,
            'statuses' => Order::statuses(),
        ]);
    }

    public function edit(Order $order)
    {
        $modelsByBrand = DeviceModel::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->groupBy('brand_id')
            ->map(fn ($models) => $models->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'device_type' => $m->device_type]));

        return view('orders.edit', [
            'order' => $order->load('accessories'),
            'clients' => Client::orderBy('name')->get(),
            'brands' => DeviceBrand::where('is_active', true)->orderBy('name')->get(),
            'accessories' => Accessory::where('is_active', true)->orderBy('name')->get(),
            'statuses' => Order::statuses(),
            'deviceTypes' => Order::deviceTypes(),
            'unlockTypes' => Order::unlockTypes(),
            'modelsByBrand' => $modelsByBrand,
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'device_type' => ['required', Rule::in(array_keys(Order::deviceTypes()))],
            'brand_id' => ['nullable', 'exists:device_brands,id'],
            'model_id' => ['nullable', 'exists:device_models,id'],
            'brand_text' => ['nullable', 'string', 'max:100'],
            'model_text' => ['nullable', 'string', 'max:100'],
            'serial_imei' => ['nullable', 'string', 'max:100'],
            'physical_condition' => ['nullable', 'string'],
            'declared_fault' => ['required', 'string'],
            'diagnosis' => ['nullable', 'string'],
            'work_done' => ['nullable', 'string'],
            'unlock_type' => ['required', Rule::in(array_keys(Order::unlockTypes()))],
            'unlock_value' => ['nullable', 'string', 'max:255'],
            'accessories' => ['nullable', 'array'],
            'accessories.*' => ['exists:accessories,id'],
            'entry_date' => ['required', 'date'],
            'estimated_delivery' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'diagnosis_cost' => ['nullable', 'numeric', 'min:0'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'parts_cost' => ['nullable', 'numeric', 'min:0'],
            'surcharge_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'balance_due' => ['nullable', 'numeric', 'min:0'],
            'warranty_days' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(array_keys(Order::statuses()))],
            'notes' => ['nullable', 'string'],
        ]);

        $oldStatus = $order->status;
        $order->update($validated);

        if (isset($validated['accessories'])) {
            $order->accessories()->sync($validated['accessories']);
        }

        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $oldStatus,
                'to_status' => $validated['status'],
                'changed_by' => Auth::id(),
            ]);
        }

        return redirect()->route('orders.show', $order)
            ->with('success', 'Orden #' . $order->order_number . ' actualizada.');
    }

    public function destroy(Order $order)
    {
        $order->delete();

        return redirect()->route('orders.index')
            ->with('success', 'Orden eliminada.');
    }

    public function uploadPhoto(Request $request, Order $order)
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240'],
            'stage' => ['nullable', Rule::in(['reception', 'process', 'delivery'])],
            'caption' => ['nullable', 'string', 'max:200'],
        ]);

        $path = $request->file('photo')->store('order_photos', 'public');

        OrderPhoto::create([
            'order_id' => $order->id,
            'stage' => $validated['stage'] ?? 'process',
            'file_path' => $path,
            'caption' => $validated['caption'] ?? null,
            'taken_by' => Auth::id(),
        ]);

        return redirect()->route('orders.show', $order)
            ->with('success', 'Foto agregada.');
    }

    public function deletePhoto(Order $order, OrderPhoto $photo)
    {
        if ($photo->order_id !== $order->id) {
            abort(404);
        }

        Storage::disk('public')->delete($photo->file_path);
        $photo->delete();

        return redirect()->route('orders.show', $order)
            ->with('success', 'Foto eliminada.');
    }

    private function generateOrderNumber(): string
    {
        $prefix = 'ORD-' . now()->format('Ymd') . '-';
        $last = Order::where('order_number', 'like', $prefix . '%')
            ->orderBy('order_number', 'desc')
            ->value('order_number');

        $next = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
