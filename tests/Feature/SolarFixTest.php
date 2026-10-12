<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SolarFixTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->role('admin')->create();
    }

    private function makeOrder(array $attributes = []): Order
    {
        $client = Client::create(['name' => 'Cliente Test', 'phone' => '0999999999', 'client_type' => 'individual']);

        return Order::create($attributes + [
            'client_id' => $client->id,
            'order_number' => 'TEST-' . uniqid(),
            'device_type' => 'celular',
            'declared_fault' => 'No enciende',
            'unlock_type' => 'none',
            'status' => 'received',
            'entry_date' => now()->toDateString(),
            'diagnosis_cost' => 10,
            'labor_cost' => 20,
            'parts_cost' => 0,
            'total_amount' => 30,
            'balance_due' => 30,
        ]);
    }

    private function orderPayload(array $overrides = []): array
    {
        return $overrides + [
            'client_name' => 'Juan Pérez',
            'client_phone' => '0991234567',
            'device_type' => 'celular',
            'declared_fault' => 'Pantalla rota',
            'unlock_type' => 'pin',
            'unlock_value' => '1234',
            'entry_date' => now()->toDateString(),
            'diagnosis_cost' => 10,
            'labor_cost' => 40,
        ];
    }

    // --- Autenticación ---

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_active_user_can_log_in(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta'])
            ->assertStatus(429);
    }

    public function test_user_can_be_deactivated_by_unchecking_checkbox(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'branch_id' => $user->branch_id,
            'is_active' => '0', // lo que envía el input hidden cuando el checkbox está desmarcado
        ])->assertRedirect(route('users.index'));

        $this->assertFalse($user->fresh()->is_active);
    }

    // --- Permisos ---

    public function test_technician_cannot_delete_orders_or_invoices(): void
    {
        $tech = User::factory()->role('technician')->create();
        $order = $this->makeOrder();
        $invoice = Invoice::createWithUniqueNumber([
            'order_id' => $order->id, 'client_name' => 'X', 'subtotal' => 30,
            'issued_at' => now(), 'status' => 'emitida',
        ]);

        $this->actingAs($tech)->delete(route('orders.destroy', $order))->assertForbidden();
        $this->actingAs($tech)->delete(route('invoices.destroy', $invoice))->assertForbidden();

        $this->assertNotSoftDeleted($order);
        $this->assertNotSoftDeleted($invoice);
    }

    public function test_admin_can_delete_orders(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin())->delete(route('orders.destroy', $order))
            ->assertRedirect(route('orders.index'));

        $this->assertSoftDeleted($order);
    }

    // --- Órdenes ---

    public function test_unlock_value_is_encrypted_at_rest(): void
    {
        $this->actingAs($this->admin())->post(route('orders.store'), $this->orderPayload());

        $order = Order::latest('id')->first();
        $raw = DB::table('orders')->where('id', $order->id)->value('unlock_value');

        $this->assertSame('1234', $order->unlock_value);
        $this->assertNotSame('1234', $raw);
    }

    public function test_new_client_requires_phone(): void
    {
        $this->actingAs($this->admin())
            ->post(route('orders.store'), $this->orderPayload(['client_phone' => '']))
            ->assertSessionHasErrors('client_phone');
    }

    public function test_initial_deposit_is_recorded_as_payment(): void
    {
        $this->actingAs($this->admin())->post(route('orders.store'), $this->orderPayload(['amount_paid' => 15]));

        $order = Order::latest('id')->first();

        $this->assertEquals(15, $order->amount_paid);
        $this->assertEquals(35, $order->balance_due);
        $this->assertEquals(15, $order->payments()->sum('amount'));
    }

    public function test_editing_order_does_not_overwrite_amount_paid(): void
    {
        $order = $this->makeOrder();
        OrderPayment::create(['order_id' => $order->id, 'amount' => 25, 'method' => 'cash', 'paid_at' => now()]);

        $this->actingAs($this->admin())->put(route('orders.update', $order), [
            'client_id' => $order->client_id,
            'device_type' => 'celular',
            'declared_fault' => 'No enciende',
            'unlock_type' => 'none',
            'entry_date' => now()->toDateString(),
            'diagnosis_cost' => 10,
            'labor_cost' => 50,
            'amount_paid' => 999, // debe ignorarse
        ]);

        $order->refresh();
        $this->assertEquals(25, $order->amount_paid);
        $this->assertEquals(35, $order->balance_due);
    }

    public function test_order_numbers_are_sequential(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('orders.store'), $this->orderPayload());
        $this->actingAs($admin)->post(route('orders.store'), $this->orderPayload());

        $numbers = Order::latest('id')->take(2)->pluck('order_number');
        $this->assertNotEquals($numbers[0], $numbers[1]);
    }

    // --- Facturas ---

    public function test_invoice_subtotal_is_computed_from_items(): void
    {
        $this->actingAs($this->admin())->post(route('invoices.store'), [
            'client_name' => 'Cliente',
            'subtotal' => 1, // valor manipulado, debe ignorarse
            'iva_percent' => 15,
            'issued_at' => now()->toDateString(),
            'items' => [
                ['description' => 'Pantalla', 'quantity' => 2, 'unit_price' => 30],
                ['description' => 'Mano de obra', 'quantity' => 1, 'unit_price' => 20],
            ],
        ]);

        $invoice = Invoice::latest('id')->first();
        $this->assertEquals(80, $invoice->subtotal);
        $this->assertEquals(12, $invoice->iva_amount);
        $this->assertEquals(92, $invoice->total);
        $this->assertCount(2, $invoice->items);
    }

    public function test_cannot_create_second_active_invoice_for_order(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();
        $this->actingAs($admin)->post(route('invoices.generate-from-order', $order));
        $first = Invoice::where('order_id', $order->id)->first();

        $this->actingAs($admin)->post(route('invoices.store'), [
            'order_id' => $order->id,
            'client_name' => 'Cliente',
            'iva_percent' => 0,
            'issued_at' => now()->toDateString(),
            'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 30]],
        ])->assertRedirect(route('invoices.show', $first));

        $this->assertEquals(1, Invoice::where('order_id', $order->id)->count());
    }

    public function test_cancelled_invoice_cannot_be_marked_paid(): void
    {
        $invoice = Invoice::createWithUniqueNumber([
            'client_name' => 'X', 'subtotal' => 10, 'issued_at' => now(), 'status' => 'anulada',
        ]);

        $this->actingAs($this->admin())->patch(route('invoices.mark-paid', $invoice));

        $this->assertSame('anulada', $invoice->fresh()->status);
    }

    public function test_cancelling_invoice_releases_payments_for_new_invoice(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();
        $payment = OrderPayment::create(['order_id' => $order->id, 'amount' => 30, 'method' => 'cash', 'paid_at' => now()]);

        $this->actingAs($admin)->post(route('invoices.generate-from-order', $order));
        $first = Invoice::where('order_id', $order->id)->first();
        $this->assertEquals($first->id, $payment->fresh()->invoice_id);

        $this->actingAs($admin)->patch(route('invoices.cancel', $first));
        $this->assertNotNull($first->fresh()->cancelled_at);
        $this->assertNull($payment->fresh()->invoice_id);

        $this->actingAs($admin)->post(route('invoices.generate-from-order', $order));
        $second = Invoice::where('order_id', $order->id)->where('status', 'emitida')->first();
        $this->assertEquals($second->id, $payment->fresh()->invoice_id);
    }

    // --- Técnico asignado ---

    public function test_technician_only_sees_own_and_unassigned_orders(): void
    {
        $tech = User::factory()->role('technician')->create();
        $other = User::factory()->role('technician')->create();
        $mine = $this->makeOrder(['user_id' => $tech->id]);
        $unassigned = $this->makeOrder();
        $theirs = $this->makeOrder(['user_id' => $other->id]);

        $this->actingAs($tech)->get(route('orders.index'))
            ->assertSee($mine->order_number)
            ->assertSee($unassigned->order_number)
            ->assertDontSee($theirs->order_number);

        $this->actingAs($tech)->get(route('orders.show', $mine))->assertOk();
        $this->actingAs($tech)->get(route('orders.show', $unassigned))->assertOk();
        $this->actingAs($tech)->get(route('orders.show', $theirs))->assertForbidden();
        $this->actingAs($tech)->get(route('orders.edit', $theirs))->assertForbidden();
        $this->actingAs($tech)->get(route('orders.pdf', $theirs))->assertForbidden();
    }

    public function test_admin_sees_all_orders(): void
    {
        $other = User::factory()->role('technician')->create();
        $order = $this->makeOrder(['user_id' => $other->id]);

        $this->actingAs($this->admin())->get(route('orders.index'))->assertSee($order->order_number);
    }

    public function test_receptionist_assigns_technician_on_create(): void
    {
        $tech = User::factory()->role('technician')->create();
        $receptionist = User::factory()->role('receptionist')->create();

        $this->actingAs($receptionist)->post(route('orders.store'), $this->orderPayload(['user_id' => $tech->id]));

        $this->assertEquals($tech->id, Order::latest('id')->first()->user_id);
    }

    public function test_only_technicians_can_be_assigned(): void
    {
        $receptionist = User::factory()->role('receptionist')->create();

        $this->actingAs($receptionist)
            ->post(route('orders.store'), $this->orderPayload(['user_id' => $receptionist->id]))
            ->assertSessionHasErrors('user_id');
    }

    public function test_technician_creating_order_is_assigned_and_cannot_reassign(): void
    {
        $tech = User::factory()->role('technician')->create();
        $other = User::factory()->role('technician')->create();

        $this->actingAs($tech)->post(route('orders.store'), $this->orderPayload(['user_id' => $other->id]));
        $order = Order::latest('id')->first();
        $this->assertEquals($tech->id, $order->user_id);

        $this->actingAs($tech)->put(route('orders.update', $order), [
            'client_id' => $order->client_id,
            'device_type' => 'celular',
            'declared_fault' => 'Pantalla rota',
            'unlock_type' => 'none',
            'entry_date' => now()->toDateString(),
            'user_id' => $other->id,
        ]);
        $this->assertEquals($tech->id, $order->fresh()->user_id);
    }

    public function test_editing_keeps_existing_non_technician_assignee(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder(['user_id' => $admin->id]);

        $this->actingAs($admin)->put(route('orders.update', $order), [
            'client_id' => $order->client_id,
            'device_type' => 'celular',
            'declared_fault' => 'No enciende',
            'unlock_type' => 'none',
            'entry_date' => now()->toDateString(),
            'user_id' => $admin->id,
        ])->assertSessionHasNoErrors();

        $this->assertEquals($admin->id, $order->fresh()->user_id);
    }

    // --- Búsqueda ---

    public function test_order_search_covers_all_pages(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->makeOrder();
        }
        $target = $this->makeOrder(['serial_imei' => 'IMEI-BUSCADO-123']);
        // La orden buscada no está en la primera página (15 por página, más recientes primero)
        Order::whereKey($target->id)->update(['created_at' => now()->subYear()]);

        $this->actingAs($this->admin())->get(route('orders.index', ['q' => 'BUSCADO']))
            ->assertSee($target->order_number)
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 1);
    }

    public function test_client_search_by_document(): void
    {
        Client::create(['name' => 'Rosa Buscada', 'phone' => '0911111111', 'id_document' => '0102030405', 'client_type' => 'individual']);
        Client::create(['name' => 'Otro Cliente', 'phone' => '0922222222', 'client_type' => 'individual']);

        $this->actingAs($this->admin())->get(route('clients.index', ['q' => '0102030405']))
            ->assertSee('Rosa Buscada')
            ->assertDontSee('Otro Cliente');
    }

    // --- Eliminación de registros en uso ---

    public function test_in_use_catalog_items_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $brand = \App\Models\DeviceBrand::create(['name' => 'Marca Test', 'is_active' => true]);
        $model = \App\Models\DeviceModel::create(['brand_id' => $brand->id, 'name' => 'Modelo Test', 'device_type' => 'celular']);
        $accessory = \App\Models\Accessory::create(['name' => 'Accesorio Test', 'is_active' => true]);
        $order = $this->makeOrder(['brand_id' => $brand->id, 'model_id' => $model->id]);
        $order->accessories()->attach($accessory->id);

        $this->actingAs($admin)->delete(route('accessories.destroy', $accessory))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('models.destroy', $model))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('brands.destroy', $brand))->assertSessionHas('error');

        $this->assertModelExists($accessory);
        $this->assertModelExists($model);
        $this->assertModelExists($brand);
    }

    public function test_unused_catalog_items_can_be_deleted(): void
    {
        $admin = $this->admin();
        $accessory = \App\Models\Accessory::create(['name' => 'Sin uso', 'is_active' => true]);

        $this->actingAs($admin)->delete(route('accessories.destroy', $accessory))->assertSessionHas('success');
        $this->assertModelMissing($accessory);
    }

    public function test_order_keeps_deleted_client_name(): void
    {
        $order = $this->makeOrder();
        $order->client->delete();

        $this->actingAs($this->admin())->get(route('orders.show', $order))->assertSee('Cliente Test');
    }

    // --- Pantallas ---

    public function test_main_screens_render_for_each_role(): void
    {
        $order = $this->makeOrder();
        $invoice = Invoice::createWithUniqueNumber([
            'order_id' => $order->id, 'client_name' => 'X', 'subtotal' => 30,
            'issued_at' => now(), 'status' => 'emitida',
        ]);

        foreach (['admin', 'technician', 'receptionist'] as $role) {
            $user = User::factory()->role($role)->create();

            foreach ([
                route('dashboard'),
                route('orders.index'),
                route('orders.index', ['q' => 'test', 'status' => 'received']),
                route('orders.create'),
                route('orders.show', $order),
                route('orders.edit', $order),
            ] as $url) {
                $this->actingAs($user)->get($url)->assertOk();
            }

            foreach ([
                route('invoices.index', ['q' => 'X']),
                route('invoices.show', $invoice),
                route('invoices.payments'),
            ] as $url) {
                $this->actingAs($user)->get($url)->assertStatus($role === 'technician' ? 403 : 200);
            }
        }

        $admin = $this->admin();
        foreach (['users', 'clients', 'brands', 'models', 'accessories'] as $resource) {
            $this->actingAs($admin)->get(route("$resource.index", ['q' => 'a']))->assertOk();
        }
        $this->actingAs($admin)->get(route('users.edit', $admin))->assertOk();
        $this->actingAs($admin)->get(route('users.create'))->assertOk();
    }
}
