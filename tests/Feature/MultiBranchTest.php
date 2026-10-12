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
}
