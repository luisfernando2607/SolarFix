<?php

namespace Tests\Feature;

use App\Models\CashClosing;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappLog;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->role('admin')->create();
    }

    private function makeOrder(array $attributes = [], string $phone = '0991234567'): Order
    {
        $client = Client::create(['name' => 'Ana Torres', 'phone' => $phone, 'client_type' => 'individual']);

        return Order::create($attributes + [
            'client_id' => $client->id,
            'order_number' => 'TEST-' . uniqid(),
            'device_type' => 'celular',
            'declared_fault' => 'No enciende',
            'unlock_type' => 'none',
            'status' => 'diagnosing',
            'entry_date' => now()->toDateString(),
            'diagnosis_cost' => 10,
            'labor_cost' => 20,
            'total_amount' => 30,
            'balance_due' => 30,
        ]);
    }

    private function pay(Order $order, float $amount, string $method, $paidAt = null): OrderPayment
    {
        return OrderPayment::create([
            'order_id' => $order->id, 'amount' => $amount, 'method' => $method, 'paid_at' => $paidAt ?? now(),
        ]);
    }

    // --- WhatsApp ---

    public function test_phone_normalization(): void
    {
        $this->assertSame('593991234567', WhatsAppMessage::normalizePhone('0991234567'));
        $this->assertSame('593991234567', WhatsAppMessage::normalizePhone('099 123 4567'));
        $this->assertSame('593991234567', WhatsAppMessage::normalizePhone('+593 99 123 4567'));
        $this->assertSame('593991234567', WhatsAppMessage::normalizePhone('991234567'));
        $this->assertNull(WhatsAppMessage::normalizePhone('12345'));
        $this->assertNull(WhatsAppMessage::normalizePhone(null));
    }

    public function test_sending_budget_logs_message_and_waits_for_approval(): void
    {
        $order = $this->makeOrder(['diagnosis' => 'Pantalla dañada']);

        $response = $this->actingAs($this->admin())->post(route('orders.whatsapp', [$order, 'budget']));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/593991234567?text=', $response->headers->get('Location'));

        $log = WhatsappLog::first();
        $this->assertSame('budget', $log->trigger_event);
        $this->assertStringContainsString('Pantalla dañada', $log->message_body);
        $this->assertStringContainsString('$30.00', $log->message_body);

        $this->assertSame('waiting_approval', $order->fresh()->status);
        $this->assertTrue(OrderStatusHistory::where('order_id', $order->id)->where('to_status', 'waiting_approval')->exists());
    }

    public function test_delivery_message_includes_warranty(): void
    {
        $order = $this->makeOrder(['status' => 'delivered', 'warranty_days' => 30, 'delivery_date' => '2026-10-01']);

        $message = WhatsAppMessage::build('delivery', $order);

        $this->assertStringContainsString('30 días, hasta el 31/10/2026', $message);
    }

    public function test_whatsapp_requires_valid_phone(): void
    {
        $order = $this->makeOrder([], 'sin número');

        $this->actingAs($this->admin())->post(route('orders.whatsapp', [$order, 'ready']))
            ->assertSessionHas('error');

        $this->assertSame(0, WhatsappLog::count());
    }

    public function test_technician_cannot_message_other_technicians_order(): void
    {
        $tech = User::factory()->role('technician')->create();
        $other = User::factory()->role('technician')->create();
        $order = $this->makeOrder(['user_id' => $other->id]);

        $this->actingAs($tech)->post(route('orders.whatsapp', [$order, 'ready']))->assertForbidden();
    }

    public function test_unknown_message_type_returns_404(): void
    {
        $this->actingAs($this->admin())->post(route('orders.whatsapp', [$this->makeOrder(), 'otro']))->assertNotFound();
    }

    // --- Cierre de caja ---

    public function test_cash_page_totals_by_method_for_selected_day(): void
    {
        $order = $this->makeOrder();
        $this->pay($order, 10, 'cash');
        $this->pay($order, 5.5, 'cash');
        $this->pay($order, 20, 'transfer');
        $this->pay($order, 99, 'cash', now()->subDay());

        $this->actingAs($this->admin())->get(route('cash.index'))
            ->assertOk()
            ->assertViewHas('totals', fn ($t) => $t['cash'] == 15.5 && $t['transfer'] == 20 && $t['total'] == 35.5);
    }

    public function test_cash_difference_requires_justification(): void
    {
        $this->pay($this->makeOrder(), 50, 'cash');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('cash.store'), ['counted_cash' => 45])
            ->assertSessionHasErrors('notes');
        $this->assertSame(0, CashClosing::count());

        $this->actingAs($admin)->post(route('cash.store'), ['counted_cash' => 45, 'notes' => 'Vuelto mal dado'])
            ->assertSessionHasNoErrors();

        $closing = CashClosing::first();
        $this->assertEquals(50, $closing->expected_cash);
        $this->assertEquals(-5, $closing->difference);
        $this->assertSame($admin->id, $closing->closed_by);
    }

    public function test_cash_closing_can_be_updated_same_day(): void
    {
        $this->pay($this->makeOrder(), 50, 'cash');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('cash.store'), ['counted_cash' => 50]);
        $this->actingAs($admin)->post(route('cash.store'), ['counted_cash' => 40, 'notes' => 'Retiro para compra']);

        $this->assertSame(1, CashClosing::count());
        $this->assertEquals(-10, CashClosing::first()->difference);
    }

    public function test_technician_cannot_access_cash(): void
    {
        $this->actingAs(User::factory()->role('technician')->create())->get(route('cash.index'))->assertForbidden();
    }

    public function test_cannot_close_future_date(): void
    {
        $this->actingAs($this->admin())->get(route('cash.index', ['date' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('date');
    }

    // --- Configuración e IVA ---

    public function test_admin_updates_settings(): void
    {
        $this->actingAs($this->admin())->put(route('settings.update'), [
            'company_name' => 'Taller Solar',
            'default_warranty_days' => 60,
            'warranty_alert_days' => 10,
            'iva_percent' => 12,
            'prices_include_iva' => '0',
            'stale_order_days' => 7,
            'approval_alert_hours' => 48,
            'commission_base' => 'total',
            'order_prefix' => 'ORD',
            'invoice_prefix' => 'FAC',
            'currency_symbol' => '$',
            'diagnosis_cost_default' => 0,
            'maintenance_due_days' => 15,
        ])->assertRedirect(route('settings.edit'));

        Setting::flushCache();
        $this->assertSame('Taller Solar', Setting::get('company_name'));
        $this->assertSame('12', Setting::get('iva_percent'));
        $this->assertSame('0', Setting::get('prices_include_iva'));
    }

    public function test_receptionist_cannot_change_settings(): void
    {
        $this->actingAs(User::factory()->role('receptionist')->create())->get(route('settings.edit'))->assertForbidden();
    }

    public function test_invoice_from_order_breaks_down_included_iva(): void
    {
        $order = $this->makeOrder(['total_amount' => 30]);

        $this->actingAs($this->admin())->post(route('invoices.generate-from-order', $order));

        $invoice = Invoice::where('order_id', $order->id)->first();
        $this->assertEquals(15, $invoice->iva_percent);
        $this->assertEquals(26.09, $invoice->subtotal); // 8.70 + 17.39
        $this->assertEquals(3.91, $invoice->iva_amount);
        $this->assertEquals(30, $invoice->total);       // igual al total de la orden
        $this->assertEquals(26.09, $invoice->items->sum('subtotal'));
    }

    public function test_invoice_from_order_adds_iva_when_prices_exclude_it(): void
    {
        Setting::put('prices_include_iva', '0', 'billing');
        $order = $this->makeOrder(['total_amount' => 30]);

        $this->actingAs($this->admin())->post(route('invoices.generate-from-order', $order));

        $invoice = Invoice::where('order_id', $order->id)->first();
        $this->assertEquals(30, $invoice->subtotal);
        $this->assertEquals(4.5, $invoice->iva_amount);
        $this->assertEquals(34.5, $invoice->total);
    }

    // --- Dashboard ---

    public function test_dashboard_shows_stale_and_unanswered_orders(): void
    {
        $stale = $this->makeOrder(['status' => 'repairing']);
        Order::whereKey($stale->id)->update(['updated_at' => now()->subDays(10)]);

        $waiting = $this->makeOrder(['status' => 'waiting_approval']);
        OrderStatusHistory::create([
            'order_id' => $waiting->id, 'from_status' => 'diagnosing', 'to_status' => 'waiting_approval',
            'changed_at' => now()->subDays(4),
        ]);

        $recent = $this->makeOrder(['status' => 'waiting_approval']);
        OrderStatusHistory::create([
            'order_id' => $recent->id, 'from_status' => 'diagnosing', 'to_status' => 'waiting_approval',
            'changed_at' => now()->subHour(),
        ]);

        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('staleOrders', fn ($orders) => $orders->pluck('id')->all() === [$stale->id])
            ->assertViewHas('awaitingApproval', fn ($orders) => $orders->pluck('id')->values()->all() === [$waiting->id]);
    }

    public function test_main_new_screens_render(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();
        WhatsappLog::create([
            'order_id' => $order->id, 'client_id' => $order->client_id, 'trigger_event' => 'ready',
            'phone_to' => '593991234567', 'message_body' => 'x', 'sent_by' => $admin->id, 'sent_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('settings.edit'))->assertOk();
        $this->actingAs($admin)->get(route('orders.show', $order))->assertOk()->assertSee('Equipo listo');
        $this->actingAs($admin)->get(route('orders.create'))->assertOk();
        $this->actingAs($admin)->get(route('invoices.create'))->assertOk();
    }
}
