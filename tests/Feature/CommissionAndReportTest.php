<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionAndReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->role('admin')->create();
    }

    private function technician(string $type = 'percent', float $value = 10): User
    {
        return User::factory()->role('technician')->create(['commission_type' => $type, 'commission_value' => $value]);
    }

    private function makeOrder(User $tech, array $attributes = []): Order
    {
        $client = Client::create(['name' => 'Diego Salazar', 'phone' => '0991234567', 'client_type' => 'individual']);

        return Order::create($attributes + [
            'client_id' => $client->id,
            'user_id' => $tech->id,
            'order_number' => 'TEST-' . uniqid(),
            'device_type' => 'celular',
            'declared_fault' => 'No enciende',
            'unlock_type' => 'none',
            'status' => 'ready',
            'entry_date' => now()->subDays(4)->toDateString(),
            'diagnosis_cost' => 10,
            'labor_cost' => 40,
            'parts_cost' => 50,
            'total_amount' => 100,
            'balance_due' => 100,
        ]);
    }

    private function deliver(Order $order, User $by, array $overrides = []): void
    {
        $this->actingAs($by)->put(route('orders.update', $order), $overrides + [
            'client_id' => $order->client_id,
            'device_type' => 'celular',
            'declared_fault' => 'No enciende',
            'unlock_type' => 'none',
            'entry_date' => $order->entry_date->toDateString(),
            'diagnosis_cost' => 10,
            'labor_cost' => 40,
            'parts_cost' => 50,
            'user_id' => $order->user_id,
        ]);
        // La entrega se registra con la firma (no desde el formulario de edición).
        $order->refresh()->markDelivered($by->id);
    }

    // --- Comisiones ---

    public function test_percent_commission_on_service_only_by_default(): void
    {
        $tech = $this->technician('percent', 10);
        $order = $this->makeOrder($tech);

        $this->deliver($order, $this->admin());

        $commission = Commission::first();
        $this->assertSame($tech->id, $commission->user_id);
        $this->assertEquals(50, $commission->order_total);      // diagnóstico + mano de obra, sin repuestos
        $this->assertEquals(5, $commission->commission_amount);
        $this->assertSame(now()->format('Y-m'), $commission->period);
    }

    public function test_percent_commission_on_total_when_configured(): void
    {
        Setting::put('commission_base', 'total', 'commissions');
        $order = $this->makeOrder($this->technician('percent', 10));

        $this->deliver($order, $this->admin());

        $this->assertEquals(10, Commission::first()->commission_amount);
    }

    public function test_fixed_commission(): void
    {
        $order = $this->makeOrder($this->technician('fixed', 7.5));

        $this->deliver($order, $this->admin());

        $this->assertEquals(7.5, Commission::first()->commission_amount);
    }

    public function test_no_commission_for_unconfigured_technician_or_warranty_order(): void
    {
        $admin = $this->admin();
        $plain = User::factory()->role('technician')->create();
        $this->deliver($this->makeOrder($plain), $admin);

        $tech = $this->technician();
        $original = $this->makeOrder($tech, ['status' => 'delivered']);
        $this->deliver($this->makeOrder($tech, ['parent_order_id' => $original->id]), $admin);

        $this->assertSame(0, Commission::count());
    }

    public function test_commission_follows_cost_corrections_and_keeps_rate(): void
    {
        $tech = $this->technician('percent', 10);
        $order = $this->makeOrder($tech);
        $admin = $this->admin();
        $this->deliver($order, $admin);

        $tech->update(['commission_value' => 50]); // cambio posterior de tarifa
        $this->deliver($order->fresh(), $admin, ['labor_cost' => 90, 'delivery_date' => today()->toDateString()]);

        $commission = Commission::first();
        $this->assertEquals(100, $commission->order_total);
        $this->assertEquals(10, $commission->commission_amount); // 10% (tarifa al entregar), no 50%
    }

    public function test_delivered_order_cannot_be_reopened_and_deleting_removes_commission(): void
    {
        $admin = $this->admin();
        $tech = $this->technician();
        $order = $this->makeOrder($tech);
        $this->deliver($order, $admin);
        $this->assertSame(1, Commission::count());

        $order->refresh();
        $this->actingAs($admin)->put(route('orders.update', $order), [
            'client_id' => $order->client_id, 'device_type' => 'celular', 'declared_fault' => 'No enciende',
            'unlock_type' => 'none', 'entry_date' => $order->entry_date->toDateString(), 'status' => 'repairing',
            'diagnosis_cost' => 10, 'labor_cost' => 40, 'parts_cost' => 50,
        ])->assertSessionHasErrors('status');
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame(1, Commission::count());
        $order->commission->delete();

        $other = $this->makeOrder($tech);
        $this->deliver($other, $admin);
        $this->actingAs($admin)->delete(route('orders.destroy', $other));
        $this->assertSame(0, Commission::count());
    }

    public function test_paid_commission_is_never_changed(): void
    {
        $admin = $this->admin();
        $tech = $this->technician();
        $order = $this->makeOrder($tech);
        $this->deliver($order, $admin);
        $this->actingAs($admin)->post(route('commissions.pay'), ['user_id' => $tech->id, 'period' => now()->format('Y-m')]);

        $this->deliver($order->fresh(), $admin, ['status' => 'repairing']);

        $commission = Commission::first();
        $this->assertTrue($commission->is_paid);
        $this->assertNotNull($commission->paid_at);
        $this->assertEquals(5, $commission->commission_amount);
    }

    public function test_delivery_signature_generates_commission(): void
    {
        $order = $this->makeOrder($this->technician('fixed', 3));
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        \Illuminate\Support\Facades\Storage::fake('local');

        $this->actingAs($this->admin())->post(route('orders.signatures.store', [$order, 'delivery']), ['signature' => $png]);

        $this->assertEquals(3, Commission::first()?->commission_amount);
    }

    public function test_technician_sees_only_own_commissions(): void
    {
        $admin = $this->admin();
        $tech = $this->technician();
        $other = $this->technician();
        $mine = $this->makeOrder($tech);
        $theirs = $this->makeOrder($other);
        $this->deliver($mine, $admin);
        $this->deliver($theirs, $admin);

        $this->actingAs($tech)->get(route('commissions.index', ['user_id' => $other->id]))
            ->assertOk()
            ->assertViewHas('commissions', fn ($c) => $c->pluck('order_id')->all() === [$mine->id]);

        $this->actingAs($tech)->post(route('commissions.pay'), ['user_id' => $tech->id, 'period' => now()->format('Y-m')])
            ->assertForbidden();
    }

    public function test_admin_commission_screen(): void
    {
        $admin = $this->admin();
        $tech = $this->technician();
        $this->deliver($this->makeOrder($tech), $admin);

        $this->actingAs($admin)->get(route('commissions.index'))->assertOk()->assertSee($tech->name)->assertSee('Pagar $5.00');
        $this->actingAs($admin)->get(route('commissions.index', ['period' => '2020-01']))->assertOk()->assertSee('No hay comisiones');
        $this->actingAs(User::factory()->role('receptionist')->create())->get(route('dashboard'))->assertDontSee(route('commissions.index'));
    }

    // --- Reportes ---

    public function test_report_totals_for_period(): void
    {
        $admin = $this->admin();
        $tech = $this->technician('fixed', 2);
        $order = $this->makeOrder($tech);
        OrderPayment::create(['order_id' => $order->id, 'amount' => 60, 'method' => 'cash', 'paid_at' => now()]);
        OrderPayment::create(['order_id' => $order->id, 'amount' => 40, 'method' => 'transfer', 'paid_at' => now()]);
        OrderPayment::create(['order_id' => $order->id, 'amount' => 999, 'method' => 'cash', 'paid_at' => now()->subYears(2)]);
        $this->deliver($order, $admin);

        $response = $this->actingAs($admin)->get(route('reports.index'));

        $response->assertOk()
            ->assertViewHas('kpis', fn ($k) => $k['revenue'] == 100 && $k['delivered'] === 1 && $k['received'] === 1 && $k['avg_days'] == 4)
            ->assertViewHas('byMethod', fn ($m) => $m->firstWhere('label', 'Efectivo')['amount'] == 60)
            ->assertViewHas('byTechnician', fn ($t) => $t->first()['name'] === $tech->name && $t->first()['commission'] == 2)
            ->assertViewHas('monthly', fn ($m) => $m->count() === 12 && $m->last()['amount'] == 100);
    }

    public function test_reports_are_admin_only(): void
    {
        $this->actingAs($this->admin())->get(route('reports.index', ['from' => '2026-01-01', 'to' => '2026-12-31']))->assertOk();

        foreach (['technician', 'receptionist'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get(route('reports.index'))->assertForbidden();
        }
    }

    // --- Adelantos de comisiones ---

    public function test_advance_is_discounted_when_paying(): void
    {
        $admin = $this->admin();
        $tech = $this->technician('fixed', 30);
        $this->deliver($this->makeOrder($tech), $admin);
        $period = now()->format('Y-m');

        $this->actingAs($admin)->post(route('commissions.advances.store'), ['user_id' => $tech->id, 'period' => $period, 'amount' => 10, 'notes' => 'Pasajes']);
        $this->actingAs($admin)->get(route('commissions.index'))
            ->assertViewHas('summary', fn ($s) => $s[$tech->id]['to_pay'] == 20)
            ->assertSee('Pagar $20.00');

        $this->actingAs($admin)->post(route('commissions.pay'), ['user_id' => $tech->id, 'period' => $period])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '$20.00') && str_contains($m, 'adelantos y ajustes por $10.00'));

        $this->assertTrue(\App\Models\CommissionAdvance::first()->is_settled);
        $this->assertTrue(Commission::first()->is_paid);
    }

    public function test_excess_advance_carries_over_to_next_month(): void
    {
        $admin = $this->admin();
        $tech = $this->technician('fixed', 5);
        $this->deliver($this->makeOrder($tech), $admin);
        $period = now()->format('Y-m');

        $this->actingAs($admin)->post(route('commissions.advances.store'), ['user_id' => $tech->id, 'period' => $period, 'amount' => 12]);
        $this->actingAs($admin)->post(route('commissions.pay'), ['user_id' => $tech->id, 'period' => $period]);

        $carry = \App\Models\CommissionAdvance::where('is_settled', false)->first();
        $this->assertEquals(7, $carry->amount);
        $this->assertSame(now()->startOfMonth()->addMonth()->format('Y-m'), $carry->period);
    }

    public function test_technician_sees_own_advances_only_and_cannot_register(): void
    {
        $admin = $this->admin();
        $tech = $this->technician();
        $other = $this->technician();
        $period = now()->format('Y-m');
        $this->actingAs($admin)->post(route('commissions.advances.store'), ['user_id' => $tech->id, 'period' => $period, 'amount' => 3]);
        $this->actingAs($admin)->post(route('commissions.advances.store'), ['user_id' => $other->id, 'period' => $period, 'amount' => 4]);

        $this->actingAs($tech)->get(route('commissions.index'))
            ->assertViewHas('advances', fn ($a) => $a->pluck('user_id')->unique()->all() === [$tech->id]);
        $this->actingAs($tech)->post(route('commissions.advances.store'), ['user_id' => $tech->id, 'period' => $period, 'amount' => 100])
            ->assertForbidden();
    }

    public function test_month_is_parsed_correctly_on_day_31(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-01-31 10:00'));

        $this->actingAs($this->admin())->get(route('commissions.index', ['period' => '2026-02']))
            ->assertOk()->assertSee('febrero 2026');
    }
}
