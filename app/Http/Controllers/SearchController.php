<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Búsqueda global (barra superior): órdenes y clientes, respetando lo que cada rol puede ver.
 */
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));
        $user = Auth::user();

        if (mb_strlen($q) < 2) {
            return view('search.index', ['q' => $q, 'orders' => collect(), 'clients' => collect()]);
        }

        // Número de orden exacto: directo a la orden.
        $exact = Order::visibleTo($user)->where('order_number', $q)->first();
        if ($exact) {
            return redirect()->route('orders.show', $exact);
        }

        $like = '%' . $q . '%';
        $orders = Order::visibleTo($user)
            ->with(['client', 'brand', 'deviceModel', 'user'])
            ->where(fn ($query) => $query->where('order_number', 'like', $like)
                ->orWhere('serial_imei', 'like', $like)
                ->orWhere('brand_text', 'like', $like)
                ->orWhere('model_text', 'like', $like)
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('id_document', 'like', $like)))
            ->orderByRaw("CASE WHEN status IN ('delivered', 'closed_no_repair') THEN 1 ELSE 0 END")
            ->latest('id')
            ->take(25)
            ->get();

        $clients = $user->can('manage-clients')
            ? Client::where(fn ($query) => $query->where('name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('id_document', 'like', $like)
                ->orWhere('email', 'like', $like))
                ->withCount('orders')
                ->orderBy('name')
                ->take(10)
                ->get()
            : collect();

        return view('search.index', ['q' => $q, 'orders' => $orders, 'clients' => $clients, 'statuses' => Order::statuses()]);
    }
}
