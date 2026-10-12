<?php

namespace App\Http\Controllers;

use App\Models\Accessory;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Checklist;
use App\Models\Client;
use App\Models\Commission;
use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderPhoto;
use App\Models\OrderStatusHistory;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AssignmentNotification;
use App\Services\InventoryService;
use App\Services\OrderWorkflow;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::visibleTo(Auth::user())
            ->with(['client', 'brand', 'deviceModel', 'user'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('q')) {
            $search = '%' . $request->q . '%';
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', $search)
                    ->orWhere('serial_imei', 'like', $search)
                    ->orWhere('brand_text', 'like', $search)
                    ->orWhere('model_text', 'like', $search)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $search)
                        ->orWhere('id_document', 'like', $search)
                        ->orWhere('phone', 'like', $search))
                    ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $search))
                    ->orWhereHas('deviceModel', fn ($m) => $m->where('name', 'like', $search));
            });
        }

        $orders = $query->paginate(15);

        return view('orders.index', [
            'orders' => $orders,
            'statuses' => Order::statuses(),
            'currentStatus' => $request->status,
        ]);
    }

    public function create(Request $request)
    {
        $data = $this->formData();

        // Orden creada desde una cita de la agenda o desde la ficha del cliente: se precarga el cliente.
        $client = null;
        if ($request->filled('appointment_id')) {
            $appointment = $this->linkableAppointment($request->appointment_id);
            $client = $appointment->client;
            $data['appointment'] = $appointment;
        } elseif ($request->filled('client_id')) {
            $client = Client::find($request->client_id);
        }
        $data['prefillClient'] = $client ? [
            'id' => $client->id, 'name' => $client->name, 'id_document' => $client->id_document,
            'phone' => $client->phone, 'email' => $client->email,
        ] : null;

        return view('orders.create', $data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => ['nullable'],
            'client_name' => ['required_without:client_id', 'nullable', 'string', 'max:150'],
            'client_id_document' => ['nullable', 'string', 'max:20'],
            'client_phone' => ['required_without:client_id', 'nullable', 'string', 'max:30'],
            'client_email' => ['nullable', 'email', 'max:180'],
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
            'photos' => ['nullable'],
            'photos.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240'],
            'diagnosis_cost' => ['nullable', 'numeric', 'min:0'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'parts_cost' => ['nullable', 'numeric', 'min:0'],
            'surcharge_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'warranty_days' => ['nullable', 'integer', 'min:0'],
            'user_id' => ['nullable', Rule::in($this->assignableTechnicians()->pluck('id'))],
            'appointment_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
        ]);

        // Sucursal: los administradores la eligen; los demás usan la suya.
        $validated['branch_id'] = BranchContext::seesAllBranches() && ! empty($validated['branch_id'])
            ? (int) $validated['branch_id']
            : BranchContext::forCreate();
        if (! empty($validated['user_id']) && (int) User::whereKey($validated['user_id'])->value('branch_id') !== $validated['branch_id']) {
            return back()->withInput()->withErrors(['user_id' => 'El técnico elegido no pertenece a esa sucursal.']);
        }

        $appointment = ! empty($validated['appointment_id']) ? $this->linkableAppointment($validated['appointment_id']) : null;
        unset($validated['appointment_id']);
        // La visita la atiende el técnico de la cita.
        if ($appointment?->user_id && empty($validated['user_id']) && ! Auth::user()->isTechnician()) {
            $validated['user_id'] = $appointment->user_id;
        }

        if (!empty($validated['client_id']) && !Client::where('id', $validated['client_id'])->exists()) {
            return back()->withErrors(['client_id' => 'El cliente seleccionado no existe.'])->withInput();
        }

        if (empty($validated['client_id']) && !empty($validated['client_name'])) {
            $client = Client::create([
                'name' => $validated['client_name'],
                'id_document' => $validated['client_id_document'] ?? null,
                'phone' => $validated['client_phone'] ?? null,
                'email' => $validated['client_email'] ?? null,
                'client_type' => 'individual',
            ]);
            $validated['client_id'] = $client->id;
        }

        unset($validated['client_name'], $validated['client_id_document'], $validated['client_phone'], $validated['client_email']);

        $diagnosisCost = (float) ($validated['diagnosis_cost'] ?? 0);
        $laborCost = (float) ($validated['labor_cost'] ?? 0);
        $partsCost = (float) ($validated['parts_cost'] ?? 0);
        $surchargePercent = (float) ($validated['surcharge_percent'] ?? 0);
        $subtotal = $diagnosisCost + $laborCost + $partsCost;
        $surchargeAmount = $subtotal * ($surchargePercent / 100);
        $validated['surcharge_amount'] = round($surchargeAmount, 2);
        $validated['total_amount'] = round($subtotal + $surchargeAmount, 2);
        $amountPaid = round((float) ($validated['amount_paid'] ?? 0), 2);
        $validated['amount_paid'] = $amountPaid;
        $validated['balance_due'] = round(max(0, $validated['total_amount'] - $amountPaid), 2);

        $validated['status'] = 'received';
        $validated['created_by'] = Auth::id();
        $validated['user_id'] = Auth::user()->isTechnician() ? Auth::id() : ($validated['user_id'] ?? null);

        $order = Order::createWithUniqueNumber($validated);

        if ($amountPaid > 0) {
            OrderPayment::create([
                'order_id' => $order->id,
                'amount' => $amountPaid,
                'method' => 'cash',
                'notes' => 'Anticipo registrado al recibir el equipo',
                'paid_at' => now(),
                'registered_by' => Auth::id(),
            ]);
        }

        if (!empty($validated['accessories'])) {
            $order->accessories()->attach($validated['accessories']);
        }

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('order_photos', 'local');
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

        $appointment?->update(['order_id' => $order->id]);

        AssignmentNotification::notify($order->user_id, 'Nueva orden asignada',
            "Orden {$order->order_number}: {$order->declared_fault}", route('orders.show', $order));

        return redirect()->route('orders.show', $order)
            ->with('success', 'Orden #' . $order->order_number . ' creada exitosamente.');
    }

    public function show(Order $order)
    {
        $this->authorizeOrder($order);

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
            'whatsappLogs.sentBy',
            'parts.sparePart',
            'warranty',
            'signatures',
            'parentOrder',
            'childOrders',
            'appointments.user',
            'workNotes.user',
        ]);

        return view('orders.show', [
            'checklists' => collect(Checklist::stages())->map(fn ($label, $stage) => $order->checklistProgress($stage))->filter(),
            'activity' => Gate::allows('manage-users')
                ? AuditLog::with('user')->where('order_id', $order->id)->orderByDesc('id')->take(30)->get()
                : collect(),
            'order' => $order,
            'statuses' => Order::statuses(),
        ]);
    }

    public function pdf(Order $order)
    {
        $this->authorizeOrder($order);

        $order->load([
            'client',
            'brand',
            'deviceModel',
            'user',
            'payments',
            'statusHistory.changedBy',
            'accessories',
            'warranty',
            'signatures',
            'parentOrder',
            'parts.sparePart',
        ]);

        $receptionChecklist = Checklist::for($order->device_type, 'reception');
        $receptionResults = $receptionChecklist
            ? $order->checklistResults()->with('item')
                ->whereIn('checklist_item_id', $receptionChecklist->items()->pluck('id'))
                ->where('result', '!=', 'pending')->get()
            : collect();

        // Las firmas están en almacenamiento privado: se insertan en el PDF como data URI.
        $signatureImages = $order->signatures->mapWithKeys(function ($signature) {
            $disk = Storage::disk('local');

            return $disk->exists($signature->signature_path)
                ? [$signature->stage => 'data:image/png;base64,' . base64_encode($disk->get($signature->signature_path))]
                : [];
        });

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('orders.pdf', [
            'order' => $order,
            'statuses' => Order::statuses(),
            'signatureImages' => $signatureImages,
            'receptionResults' => $receptionResults,
        ]);

        return $pdf->download('orden-' . $order->order_number . '.pdf');
    }

    /**
     * Ticket de 80 mm para impresora térmica (se imprime al abrir).
     */
    public function ticket(Order $order)
    {
        $this->authorizeOrder($order);
        $order->load(['client', 'brand', 'deviceModel', 'accessories']);

        return view('orders.ticket', [
            'order' => $order,
            'company' => [
                'name' => Setting::get('company_name', 'SolarFix'),
                'phone' => Setting::get('company_phone'),
                'address' => Setting::get('company_address'),
                'terms' => Setting::get('ticket_terms'),
            ],
        ]);
    }

    public function edit(Order $order)
    {
        $this->authorizeOrder($order);

        return view('orders.edit', ['order' => $order->load('accessories')] + $this->formData($order));
    }

    public function update(Request $request, Order $order)
    {
        $this->authorizeOrder($order);

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
            'warranty_days' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(array_keys(Order::statuses()))],
            'notes' => ['nullable', 'string'],
            'user_id' => ['nullable', Rule::in($this->assignableTechnicians($order)->pluck('id'))],
        ]);

        // Control de calidad obligatorio (opcional en Configuración) para pasar a "Listo".
        if ($error = app(OrderWorkflow::class)->qualityCheckError($order, $validated['status'] ?? null)) {
            return back()->withInput()->withErrors(['status' => $error]);
        }

        // Los técnicos no pueden reasignar órdenes.
        if (Auth::user()->isTechnician()) {
            unset($validated['user_id']);
        }

        // Si la orden tiene repuestos del inventario, su costo sale de ellos y no del formulario.
        if ($order->parts()->exists()) {
            $validated['parts_cost'] = round((float) $order->parts()->sum('subtotal'), 2);
        }

        $diagnosisCost = (float) ($validated['diagnosis_cost'] ?? 0);
        $laborCost = (float) ($validated['labor_cost'] ?? 0);
        $partsCost = (float) ($validated['parts_cost'] ?? 0);
        $surchargePercent = (float) ($validated['surcharge_percent'] ?? 0);
        $subtotal = $diagnosisCost + $laborCost + $partsCost;
        $surchargeAmount = $subtotal * ($surchargePercent / 100);
        $validated['surcharge_amount'] = round($surchargeAmount, 2);
        $validated['total_amount'] = round($subtotal + $surchargeAmount, 2);
        $amountPaid = (float) $order->payments()->sum('amount');
        $validated['amount_paid'] = round($amountPaid, 2);
        $validated['balance_due'] = round(max(0, $validated['total_amount'] - $amountPaid), 2);

        $oldStatus = $order->status;
        $oldTechnician = $order->user_id;
        $order->update($validated);

        if ($order->user_id && (int) $order->user_id !== (int) $oldTechnician) {
            AssignmentNotification::notify($order->user_id, 'Orden asignada',
                "Te asignaron la orden {$order->order_number}", route('orders.show', $order));
        }

        if (isset($validated['accessories'])) {
            $order->accessories()->sync($validated['accessories']);
        }

        // Historial, repuestos, garantía y comisión según el nuevo estado.
        app(OrderWorkflow::class)->afterUpdate($order, $oldStatus, Auth::id());

        return redirect()->route('orders.show', $order)
            ->with('success', 'Orden #' . $order->order_number . ' actualizada.');
    }

    public function destroy(Order $order)
    {
        if (! in_array($order->status, ['delivered', 'closed_no_repair'])) {
            app(InventoryService::class)->releaseOrderParts($order, 'Devuelto: anulada la orden', Auth::id());
        }
        $order->delete();
        Commission::syncFor($order);

        return redirect()->route('orders.index')
            ->with('success', 'Orden eliminada.');
    }

    public function uploadPhoto(Request $request, Order $order)
    {
        $this->authorizeOrder($order);

        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240'],
            'stage' => ['nullable', Rule::in(['reception', 'process', 'delivery'])],
            'caption' => ['nullable', 'string', 'max:200'],
        ]);

        $path = $request->file('photo')->store('order_photos', 'local');

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

    public function showPhoto(Order $order, OrderPhoto $photo)
    {
        $this->authorizeOrder($order);

        if ($photo->order_id !== $order->id) {
            abort(404);
        }

        $disk = Storage::disk('local')->exists($photo->file_path) ? Storage::disk('local') : Storage::disk('public');
        abort_unless($disk->exists($photo->file_path), 404);

        return $disk->response($photo->file_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function deletePhoto(Order $order, OrderPhoto $photo)
    {
        $this->authorizeOrder($order);

        if ($photo->order_id !== $order->id) {
            abort(404);
        }

        // Las fotos anteriores a la migración a almacenamiento privado pueden seguir en el disco público.
        Storage::disk('local')->delete($photo->file_path);
        Storage::disk('public')->delete($photo->file_path);
        $photo->delete();

        return redirect()->route('orders.show', $order)
            ->with('success', 'Foto eliminada.');
    }

    /**
     * Datos compartidos por los formularios de crear y editar orden.
     */
    private function formData(?Order $order = null): array
    {
        $brands = DeviceBrand::where('is_active', true)->orderBy('name')->get();

        return [
            'technicians' => $this->assignableTechnicians($order),
            'branches' => BranchContext::seesAllBranches() && ! $order
                ? Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code'])
                : collect(),
            'brands' => $brands,
            'brandsData' => $brands->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'device_types' => $b->device_types ?? [],
            ]),
            'accessories' => Accessory::where('is_active', true)->orderBy('name')->get(),
            'statuses' => Order::statuses(),
            'deviceTypes' => Order::deviceTypes(),
            'unlockTypes' => Order::unlockTypes(),
            'modelsByBrand' => DeviceModel::where('is_active', true)
                ->orderBy('name')
                ->get()
                ->groupBy('brand_id')
                ->map(fn ($models) => $models->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'device_type' => $m->device_type])),
            'deviceTypesData' => collect(Order::deviceTypes())->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
        ];
    }

    /**
     * Técnicos activos que se pueden asignar, más el asignado actual de la orden (si lo hay).
     */
    private function assignableTechnicians(?Order $order = null)
    {
        // Técnicos de la sucursal de la orden (o de la sucursal activa al crear); con "todas", de cualquiera.
        $branch = $order?->branch_id ?? BranchContext::current();

        return User::with('branch:id,code')->where(function ($q) use ($order, $branch) {
            $q->where('role', 'technician')->where('is_active', true)
                ->when($branch, fn ($q) => $q->where('branch_id', $branch));
            if ($order?->user_id) {
                $q->orWhere('id', $order->user_id);
            }
        })->orderBy('name')->get(['id', 'name', 'branch_id']);
    }

    /**
     * Cita abierta y sin orden, visible para el usuario (un técnico solo las suyas).
     */
    private function linkableAppointment($id): Appointment
    {
        $appointment = Appointment::with('client')->findOrFail($id);
        abort_if(Auth::user()->isTechnician() && $appointment->user_id !== Auth::id(), 403, 'Esta cita está asignada a otro técnico.');
        abort_if($appointment->order_id || ! $appointment->isOpen(), 422, 'La cita ya tiene una orden o está cerrada.');

        return $appointment;
    }

    private function authorizeOrder(Order $order): void
    {
        abort_unless($order->isVisibleTo(Auth::user()), 403, 'Esta orden está asignada a otro técnico.');
    }
}
