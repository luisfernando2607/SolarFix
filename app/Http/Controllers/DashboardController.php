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
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $stats = [];

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            $stats['users_count'] = User::count();
            $stats['revenue'] = (float) BranchContext::applyThroughOrder(OrderPayment::query())->sum('amount')
                + (float) BranchContext::apply(\App\Models\Sale::active())->sum('total');
        }

        $ordersQuery = Order::visibleTo($user);

        $stats['orders_total'] = (clone $ordersQuery)->count();
        $stats['orders_pending'] = (clone $ordersQuery)->whereNotIn('status', ['delivered', 'closed_no_repair'])->count();
        $stats['orders_ready'] = (clone $ordersQuery)->where('status', 'ready')->count();
        $stats['orders_delivered'] = (clone $ordersQuery)->where('status', 'delivered')->count();

        $today = [today(), today()->endOfDay()];
        $stats['orders_today'] = (clone $ordersQuery)->whereBetween('created_at', $today)->count();
        $stats['delivered_today'] = OrderStatusHistory::where('to_status', 'delivered')
            ->whereBetween('changed_at', $today)
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
        // Las que entraron a "esperando aprobación" antes del límite (filtrado y orden en SQL).
        $approvalLimit = now()->subHours($approvalHours);
        $awaitingApproval = (clone $ordersQuery)
            ->with('client')
            ->withStatusSince('waiting_approval')
            ->where('status', 'waiting_approval')
            ->whereDoesntHave('statusHistory', fn ($q) => $q->where('to_status', 'waiting_approval')->where('changed_at', '>=', $approvalLimit))
            // Sin historial (datos antiguos) se usa la última modificación.
            ->where(fn ($q) => $q->whereHas('statusHistory', fn ($h) => $h->where('to_status', 'waiting_approval'))
                ->orWhere('updated_at', '<', $approvalLimit))
            ->orderBy('status_since')
            ->take(10)
            ->get()
            ->each(fn ($order) => $order->waiting_since = $order->status_since ?? $order->updated_at);

        $expiringWarranties = collect();
        if (! $user->isTechnician()) {
            $expiringWarranties = BranchContext::applyThroughOrder(Warranty::with('order.client'))
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
            ->whereBetween('scheduled_at', $today)
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('scheduled_at')
            ->get();

        $duePlans = $user->can('manage-appointments') ? BranchContext::apply(MaintenancePlan::due())->count() : 0;

        $unassignedAppointments = $user->can('manage-appointments')
            ? Appointment::visibleTo($user)->whereNull('user_id')->whereIn('status', ['pending', 'confirmed'])->where('scheduled_at', '>=', now())->count()
            : 0;

        // Solicitudes de cita hechas por clientes en la página pública, aún sin confirmar.
        $onlineRequests = $user->can('manage-appointments')
            ? Appointment::with('client')->visibleTo($user)->where('requested_online', true)->where('status', 'pending')
                ->where('scheduled_at', '>=', today())->orderBy('scheduled_at')->take(10)->get()
            : collect();

        // Órdenes con dinero cobrado de más: hay que devolverlo al cliente.
        $creditOrders = $user->can('register-payments')
            ? Order::visibleTo($user)->with('client')->whereColumn('amount_paid', '>', 'total_amount')->orderBy('updated_at')->take(10)->get()
            : collect();

        $myWork = $user->isTechnician() ? $this->technicianQueue($user) : null;
        $counter = $user->can('manage-invoices') ? $this->counterPanel() : null;
        // Indicadores del mes: se recalculan cada 5 minutos (son varias consultas sobre todo el historial).
        $metrics = $user->can('manage-commissions')
            ? Cache::remember('metrics:' . now()->format('Y-m') . ':' . (BranchContext::current() ?? 'all'), now()->addMinutes(5),
                fn () => app(BusinessMetrics::class)->forMonth(now(), BranchContext::current()))
            : null;

        $recentOrders = (clone $ordersQuery)
            ->with(['client', 'brand', 'deviceModel'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // En producción, avisa a la administración si falta algo indispensable (revisión cada 10 minutos).
        $systemIssues = app()->isProduction() && ($user->isSuperAdmin() || $user->isAdmin())
            && Cache::remember('system-check-failures', now()->addMinutes(10), fn () => \App\Support\SystemCheck::hasFailures());

        return view('dashboard', [
            'systemIssues' => $systemIssues,
            'onlineRequests' => $onlineRequests,
            'creditOrders' => $creditOrders,
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
        $with = ['client', 'brand', 'deviceModel'];
        // Las más antiguas primero; se muestran hasta 50 por columna (el total va aparte).
        $column = fn (string $status) => Order::visibleTo(Auth::user())->where('status', $status);
        $list = fn (string $status) => $column($status)->with($with)->withStatusSince($status)
            ->orderBy('status_since')->orderBy('id')->take(50)->get()
            ->each(fn ($o) => $o->status_since ??= $o->updated_at);

        return [
            'ready' => $list('ready'),
            'ready_count' => $column('ready')->count(),
            'waiting' => $list('waiting_approval'),
            'waiting_count' => $column('waiting_approval')->count(),
            'today' => Order::visibleTo(Auth::user())->with(['client', 'brand', 'deviceModel', 'user'])
                ->whereBetween('created_at', [today(), today()->endOfDay()])->latest('id')->get(),
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
