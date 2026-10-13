<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use App\Models\SparePart;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessInsightsTest extends TestCase
{
    use RefreshDatabase;

    private function part(string $name, int $stock, float $cost, float $price, int $min = 2): SparePart
    {
        $part = SparePart::create(['name' => $name, 'code' => strtoupper(substr($name, 0, 3)) . '-' . uniqid(), 'category' => 'X', 'cost_price' => $cost, 'sale_price' => $price, 'min_stock' => $min]);
        $part->setBranchStock(Branch::mainId(), $stock);
        $part->update(['stock_quantity' => $stock]);

        return $part;
    }

    private function order(array $attributes = []): Order
    {
        $client = Client::create(['name' => 'Ana', 'phone' => '0991234567', 'client_type' => 'individual']);
        $order = Order::create($attributes + [
            'client_id' => $client->id, 'order_number' => 'T-' . uniqid(), 'device_type' => 'celular', 'declared_fault' => 'x',
            'unlock_type' => 'none', 'status' => 'repairing', 'entry_date' => today(), 'labor_cost' => 40,
        ]);
        $order->recalculateTotals();

        return $order;
    }

    public function test_profit_report_combines_orders_sales_and_expenses(): void
    {
        $admin = User::factory()->role('admin')->create();
        $tech = User::factory()->role('technician')->create(['commission_type' => 'fixed', 'commission_value' => 5]);
        $screen = $this->part('Pantalla', 5, 20, 50);
        $case = $this->part('Funda', 10, 3, 8);

        $order = $this->order(['user_id' => $tech->id]);
        app(InventoryService::class)->addToOrder($order, $screen, 1, null);   // total 40 + 50 = 90, costo 20
        $order->fresh()->update(['status' => 'ready']);
        $order->fresh()->markDelivered(null);                                  // comisión 5
        app(InventoryService::class)->sell(Branch::mainId(), [['spare_part_id' => $case->id, 'quantity' => 2]], ['method' => 'cash'], null); // 16, costo 6
        CashMovement::create(['branch_id' => Branch::mainId(), 'type' => 'expense', 'amount' => 10, 'description' => 'Taxi', 'movement_date' => today()]);

        $this->actingAs($admin)->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Utilidad estimada del período')
            ->assertViewHas('profit', fn ($p) => $p['orders_revenue'] == 90 && $p['parts_cost'] == 20 && $p['commissions'] == 5
                && $p['sales_revenue'] == 16 && $p['sales_cost'] == 6 && $p['expenses'] == 10 && $p['profit'] == 65);
    }

    public function test_purchase_suggestion_uses_consumption_and_last_supplier(): void
    {
        $admin = User::factory()->role('admin')->create();
        $supplier = Supplier::create(['name' => 'Repuestos Andinos', 'is_active' => true]);
        $fast = $this->part('Batería', 0, 10, 25, 2);
        app(InventoryService::class)->receive($fast, 12, 9.5, null, null, $admin->id, $supplier->id);
        app(InventoryService::class)->sell(Branch::mainId(), [['spare_part_id' => $fast->id, 'quantity' => 9]], ['method' => 'cash'], null);
        $this->part('Tornillo', 50, 0.1, 0.5, 5);                               // stock de sobra: no aparece

        $this->actingAs($admin)->get(route('spare-parts.reorder'))
            ->assertOk()
            ->assertSee('Repuestos Andinos')
            ->assertDontSee('Tornillo')
            ->assertViewHas('groups', function ($groups) {
                $row = $groups['Repuestos Andinos']->first();

                // stock 3; consumo 9 en 90 días = 3/mes; pedir = mínimo 2 + 3 − 3 = 2
                return $row['stock'] === 3 && $row['monthly'] == 3.0 && $row['quantity'] === 2 && $row['unit_cost'] == 9.5;
            });

        $this->actingAs($admin)->get(route('spare-parts.reorder', ['export' => 'csv']))->assertOk();
        $this->actingAs(User::factory()->role('technician')->create())->get(route('spare-parts.reorder'))->assertForbidden();
    }

    public function test_dashboard_shows_online_requests_and_credits(): void
    {
        $desk = User::factory()->role('receptionist')->create();
        $client = Client::create(['name' => 'Luis Mora', 'phone' => '0990000000', 'client_type' => 'individual']);
        Appointment::create(['branch_id' => $desk->branch_id, 'client_id' => $client->id, 'type' => 'home_visit', 'title' => 'Solicitud en línea · Visita',
            'scheduled_at' => now()->addDay(), 'duration_min' => 60, 'status' => 'pending', 'requested_online' => true]);
        $order = $this->order(['branch_id' => $desk->branch_id]);
        OrderPayment::register($order, 40, 'cash', null);
        $order->fresh()->update(['labor_cost' => 30]);
        $order->fresh()->recalculateTotals();

        $this->actingAs($desk)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Citas pedidas en línea por confirmar')
            ->assertSee('Luis Mora')
            ->assertSee('Saldos a favor por devolver')
            ->assertSee($order->order_number);
    }
}
