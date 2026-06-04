<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $stats = [];

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            $stats['users_count'] = User::count();
            $stats['revenue'] = OrderPayment::sum('amount');
        }

        $ordersQuery = Order::query();
        if ($user->isTechnician()) {
            $ordersQuery->where('user_id', $user->id);
        }

        $stats['orders_total'] = (clone $ordersQuery)->count();
        $stats['orders_pending'] = (clone $ordersQuery)->whereNotIn('status', ['delivered', 'closed_no_repair'])->count();
        $stats['orders_ready'] = (clone $ordersQuery)->where('status', 'ready')->count();
        $stats['orders_delivered'] = (clone $ordersQuery)->where('status', 'delivered')->count();

        $recentOrders = (clone $ordersQuery)
            ->with(['client', 'brand', 'deviceModel'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('dashboard', [
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'statuses' => Order::statuses(),
        ]);
    }
}
