<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use App\Models\Setting;
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

        $ordersQuery = Order::visibleTo($user);

        $stats['orders_total'] = (clone $ordersQuery)->count();
        $stats['orders_pending'] = (clone $ordersQuery)->whereNotIn('status', ['delivered', 'closed_no_repair'])->count();
        $stats['orders_ready'] = (clone $ordersQuery)->where('status', 'ready')->count();
        $stats['orders_delivered'] = (clone $ordersQuery)->where('status', 'delivered')->count();

        $stats['orders_today'] = (clone $ordersQuery)->whereDate('created_at', today())->count();
        $stats['delivered_today'] = OrderStatusHistory::where('to_status', 'delivered')
            ->whereDate('changed_at', today())
            ->whereIn('order_id', (clone $ordersQuery)->select('id'))
            ->distinct()
            ->count('order_id');

        // Alertas: equipos sin movimiento y presupuestos sin respuesta.
        $staleDays = (int) Setting::get('stale_order_days', 5);
        $staleOrders = (clone $ordersQuery)
            ->with('client')
            ->whereNotIn('status', ['delivered', 'closed_no_repair', 'waiting_approval'])
            ->where('updated_at', '<', now()->subDays($staleDays))
            ->orderBy('updated_at')
            ->take(10)
            ->get();

        $approvalHours = (int) Setting::get('approval_alert_hours', 72);
        $awaitingApproval = (clone $ordersQuery)
            ->with(['client', 'statusHistory'])
            ->where('status', 'waiting_approval')
            ->get()
            ->map(function ($order) {
                $order->waiting_since = $order->statusHistory->where('to_status', 'waiting_approval')->max('changed_at')
                    ?? $order->updated_at;

                return $order;
            })
            ->filter(fn ($order) => $order->waiting_since->lt(now()->subHours($approvalHours)))
            ->sortBy('waiting_since')
            ->take(10);

        $recentOrders = (clone $ordersQuery)
            ->with(['client', 'brand', 'deviceModel'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('dashboard', [
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'staleOrders' => $staleOrders,
            'staleDays' => $staleDays,
            'awaitingApproval' => $awaitingApproval,
            'approvalHours' => $approvalHours,
            'statuses' => Order::statuses(),
        ]);
    }
}
