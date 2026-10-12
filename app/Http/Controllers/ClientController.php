<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        // Quien no gestiona clientes (técnicos) busca con al menos 2 caracteres, recibe menos
        // resultados y sin correo ni dirección: lo justo para elegir al cliente de la orden.
        $full = Gate::allows('manage-clients');
        $request->validate(['q' => ['required', 'string', 'min:' . ($full ? 1 : 2), 'max:100']]);
        $search = '%' . $request->q . '%';

        return Client::where(fn ($q) => $q->where('name', 'like', $search)
                ->orWhere('id_document', 'like', $search)
                ->orWhere('phone', 'like', $search))
            ->orderBy('name')
            ->take($full ? 15 : 5)
            ->get(['id', 'name', 'id_document', 'phone', 'email', 'address'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'label' => $c->name . ($c->id_document ? ' · ' . $c->id_document : ''),
                'id_document' => $c->id_document,
                'phone' => $c->phone,
                'email' => $full ? $c->email : null,
                'address' => $full ? $c->address : null,
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
        // Totales en la base; en pantalla, las 30 órdenes más recientes.
        $orders = $client->orders()->with(['brand', 'deviceModel'])->latest('id')->take(30)->get();
        $sales = $client->sales()->active()->with('items')->latest('sold_at')->take(10)->get();

        return view('clients.show', [
            'client' => $client,
            'orders' => $orders,
            'sales' => $sales,
            'stats' => [
                'orders' => $client->orders()->count(),
                'paid' => round((float) OrderPayment::whereIn('order_id', $client->orders()->select('id'))->sum('amount')
                    + (float) $client->sales()->active()->sum('total'), 2),
                'balance' => round((float) $client->orders()->where('status', '!=', 'closed_no_repair')->sum('balance_due'), 2),
            ],
            'warranties' => \App\Models\Warranty::with('order:id,order_number')
                ->whereIn('order_id', $client->orders()->select('id'))
                ->where('end_date', '>=', today())->where('status', '!=', 'claimed')
                ->orderBy('end_date')->get(),
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
