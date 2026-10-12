<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->role('admin')->create();
    }

    private function makeOrder(array $attributes = []): Order
    {
        $client = Client::create(['name' => 'María Paz', 'phone' => '0991234567', 'client_type' => 'individual']);

        return Order::create($attributes + [
            'client_id' => $client->id,
            'order_number' => 'TEST-' . uniqid(),
            'device_type' => 'celular',
            'declared_fault' => 'No enciende',
            'unlock_type' => 'pin',
            'unlock_value' => '9999',
            'status' => 'repairing',
            'entry_date' => now()->toDateString(),
            'labor_cost' => 40,
            'total_amount' => 40,
            'balance_due' => 40,
        ]);
    }

    public function test_cost_change_is_audited_with_old_and_new_values(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();

        $this->actingAs($admin)->put(route('orders.update', $order), [
            'client_id' => $order->client_id,
            'device_type' => 'celular',
            'declared_fault' => 'No enciende',
            'unlock_type' => 'pin',
            'unlock_value' => '9999',
            'entry_date' => now()->toDateString(),
            'labor_cost' => 25,
        ]);

        $log = AuditLog::where('event', 'updated')->where('auditable_type', 'Order')->latest('id')->first();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($order->id, $log->order_id);
        $this->assertEquals(40, $log->old_values['labor_cost']);
        $this->assertEquals(25, $log->new_values['labor_cost']);
        $this->assertSame('Orden ' . $order->order_number, $log->description);
    }

    public function test_sensitive_values_are_masked(): void
    {
        $order = $this->makeOrder();
        $order->update(['unlock_value' => '1111']);
        $user = User::factory()->create();
        $user->update(['password' => 'otra-clave-segura']);

        foreach (AuditLog::all() as $log) {
            $json = json_encode([$log->old_values, $log->new_values]);
            $this->assertStringNotContainsString('9999', $json);
            $this->assertStringNotContainsString('1111', $json);
            $this->assertStringNotContainsString('otra-clave-segura', $json);
            $this->assertStringNotContainsString('$2y$', $json);
        }

        $passwordLog = AuditLog::where('auditable_type', 'User')->where('event', 'updated')->first();
        $this->assertSame('[oculto]', $passwordLog->new_values['password']);
    }

    public function test_deleted_payment_is_audited_and_linked_to_order(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();
        $payment = OrderPayment::create(['order_id' => $order->id, 'amount' => 20, 'method' => 'cash', 'paid_at' => now()]);

        $this->actingAs($admin)->delete(route('orders.payments.destroy', [$order, $payment]));

        $log = AuditLog::where('event', 'deleted')->where('auditable_type', 'OrderPayment')->first();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($order->id, $log->order_id);
        $this->assertEquals(20, $log->old_values['amount']);
        $this->assertStringContainsString($order->order_number, $log->description);
    }

    public function test_invoice_cancellation_is_audited(): void
    {
        $admin = $this->admin();
        $invoice = Invoice::createWithUniqueNumber(['client_name' => 'X', 'subtotal' => 10, 'issued_at' => now(), 'status' => 'emitida']);

        $this->actingAs($admin)->patch(route('invoices.cancel', $invoice));

        $log = AuditLog::where('auditable_type', 'Invoice')->where('event', 'updated')->first();
        $this->assertSame('emitida', $log->old_values['status']);
        $this->assertSame('anulada', $log->new_values['status']);
    }

    public function test_settings_change_is_audited_with_key(): void
    {
        $this->actingAs($this->admin())->put(route('settings.update'), [
            'company_name' => 'SolarFix',
            'default_warranty_days' => 30,
            'warranty_alert_days' => 15,
            'iva_percent' => 12,
            'prices_include_iva' => '1',
            'stale_order_days' => 5,
            'approval_alert_hours' => 72,
        ]);

        $this->assertTrue(AuditLog::where('auditable_type', 'Setting')->where('description', 'Configuración: iva_percent')->exists());
    }

    public function test_unchanged_save_is_not_audited(): void
    {
        $order = $this->makeOrder();
        $count = AuditLog::count();

        $order->save();
        $order->update(['labor_cost' => 40]);

        $this->assertSame($count, AuditLog::count());
    }

    public function test_logins_and_failed_attempts_are_audited(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta']);
        $failed = AuditLog::where('event', 'login_failed')->first();
        $this->assertSame($user->id, $failed->user_id);
        $this->assertSame('Intento con ' . $user->email, $failed->description);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertTrue(AuditLog::where('event', 'login')->where('user_id', $user->id)->exists());
    }

    public function test_only_admins_can_view_audit(): void
    {
        $order = $this->makeOrder();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('audit.index'))->assertOk()->assertSee($order->order_number);
        $this->actingAs($admin)->get(route('audit.index', ['type' => 'Order', 'event' => 'created', 'q' => $order->order_number]))->assertOk();

        foreach (['receptionist', 'technician'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get(route('audit.index'))->assertForbidden();
        }
    }

    public function test_order_activity_visible_only_to_admins(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin())->get(route('orders.show', $order))->assertSee('Actividad');
        $this->actingAs(User::factory()->role('receptionist')->create())->get(route('orders.show', $order))
            ->assertOk()
            ->assertDontSee('Visible solo para administradores');
    }
}
