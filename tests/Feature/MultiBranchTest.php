<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiBranchTest extends TestCase
{
    use RefreshDatabase;

    private Branch $gye;
    private Branch $qto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gye = Branch::find(Branch::mainId());
        $this->gye->update(['name' => 'Guayaquil', 'code' => 'GYE']);
        $this->qto = Branch::create(['name' => 'Quito', 'code' => 'QTO', 'is_active' => true]);
    }

    private function user(string $role, ?Branch $branch = null): User
    {
        return User::factory()->role($role)->create(['branch_id' => ($branch ?? $this->gye)->id]);
    }

    private function makeOrder(Branch $branch, array $attributes = []): Order
    {
        $client = Client::create(['name' => 'Cliente ' . $branch->code, 'phone' => '0991234567', 'client_type' => 'individual']);

        return Order::create($attributes + [
            'branch_id' => $branch->id, 'client_id' => $client->id, 'order_number' => $branch->code . '-' . uniqid(),
            'device_type' => 'celular', 'declared_fault' => 'Falla ' . $branch->code, 'unlock_type' => 'none',
            'status' => 'received', 'entry_date' => now()->toDateString(),
        ]);
    }

    private function orderPayload(array $overrides = []): array
    {
        return $overrides + [
            'client_name' => 'Nuevo', 'client_phone' => '0990000000', 'device_type' => 'celular',
            'declared_fault' => 'x', 'unlock_type' => 'none', 'entry_date' => now()->toDateString(),
        ];
    }

    // --- Sucursales ---

    public function test_admin_manages_branches_with_unique_code(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post(route('branches.store'), ['name' => 'Cuenca', 'code' => 'cue', 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('CUE', Branch::where('name', 'Cuenca')->value('code'));

        $this->actingAs($admin)->post(route('branches.store'), ['name' => 'Otra', 'code' => 'GYE'])->assertSessionHasErrors('code');
        $this->actingAs($admin)->post(route('branches.store'), ['name' => 'Mala', 'code' => 'A B'])->assertSessionHasErrors('code');
        $this->actingAs($this->user('receptionist'))->get(route('branches.index'))->assertForbidden();
    }

    public function test_each_branch_has_its_own_order_sequence(): void
    {
        $this->actingAs($this->user('receptionist', $this->gye))->post(route('orders.store'), $this->orderPayload());
        $this->actingAs($this->user('receptionist', $this->gye))->post(route('orders.store'), $this->orderPayload());
        $this->actingAs($this->user('receptionist', $this->qto))->post(route('orders.store'), $this->orderPayload());

        $day = now()->format('Ymd');
        $numbers = Order::orderBy('id')->pluck('order_number')->all();
        $this->assertSame(["ORD-GYE-{$day}-0001", "ORD-GYE-{$day}-0002", "ORD-QTO-{$day}-0001"], $numbers);
        $this->assertSame($this->qto->id, Order::latest('id')->first()->branch_id);
    }

    // --- Aislamiento ---

    public function test_receptionist_only_sees_own_branch(): void
    {
        $gyeOrder = $this->makeOrder($this->gye);
        $qtoOrder = $this->makeOrder($this->qto);
        $receptionist = $this->user('receptionist', $this->qto);

        $this->actingAs($receptionist)->get(route('orders.index'))
            ->assertSee($qtoOrder->order_number)->assertDontSee($gyeOrder->order_number);
        $this->actingAs($receptionist)->get(route('orders.show', $gyeOrder))->assertForbidden();
        $this->actingAs($receptionist)->get(route('orders.ticket', $gyeOrder))->assertForbidden();
        $this->actingAs($receptionist)->get(route('search', ['q' => 'Cliente']))
            ->assertViewHas('orders', fn ($o) => $o->pluck('id')->all() === [$qtoOrder->id]);
        $this->actingAs($receptionist)->get(route('dashboard'))
            ->assertViewHas('counter', fn ($c) => $c['today']->pluck('id')->all() === [$qtoOrder->id]);
    }

    public function test_technician_cannot_take_or_work_on_other_branch(): void
    {
        $gyeOrder = $this->makeOrder($this->gye);
        $tech = $this->user('technician', $this->qto);

        $this->actingAs($tech)->post(route('orders.take', $gyeOrder))->assertForbidden();
        $this->actingAs($tech)->patch(route('orders.quick-status', $gyeOrder), ['status' => 'diagnosing'])->assertForbidden();
        $this->actingAs($tech)->get(route('dashboard'))->assertViewHas('myWork', fn ($w) => $w['available']->isEmpty());
    }

    public function test_admin_sees_all_and_filters_with_switcher(): void
    {
        $gyeOrder = $this->makeOrder($this->gye);
        $qtoOrder = $this->makeOrder($this->qto);
        $admin = $this->user('admin');

        $this->actingAs($admin)->get(route('orders.index'))->assertSee($gyeOrder->order_number)->assertSee($qtoOrder->order_number);

        $this->actingAs($admin)->post(route('branch-context'), ['branch_id' => $this->qto->id]);
        $this->actingAs($admin)->get(route('orders.index'))->assertSee($qtoOrder->order_number)->assertDontSee($gyeOrder->order_number);
        // Aun filtrando, puede abrir una orden de otra sucursal.
        $this->actingAs($admin)->get(route('orders.show', $gyeOrder))->assertOk();

        $this->actingAs($admin)->post(route('branch-context'), ['branch_id' => '']);
        $this->assertNull(BranchContext::current($admin));
    }

    public function test_non_admin_cannot_use_switcher(): void
    {
        $this->actingAs($this->user('receptionist'))->post(route('branch-context'), ['branch_id' => $this->qto->id])->assertForbidden();
    }

    public function test_admin_creates_order_in_chosen_branch_with_its_technicians(): void
    {
        $admin = $this->user('admin');
        $qtoTech = $this->user('technician', $this->qto);
        $gyeTech = $this->user('technician', $this->gye);

        $this->actingAs($admin)->get(route('orders.create'))->assertSee('Sucursal *');

        $this->actingAs($admin)->post(route('orders.store'), $this->orderPayload(['branch_id' => $this->qto->id, 'user_id' => $gyeTech->id]))
            ->assertSessionHasErrors('user_id');

        $this->actingAs($admin)->post(route('orders.store'), $this->orderPayload(['branch_id' => $this->qto->id, 'user_id' => $qtoTech->id]))
            ->assertSessionHasNoErrors();
        $order = Order::latest('id')->first();
        $this->assertSame($this->qto->id, $order->branch_id);
        $this->assertStringContainsString('-QTO-', $order->order_number);
    }

    public function test_receptionist_cannot_pick_other_branch(): void
    {
        $this->actingAs($this->user('receptionist', $this->gye))->post(route('orders.store'), $this->orderPayload(['branch_id' => $this->qto->id]));

        $this->assertSame($this->gye->id, Order::latest('id')->first()->branch_id);
    }

    public function test_technician_and_receptionist_require_branch(): void
    {
        $this->actingAs($this->user('admin'))->post(route('users.store'), [
            'name' => 'Sin sucursal', 'email' => 'x@solarfix.ec', 'password' => 'secreto123', 'password_confirmation' => 'secreto123',
            'role' => 'technician', 'is_active' => '1',
        ])->assertSessionHasErrors('branch_id');
    }

    // --- Caja por sucursal ---

    public function test_each_branch_has_its_own_cash(): void
    {
        $gyeOrder = $this->makeOrder($this->gye);
        $qtoOrder = $this->makeOrder($this->qto);
        \App\Models\OrderPayment::create(['order_id' => $gyeOrder->id, 'amount' => 40, 'method' => 'cash', 'paid_at' => now()]);
        \App\Models\OrderPayment::create(['order_id' => $qtoOrder->id, 'amount' => 25, 'method' => 'cash', 'paid_at' => now()]);
        $gyeDesk = $this->user('receptionist', $this->gye);
        $qtoDesk = $this->user('receptionist', $this->qto);

        $this->actingAs($qtoDesk)->post(route('cash.movements.store'), ['type' => 'opening', 'amount' => 10, 'description' => 'Fondo Quito']);
        $this->actingAs($qtoDesk)->get(route('cash.index'))
            ->assertViewHas('totals', fn ($t) => $t['cash'] == 25)
            ->assertViewHas('cash', fn ($c) => $c['expected'] == 35);
        $this->actingAs($gyeDesk)->get(route('cash.index'))
            ->assertViewHas('totals', fn ($t) => $t['cash'] == 40)
            ->assertViewHas('cash', fn ($c) => $c['expected'] == 40);

        // Cada sucursal cierra su propia caja el mismo día.
        $this->actingAs($gyeDesk)->post(route('cash.store'), ['counted_cash' => 40])->assertSessionHasNoErrors();
        $this->actingAs($qtoDesk)->post(route('cash.store'), ['counted_cash' => 35])->assertSessionHasNoErrors();
        $this->assertSame(2, \App\Models\CashClosing::count());
        $this->assertEquals(35, \App\Models\CashClosing::where('branch_id', $this->qto->id)->value('expected_cash'));
    }

    public function test_receptionist_cannot_open_other_branch_cash(): void
    {
        $qtoOrder = $this->makeOrder($this->qto);
        \App\Models\OrderPayment::create(['order_id' => $qtoOrder->id, 'amount' => 25, 'method' => 'cash', 'paid_at' => now()]);

        $this->actingAs($this->user('receptionist', $this->gye))->get(route('cash.index', ['branch' => $this->qto->id]))
            ->assertViewHas('totals', fn ($t) => $t['cash'] == 0);
    }

    public function test_admin_reviews_any_branch_cash(): void
    {
        $qtoOrder = $this->makeOrder($this->qto);
        \App\Models\OrderPayment::create(['order_id' => $qtoOrder->id, 'amount' => 25, 'method' => 'cash', 'paid_at' => now()]);

        $this->actingAs($this->user('admin'))->get(route('cash.index', ['branch' => $this->qto->id]))
            ->assertOk()->assertSee('Caja del día · Quito')
            ->assertViewHas('totals', fn ($t) => $t['cash'] == 25);
    }

    // --- Inventario por sucursal ---

    private function part(array $stocks): \App\Models\SparePart
    {
        $part = \App\Models\SparePart::create(['name' => 'Pantalla A15', 'cost_price' => 20, 'sale_price' => 45, 'min_stock' => 2]);
        foreach ($stocks as $branchId => $quantity) {
            $part->setBranchStock($branchId, $quantity);
        }

        return $part->fresh();
    }

    public function test_purchase_goes_to_the_users_branch(): void
    {
        $part = $this->part([$this->gye->id => 5]);

        $this->actingAs($this->user('technician', $this->qto))
            ->post(route('spare-parts.receive', $part), ['quantity' => 3, 'unit_cost' => 20])->assertSessionHasNoErrors();

        $part->refresh();
        $this->assertSame(5, $part->stockIn($this->gye->id));
        $this->assertSame(3, $part->stockIn($this->qto->id));
        $this->assertSame(8, $part->stock_quantity);
        $this->assertSame($this->qto->id, $part->movements()->first()->branch_id);
    }

    public function test_order_uses_stock_of_its_branch(): void
    {
        $part = $this->part([$this->gye->id => 5, $this->qto->id => 0]);
        $qtoOrder = $this->makeOrder($this->qto);
        $qtoDesk = $this->user('receptionist', $this->qto);

        $this->actingAs($qtoDesk)->post(route('orders.parts.store', $qtoOrder), ['spare_part_id' => $part->id, 'quantity' => 1])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'en esta sucursal'));

        $this->actingAs($qtoDesk)->getJson(route('spare-parts.search', ['q' => 'Pantalla', 'branch_id' => $qtoOrder->branch_id]))
            ->assertJsonPath('0.stock', 0);
    }

    public function test_transfer_moves_units_between_branches(): void
    {
        $part = $this->part([$this->gye->id => 5]);
        $tech = $this->user('technician', $this->gye);

        $this->actingAs($tech)->post(route('spare-parts.transfer', $part), [
            'from_branch_id' => $this->gye->id, 'to_branch_id' => $this->qto->id, 'quantity' => 2, 'note' => 'Pedido urgente',
        ])->assertSessionHas('success');

        $part->refresh();
        $this->assertSame(3, $part->stockIn($this->gye->id));
        $this->assertSame(2, $part->stockIn($this->qto->id));
        $this->assertSame(5, $part->stock_quantity);
        $movements = \App\Models\SparePartMovement::where('spare_part_id', $part->id)->orderBy('id')->get();
        $this->assertSame(['out', 'in'], $movements->pluck('type')->all());
        $this->assertSame([$this->gye->id, $this->qto->id], $movements->pluck('branch_id')->all());
        $this->assertStringContainsString('Traslado a Quito', $movements->first()->reason);

        // Ya en Quito, se puede usar en una orden de Quito.
        $qtoOrder = $this->makeOrder($this->qto);
        $this->actingAs($this->user('receptionist', $this->qto))
            ->post(route('orders.parts.store', $qtoOrder), ['spare_part_id' => $part->id, 'quantity' => 2])->assertSessionHas('success');
        $this->assertSame(0, $part->fresh()->stockIn($this->qto->id));
    }

    public function test_transfer_rules(): void
    {
        $part = $this->part([$this->gye->id => 1]);

        // Sin stock suficiente en el origen.
        $this->actingAs($this->user('admin'))->post(route('spare-parts.transfer', $part), [
            'from_branch_id' => $this->gye->id, 'to_branch_id' => $this->qto->id, 'quantity' => 5,
        ])->assertSessionHas('error');
        // Un técnico no envía desde otra sucursal.
        $this->actingAs($this->user('technician', $this->qto))->post(route('spare-parts.transfer', $part), [
            'from_branch_id' => $this->gye->id, 'to_branch_id' => $this->qto->id, 'quantity' => 1,
        ])->assertForbidden();
        // Origen y destino distintos.
        $this->actingAs($this->user('admin'))->post(route('spare-parts.transfer', $part), [
            'from_branch_id' => $this->gye->id, 'to_branch_id' => $this->gye->id, 'quantity' => 1,
        ])->assertSessionHasErrors('to_branch_id');

        $this->assertSame(1, $part->fresh()->stockIn($this->gye->id));
    }

    public function test_low_stock_alert_is_per_branch(): void
    {
        $part = $this->part([$this->gye->id => 10, $this->qto->id => 1]);

        $this->assertTrue($part->isLowStock($this->qto->id));
        $this->assertFalse($part->isLowStock($this->gye->id));
        $this->assertSame(1, \App\Models\SparePart::lowStock($this->qto->id)->count());
        $this->assertSame(0, \App\Models\SparePart::lowStock($this->gye->id)->count());

        $this->actingAs($this->user('technician', $this->gye))->get(route('dashboard'))->assertDontSee('Repuestos con stock bajo');
        $this->actingAs($this->user('technician', $this->qto))->get(route('dashboard'))->assertSee('Repuestos con stock bajo');
    }

    public function test_cancelled_order_returns_parts_to_its_branch(): void
    {
        $part = $this->part([$this->qto->id => 4]);
        $qtoOrder = $this->makeOrder($this->qto);
        $admin = $this->user('admin');
        $this->actingAs($admin)->post(route('orders.parts.store', $qtoOrder), ['spare_part_id' => $part->id, 'quantity' => 3]);

        $this->actingAs($admin)->delete(route('orders.destroy', $qtoOrder));

        $this->assertSame(4, $part->fresh()->stockIn($this->qto->id));
        $this->assertSame(0, $part->fresh()->stockIn($this->gye->id));
    }

    public function test_inventory_screens_show_branch_breakdown(): void
    {
        $part = $this->part([$this->gye->id => 5, $this->qto->id => 2]);
        $admin = $this->user('admin');

        $this->actingAs($admin)->get(route('spare-parts.index'))->assertSee('GYE: 5')->assertSee('QTO: 2');
        $this->actingAs($admin)->get(route('spare-parts.show', $part))->assertOk()->assertSee('Traslado entre sucursales');
        $this->actingAs($this->user('receptionist', $this->qto))->get(route('spare-parts.index'))
            ->assertViewHas('branch', $this->qto->id);
    }
}
