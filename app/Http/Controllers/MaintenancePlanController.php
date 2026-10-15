<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\MaintenancePlan;
use App\Models\WhatsappLog;
use App\Services\WhatsAppMessage;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MaintenancePlanController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['filter' => ['nullable', 'in:due,all,inactive']]);
        $filter = $request->input('filter', 'due');

        $plans = BranchContext::apply(MaintenancePlan::query())
            ->with(['client', 'appointments' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed'])])
            ->when($filter === 'due', fn ($q) => $q->due())
            ->when($filter === 'all', fn ($q) => $q->where('is_active', true))
            ->when($filter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%' . $request->q . '%';
                $query->where(fn ($q) => $q->where('title', 'like', $search)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $search)->orWhere('phone', 'like', $search)));
            })
            ->orderBy('next_due_date')
            ->paginate(20)
            ->withQueryString();

        return view('maintenance_plans.index', ['plans' => $plans, 'filter' => $filter]);
    }

    public function create(Request $request)
    {
        $plan = new MaintenancePlan(['interval_months' => 6, 'next_due_date' => today()->addMonths(6), 'is_active' => true]);

        if ($request->filled('client_id') && $client = Client::find($request->client_id)) {
            $plan->fill(['client_id' => $client->id, 'address' => $client->address]);
        }

        return view('maintenance_plans.form', ['plan' => $plan]);
    }

    public function store(Request $request)
    {
        $plan = MaintenancePlan::create($this->validated($request));

        return redirect()->route('clients.show', $plan->client_id)->with('success', 'Plan de mantenimiento creado.');
    }

    public function edit(MaintenancePlan $maintenancePlan)
    {
        $this->authorizePlan($maintenancePlan);
        return view('maintenance_plans.form', ['plan' => $maintenancePlan->load('client')]);
    }

    public function update(Request $request, MaintenancePlan $maintenancePlan)
    {
        $this->authorizePlan($maintenancePlan);
        $maintenancePlan->update($this->validated($request));

        return redirect()->route('maintenance-plans.index')->with('success', 'Plan de mantenimiento actualizado.');
    }

    /**
     * Aviso al cliente de que le toca el mantenimiento.
     */
    public function whatsapp(MaintenancePlan $maintenancePlan)
    {
        $this->authorizePlan($maintenancePlan);
        $maintenancePlan->load('client');
        $phone = WhatsAppMessage::normalizePhone($maintenancePlan->client?->phone);
        if (! $phone) {
            return back()->with('error', 'El cliente no tiene un número de teléfono válido para WhatsApp.');
        }

        if (\App\Services\WhatsAppCloud::enabled()) {
            $log = \App\Services\WhatsAppCloud::sendTemplate('maintenance_due', $phone, \App\Services\WhatsAppTemplates::forMaintenance($maintenancePlan),
                ['client_id' => $maintenancePlan->client_id]);

            return $log->status === 'failed'
                ? back()->with('error', 'WhatsApp no se envió: ' . $log->error)
                : back()->with('success', 'Mensaje de WhatsApp enviado a ' . $phone . '.');
        }

        $message = WhatsAppMessage::buildMaintenanceDue($maintenancePlan);

        WhatsappLog::create([
            'client_id' => $maintenancePlan->client_id,
            'trigger_event' => 'maintenance_due',
            'phone_to' => $phone,
            'message_body' => $message,
            'sent_by' => Auth::id(),
            'sent_at' => now(),
        ]);

        return redirect()->away(WhatsAppMessage::url($phone, $message));
    }

    private function authorizePlan(MaintenancePlan $plan): void
    {
        abort_unless(BranchContext::allows($plan->branch_id), 403, 'El plan es de otra sucursal.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'interval_months' => ['required', 'integer', 'min:1', 'max:36'],
            'next_due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ], [], ['next_due_date' => 'próximo mantenimiento']);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
