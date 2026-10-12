<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Commission;
use App\Models\MaintenancePlan;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use App\Models\Setting;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Warranty;
use App\Services\BusinessMetrics;
use App\Support\BranchContext;
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

        $expiringWarranties = collect();
        if (! $user->isTechnician()) {
            $expiringWarranties = Warranty::with('order.client')
                ->filterStatus('expiring')
                ->orderBy('end_date')
                ->take(10)
                ->get();
        }

        $lowStockParts = $user->can('receive-inventory')
            ? SparePart::with('stocks.branch:id,code')->lowStock(BranchContext::current())->orderBy('name')->take(10)->get()
            : collect();

        $todayAppointments = Appointment::with(['client', 'user'])
            ->visibleTo($user)
            ->whereDate('scheduled_at', today())
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('scheduled_at')
            ->get();

        $duePlans = $user->can('manage-appointments') ? MaintenancePlan::due()->count() : 0;

        $unassignedAppointments = $user->can('manage-appointments')
            ? Appointment::whereNull('user_id')->whereIn('status', ['pending', 'confirmed'])->where('scheduled_at', '>=', now())->count()
            : 0;

        $myWork = $user->isTechnician() ? $this->technicianQueue($user) : null;
        $counter = $user->can('manage-invoices') ? $this->counterPanel() : null;
        $metrics = $user->can('manage-commissions') ? app(BusinessMetrics::class)->forMonth(now()) : null;

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
            'expiringWarranties' => $expiringWarranties,
            'lowStockParts' => $lowStockParts,
            'myWork' => $myWork,
            'counter' => $counter,
            'metrics' => $metrics,
            'todayAppointments' => $todayAppointments,
            'unassignedAppointments' => $unassignedAppointments,
            'duePlans' => $duePlans,
            'warrantyAlertDays' => Warranty::alertDays(),
            'statuses' => Order::statuses(),
        ]);
    }

    /**
     * Mostrador (recepción/administración): equipos para entregar, presupuestos por
     * confirmar y lo recibido hoy.
     */
    private function counterPanel(): array
    {
        $with = ['client', 'brand', 'deviceModel', 'statusHistory'];
        $since = fn ($order, string $status) => $order->statusHistory->where('to_status', $status)->max('changed_at') ?? $order->updated_at;

        return [
            'ready' => Order::visibleTo(Auth::user())->with($with)->where('status', 'ready')->get()
                ->each(fn ($o) => $o->status_since = $since($o, 'ready'))
                ->sortBy('status_since')->values(),
            'waiting' => Order::visibleTo(Auth::user())->with($with)->where('status', 'waiting_approval')->get()
                ->each(fn ($o) => $o->status_since = $since($o, 'waiting_approval'))
                ->sortBy('status_since')->values(),
            'today' => Order::visibleTo(Auth::user())->with(['client', 'brand', 'deviceModel', 'user'])->whereDate('created_at', today())->latest('id')->get(),
        ];
    }

    /**
     * Cola del técnico: sus órdenes abiertas por etapa (la más antigua primero),
     * las que puede tomar y su comisión del mes.
     */
    private function technicianQueue(User $user): array
    {
        $stages = [
            'Por diagnosticar' => ['received', 'diagnosing', 'warranty'],
            'Esperando aprobación' => ['waiting_approval'],
            'En reparación' => ['repairing'],
            'Listas para entregar' => ['ready'],
        ];

        $orders = Order::with(['client', 'brand', 'deviceModel'])
            ->where('user_id', $user->id)
            ->whereIn('status', array_merge(...array_values($stages)))
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        return [
            'stages' => collect($stages)->map(fn ($statuses) => $orders->whereIn('status', $statuses)->values()),
            'available' => Order::visibleTo($user)->with(['client', 'brand', 'deviceModel'])
                ->whereNull('user_id')
                ->whereNotIn('status', ['delivered', 'closed_no_repair'])
                ->orderBy('entry_date')
                ->take(10)
                ->get(),
            'commission' => round((float) Commission::where('user_id', $user->id)
                ->where('period', now()->format('Y-m'))->sum('commission_amount'), 2),
        ];
    }
}
