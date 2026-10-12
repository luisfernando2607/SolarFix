<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\MaintenancePlan;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private Branch $gye;
    private Branch $qto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gye = Branch::find(Branch::mainId());
        $this->gye->update(['code' => 'GYE']);
        $this->qto = Branch::create(['name' => 'Quito', 'code' => 'QTO', 'is_active' => true]);
    }

    private function user(string $role, ?Branch $branch = null, array $attributes = []): User
    {
        return User::factory()->role($role)->create($attributes + ['branch_id' => ($branch ?? $this->gye)->id]);
    }

    private function makeOrder(Branch $branch, array $attributes = []): Order
    {
        $client = Client::create(['name' => 'Cliente ' . $branch->code, 'phone' => '0991234567', 'client_type' => 'individual']);

        return Order::create($attributes + [
            'branch_id' => $branch->id, 'client_id' => $client->id, 'order_number' => $branch->code . '-' . uniqid(),
            'device_type' => 'celular', 'declared_fault' => 'Falla', 'unlock_type' => 'none',
            'status' => 'ready', 'entry_date' => now()->toDateString(),
            'labor_cost' => 100, 'total_amount' => 100, 'balance_due' => 100,
        ]);
    }

    private function editPayload(Order $order, array $overrides = []): array
    {
        return $overrides + [
            'client_id' => $order->client_id, 'device_type' => 'celular', 'declared_fault' => 'Falla',
            'unlock_type' => 'none', 'entry_date' => $order->entry_date->toDateString(), 'labor_cost' => 100,
        ];
    }

    // --- Acceso entre sucursales ---

    public function test_receptionist_cannot_charge_or_read_receipts_of_other_branch(): void
    {
        $qtoOrder = $this->makeOrder($this->qto);
        $gyeDesk = $this->user('receptionist', $this->gye);

        $this->actingAs($gyeDesk)->post(route('orders.payments.store', $qtoOrder), ['amount' => 10, 'method' => 'cash'])->assertForbidden();
        $this->assertSame(0, OrderPayment::count());

        $payment = OrderPayment::register($qtoOrder, 10, 'cash', null);
        $this->actingAs($gyeDesk)->get(route('orders.payments.receipt', [$qtoOrder, $payment]))->assertForbidden();
    }

    public function test_invoices_cannot_be_created_for_other_branch_orders(): void
    {
        $qtoOrder = $this->makeOrder($this->qto);
        $gyeDesk = $this->user('receptionist', $this->gye);

        $this->actingAs($gyeDesk)->get(route('invoices.create', ['order_id' => $qtoOrder->id]))->assertForbidden();
        $this->actingAs($gyeDesk)->post(route('invoices.store'), [
            'order_id' => $qtoOrder->id, 'client_name' => 'X', 'iva_percent' => 15, 'issued_at' => today()->toDateString(),
            'items' => [['description' => 'Servicio', 'quantity' => 1, 'unit_price' => 10]],
        ])->assertForbidden();
        $this->assertSame(0, Invoice::count());
    }

    public function test_appointments_cannot_point_to_other_branch_orders_or_plans(): void
    {
        $qtoOrder = $this->makeOrder($this->qto);
        $plan = MaintenancePlan::create([
            'branch_id' => $this->qto->id, 'client_id' => $qtoOrder->client_id, 'title' => 'Plan Quito',
            'interval_months' => 6, 'next_due_date' => today()->addMonth(), 'is_active' => true,
        ]);
        $gyeDesk = $this->user('receptionist', $this->gye);

        $this->actingAs($gyeDesk)->get(route('appointments.create', ['order_id' => $qtoOrder->id]))->assertForbidden();
        $this->actingAs($gyeDesk)->get(route('appointments.create', ['plan_id' => $plan->id]))->assertForbidden();

        $base = [
            'client_id' => $qtoOrder->client_id, 'type' => 'followup', 'title' => 'Visita',
            'scheduled_at' => now()->addDay()->format('Y-m-d\\TH:i'), 'duration_min' => 60,
        ];
        $this->actingAs($gyeDesk)->post(route('appointments.store'), $base + ['order_id' => $qtoOrder->id])->assertSessionHasErrors('order_id');
        $this->actingAs($gyeDesk)->post(route('appointments.store'), $base + ['maintenance_plan_id' => $plan->id])->assertSessionHasErrors('maintenance_plan_id');
        $this->assertSame(0, Appointment::count());
    }

    public function test_order_cannot_link_appointment_of_other_branch(): void
    {
        $client = Client::create(['name' => 'Ana', 'phone' => '0991234567', 'client_type' => 'individual']);
        $appointment = Appointment::create([
            'branch_id' => $this->qto->id, 'client_id' => $client->id, 'type' => 'home_visit', 'title' => 'Visita Quito',
            'scheduled_at' => now()->addDay(), 'duration_min' => 60, 'status' => 'pending',
        ]);

        $this->actingAs($this->user('receptionist', $this->gye))->post(route('orders.store'), [
            'client_id' => $client->id, 'device_type' => 'celular', 'declared_fault' => 'x', 'unlock_type' => 'none',
            'entry_date' => today()->toDateString(), 'appointment_id' => $appointment->id,
        ])->assertForbidden();
        $this->assertNull($appointment->fresh()->order_id);
    }

    // --- Usuarios y roles ---

    public function test_admin_cannot_create_or_manage_super_admins(): void
    {
        $admin = $this->user('admin');
        $super = $this->user('super_admin');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Otro', 'email' => 'otro@x.ec', 'password' => 'secreto123', 'password_confirmation' => 'secreto123',
            'role' => 'super_admin', 'is_active' => '1',
        ])->assertSessionHasErrors('role');

        $this->actingAs($admin)->get(route('users.edit', $super))->assertForbidden();
        $this->actingAs($admin)->put(route('users.update', $super), [
            'name' => $super->name, 'email' => $super->email, 'role' => 'super_admin',
            'password' => 'tomada123', 'password_confirmation' => 'tomada123', 'is_active' => '0',
        ])->assertForbidden();
        $this->actingAs($admin)->delete(route('users.destroy', $super))->assertForbidden();
        $this->assertTrue($super->fresh()->is_active);
    }

    public function test_users_cannot_promote_or_deactivate_themselves(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'super_admin', 'is_active' => '0',
        ])->assertSessionHasErrors('role');

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'admin', 'is_active' => '0',
        ]);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_last_active_super_admin_is_protected(): void
    {
        $super = $this->user('super_admin');
        $other = $this->user('super_admin');

        // Con dos, uno puede desactivar al otro; el que queda no puede quedarse sin reemplazo.
        $this->actingAs($super)->put(route('users.update', $other), [
            'name' => $other->name, 'email' => $other->email, 'role' => 'super_admin', 'is_active' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($other->fresh()->is_active);

        $this->flushSession();
        $this->actingAs($other->fresh())->get(route('dashboard'))->assertRedirect(route('login')); // desactivado: queda fuera
        $this->assertGuest();

        $third = $this->user('super_admin', null, ['is_active' => false]);
        $this->actingAs($super)->put(route('users.update', $super), [
            'name' => $super->name, 'email' => $super->email, 'role' => 'admin', 'is_active' => '1',
        ]);
        $this->assertSame('super_admin', $super->fresh()->role); // no puede cambiar su propio rol
        $this->actingAs($super)->delete(route('users.destroy', $super));
        $this->assertNotNull($super->fresh());
    }

    // --- Técnicos ---

    public function test_nobody_delivers_from_the_edit_form(): void
    {
        $order = $this->makeOrder($this->gye);

        $this->actingAs($this->user('admin'))->put(route('orders.update', $order), $this->editPayload($order, ['status' => 'delivered']))
            ->assertSessionHasErrors('status');
        $this->assertSame('ready', $order->fresh()->status);
        $this->assertNull($order->fresh()->warranty);
    }

    public function test_technician_follows_workflow_and_cannot_edit_closed_orders(): void
    {
        $tech = $this->user('technician', $this->gye, ['commission_type' => 'percent', 'commission_value' => 10]);
        $order = $this->makeOrder($this->gye, ['user_id' => $tech->id, 'status' => 'received']);

        // Saltarse pasos no está permitido; el siguiente paso sí.
        $this->actingAs($tech)->put(route('orders.update', $order), $this->editPayload($order, ['status' => 'ready']))
            ->assertSessionHasErrors('status');
        $this->actingAs($tech)->put(route('orders.update', $order), $this->editPayload($order, ['status' => 'diagnosing']))
            ->assertSessionHasNoErrors();
        $this->assertSame('diagnosing', $order->fresh()->status);

        // Entregada: no puede subir la mano de obra (y con ella su comisión).
        $order->update(['status' => 'ready']);
        $order->fresh()->markDelivered(null);
        $commission = $order->fresh()->commission;
        $before = (float) $commission->commission_amount;

        $this->actingAs($tech)->put(route('orders.update', $order), $this->editPayload($order, ['labor_cost' => 9999, 'status' => 'delivered']))
            ->assertRedirect(route('orders.show', $order));
        $this->assertEquals(100, $order->fresh()->labor_cost);
        $this->assertEquals($before, (float) $commission->fresh()->commission_amount);
    }

    public function test_technician_cannot_register_advance_payment(): void
    {
        $tech = $this->user('technician', $this->gye);

        $this->actingAs($tech)->post(route('orders.store'), [
            'client_name' => 'Nuevo', 'client_phone' => '0990000000', 'device_type' => 'celular', 'declared_fault' => 'x',
            'unlock_type' => 'none', 'entry_date' => today()->toDateString(), 'labor_cost' => 50, 'amount_paid' => 500,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, OrderPayment::count());
        $this->assertEquals(0, Order::first()->amount_paid);
    }

    public function test_advance_payment_cannot_exceed_total(): void
    {
        $this->actingAs($this->user('receptionist', $this->gye))->post(route('orders.store'), [
            'client_name' => 'Nuevo', 'client_phone' => '0990000000', 'device_type' => 'celular', 'declared_fault' => 'x',
            'unlock_type' => 'none', 'entry_date' => today()->toDateString(), 'labor_cost' => 50, 'amount_paid' => 80,
        ])->assertSessionHasErrors('amount_paid');
        $this->assertSame(0, Order::count());
    }

    public function test_technician_client_search_is_limited(): void
    {
        Client::create(['name' => 'Ana Torres', 'phone' => '0991111111', 'email' => 'ana@x.ec', 'address' => 'Calle 1', 'client_type' => 'individual']);
        $tech = $this->user('technician', $this->gye);

        $this->actingAs($tech)->get(route('clients.search', ['q' => 'a']))->assertSessionHasErrors('q');
        $this->actingAs($tech)->getJson(route('clients.search', ['q' => 'Ana']))
            ->assertOk()->assertJsonPath('0.name', 'Ana Torres')->assertJsonPath('0.email', null)->assertJsonPath('0.address', null);

        $this->actingAs($this->user('receptionist', $this->gye))->getJson(route('clients.search', ['q' => 'Ana']))
            ->assertJsonPath('0.email', 'ana@x.ec');
    }

    // --- Cuenta ---

    public function test_deactivated_user_is_logged_out(): void
    {
        $desk = $this->user('receptionist', $this->gye);
        $this->actingAs($desk)->get(route('dashboard'))->assertOk();

        $desk->update(['is_active' => false]);
        $this->actingAs($desk->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_assigned_password_must_be_changed_on_first_login(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Nueva', 'email' => 'nueva@x.ec', 'password' => 'temporal123', 'password_confirmation' => 'temporal123',
            'role' => 'receptionist', 'branch_id' => $this->gye->id, 'is_active' => '1',
        ]);
        $new = User::where('email', 'nueva@x.ec')->first();
        $this->assertTrue($new->must_change_password);
        $this->flushSession(); // otra persona, otro navegador

        $this->actingAs($new)->get(route('orders.index'))->assertRedirect(route('password.edit'));
        $this->actingAs($new)->get(route('password.edit'))->assertOk()->assertSee('asignada por otra persona');

        $this->actingAs($new)->put(route('password.update'), [
            'current_password' => 'mala', 'password' => 'propia1234', 'password_confirmation' => 'propia1234',
        ])->assertSessionHasErrors('current_password');
        $this->actingAs($new)->put(route('password.update'), [
            'current_password' => 'temporal123', 'password' => 'propia1234', 'password_confirmation' => 'propia1234',
        ])->assertRedirect(route('dashboard'));

        $new->refresh();
        $this->assertFalse($new->must_change_password);
        $this->assertTrue(Hash::check('propia1234', $new->password));
        $this->actingAs($new)->get(route('orders.index'))->assertOk();
    }

    public function test_production_seed_forces_password_change(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('db:seed', ['--force' => true]);

        $this->assertTrue(User::where('email', 'admin@solarfix.ec')->first()->must_change_password);
        $this->assertSame(0, Order::count());
    }

    public function test_validation_messages_are_in_spanish(): void
    {
        app()->setLocale('es');

        $this->actingAs($this->user('admin'))->post(route('orders.store'), [])
            ->assertSessionHasErrors(['declared_fault' => 'El campo falla declarada es obligatorio.']);
    }
}
