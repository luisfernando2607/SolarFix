<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%' . $request->q . '%';
                $query->where(fn ($q) => $q->where('name', 'like', $search)
                    ->orWhere('id_document', 'like', $search)
                    ->orWhere('phone', 'like', $search)
                    ->orWhere('email', 'like', $search));
            })
            ->orderBy('name')
            ->paginate(10);
        return view('clients.index', compact('clients'));
    }

    /**
     * Búsqueda para el autocompletado del formulario de órdenes.
     */
    public function search(Request $request)
    {
        $request->validate(['q' => ['required', 'string', 'max:100']]);
        $search = '%' . $request->q . '%';

        return Client::where(fn ($q) => $q->where('name', 'like', $search)
                ->orWhere('id_document', 'like', $search)
                ->orWhere('phone', 'like', $search))
            ->orderBy('name')
            ->take(15)
            ->get(['id', 'name', 'id_document', 'phone', 'email', 'address'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'label' => $c->name . ($c->id_document ? ' · ' . $c->id_document : ''),
                'id_document' => $c->id_document,
                'phone' => $c->phone,
                'email' => $c->email,
                'address' => $c->address,
            ]);
    }

    public function create()
    {
        return view('clients.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:180'],
            'address' => ['nullable', 'string', 'max:255'],
            'id_document' => ['nullable', 'string', 'max:20'],
            'client_type' => ['required', Rule::in(['individual', 'business', 'frequent'])],
            'notes' => ['nullable', 'string'],
        ]);

        Client::create($validated);

        return redirect()->route('clients.index')
            ->with('success', 'Cliente creado exitosamente.');
    }

    public function show(Client $client)
    {
        $orders = $client->orders()->with(['brand', 'deviceModel', 'warranty'])->latest('id')->get();

        return view('clients.show', [
            'client' => $client,
            'orders' => $orders->take(30),
            'stats' => [
                'orders' => $orders->count(),
                'paid' => round((float) OrderPayment::whereIn('order_id', $orders->pluck('id'))->sum('amount'), 2),
                'balance' => round((float) $orders->whereNotIn('status', ['closed_no_repair'])->sum('balance_due'), 2),
            ],
            'warranties' => $orders->pluck('warranty')->filter()->filter->isValid(),
            'appointments' => $client->appointments()->with('user')
                ->where('scheduled_at', '>=', today())->whereIn('status', ['pending', 'confirmed'])
                ->orderBy('scheduled_at')->get(),
            'statuses' => Order::statuses(),
            'plans' => $client->maintenancePlans()->where('is_active', true)->orderBy('next_due_date')->get(),
        ]);
    }

    public function edit(Client $client)
    {
        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:180'],
            'address' => ['nullable', 'string', 'max:255'],
            'id_document' => ['nullable', 'string', 'max:20'],
            'client_type' => ['required', Rule::in(['individual', 'business', 'frequent'])],
            'notes' => ['nullable', 'string'],
        ]);

        $client->update($validated);

        return redirect()->route('clients.index')
            ->with('success', 'Cliente actualizado exitosamente.');
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()->route('clients.index')
            ->with('success', 'Cliente eliminado exitosamente.');
    }
}
