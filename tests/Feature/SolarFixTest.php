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
                route('orders.create'),
                route('orders.show', $order),
                route('orders.edit', $order),
                route('invoices.index'),
                route('invoices.show', $invoice),
            ] as $url) {
                $this->actingAs($user)->get($url)->assertOk();
            }
        }

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('users.edit', $admin))->assertOk();
        $this->actingAs($admin)->get(route('users.create'))->assertOk();
    }
}
