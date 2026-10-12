<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $status, array $attributes = []): Order
    {
        $client = Client::create(['name' => 'Cliente ' . uniqid(), 'phone' => '0991234567', 'client_type' => 'individual']);

        return Order::create($attributes + [
            'client_id' => $client->id, 'order_number' => 'T-' . uniqid(), 'device_type' => 'celular', 'declared_fault' => 'x',
            'unlock_type' => 'none', 'status' => $status, 'entry_date' => today(), 'total_amount' => 50, 'balance_due' => 50,
        ]);
    }

    public function test_counter_panel_shows_oldest_first_with_full_count(): void
    {
        $old = $this->makeOrder('ready');
        OrderStatusHistory::create(['order_id' => $old->id, 'from_status' => 'repairing', 'to_status' => 'ready', 'changed_at' => now()->subDays(9)]);
        $new = $this->makeOrder('ready');
        OrderStatusHistory::create(['order_id' => $new->id, 'from_status' => 'repairing', 'to_status' => 'ready', 'changed_at' => now()->subHour()]);

        $this->actingAs(User::factory()->role('receptionist')->create())->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('counter', fn ($counter) => $counter['ready_count'] === 2
                && $counter['ready']->pluck('id')->all() === [$old->id, $new->id]
                && $counter['ready']->first()->status_since->lt(now()->subDays(8)));
    }

    public function test_dashboard_query_count_does_not_grow_with_orders(): void
    {
        $admin = User::factory()->role('admin')->create();
        $count = function () use ($admin) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin)->get(route('dashboard'))->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();
            \Illuminate\Support\Facades\Cache::flush();

            return $queries;
        };

        foreach (['ready', 'waiting_approval', 'repairing'] as $status) {
            $this->makeOrder($status);
        }
        $few = $count();

        for ($i = 0; $i < 15; $i++) {
            $order = $this->makeOrder($i % 2 ? 'ready' : 'waiting_approval');
            OrderPayment::create(['order_id' => $order->id, 'amount' => 10, 'method' => 'cash', 'paid_at' => now()]);
        }

        $this->assertLessThanOrEqual($few + 2, $count());
    }

    public function test_invoice_form_searches_clients_instead_of_listing_them(): void
    {
        Client::create(['name' => 'Cliente Escondido', 'phone' => '0990000000', 'client_type' => 'individual']);

        $this->actingAs(User::factory()->role('receptionist')->create())->get(route('invoices.create'))
            ->assertOk()
            ->assertSee('invoiceClientSearch')
            ->assertDontSee('Cliente Escondido');
    }

    public function test_receptionist_has_no_commissions_screen(): void
    {
        $this->actingAs(User::factory()->role('receptionist')->create())->get(route('commissions.index'))->assertForbidden();
        $this->actingAs(User::factory()->role('technician')->create())->get(route('commissions.index'))->assertOk();
    }

    public function test_reports_aggregate_correctly(): void
    {
        $order = $this->makeOrder('delivered', ['delivery_date' => today(), 'entry_date' => today()->subDays(2)]);
        OrderStatusHistory::create(['order_id' => $order->id, 'from_status' => 'ready', 'to_status' => 'delivered', 'changed_at' => now()]);
        OrderPayment::create(['order_id' => $order->id, 'amount' => 30, 'method' => 'cash', 'paid_at' => now()]);
        OrderPayment::create(['order_id' => $order->id, 'amount' => 20, 'method' => 'transfer', 'paid_at' => now()]);

        $this->actingAs(User::factory()->role('admin')->create())->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('kpis', fn ($k) => $k['revenue'] == 50 && $k['received'] === 1 && $k['delivered'] === 1 && $k['avg_days'] == 2)
            ->assertViewHas('byMethod', fn ($rows) => $rows->firstWhere('label', 'Efectivo')['amount'] == 30)
            ->assertViewHas('monthly', fn ($months) => $months->last()['amount'] == 50);
    }
}
