<?php

namespace Tests\Feature;

use App\Models\CashClosing;
use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Commission;
use App\Models\CommissionAdvance;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Setting;
use App\Models\SparePart;
use App\Models\User;
use App\Services\InventoryService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->role('admin')->create();
    }

    private function makeOrder(array $attributes = []): Order
    {
        $client = Client::create(['name' => 'Diego Salazar', 'phone' => '0991234567', 'client_type' => 'individual']);

        $order = Order::create($attributes + [
            'client_id' => $client->id, 'order_number' => 'TEST-' . uniqid(), 'device_type' => 'celular',
            'declared_fault' => 'No enciende', 'unlock_type' => 'none', 'status' => 'repairing',
            'entry_date' => now()->toDateString(), 'labor_cost' => 100,
        ]);
        $order->recalculateTotals();

        return $order;
    }

    private function part(int $stock): SparePart
    {
        $part = SparePart::create(['name' => 'Pantalla', 'code' => 'PAN-' . uniqid(), 'category' => 'Pantallas', 'cost_price' => 20, 'sale_price' => 30, 'min_stock' => 1]);
        $part->setBranchStock(\App\Models\Branch::mainId(), $stock);

        return $part;
    }

    private function editPayload(Order $order, array $overrides = []): array
    {
        return $overrides + [
            'client_id' => $order->client_id, 'device_type' => 'celular', 'declared_fault' => 'No enciende',
            'unlock_type' => 'none', 'entry_date' => $order->entry_date->toDateString(),
            'diagnosis_cost' => (float) $order->diagnosis_cost, 'labor_cost' => (float) $order->labor_cost,
            'status' => $order->status,
        ];
    }

    // --- Órdenes entregadas ---

    public function test_delivered_order_keeps_status_and_installed_parts(): void
    {
        $part = $this->part(5);
        $order = $this->makeOrder(['status' => 'ready', 'warranty_days' => 30]);
        app(InventoryService::class)->addToOrder($order, $part, 2, null);
        $order->fresh()->markDelivered(null);

        $this->actingAs($this->admin())->put(route('orders.update', $order), $this->editPayload($order->fresh(), ['status' => 'closed_no_repair']))
            ->assertSessionHasErrors('status');

        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame(3, $part->fresh()->stock_quantity); // no volvieron al stock
        $this->assertSame('active', $order->fresh()->warranty->currentStatus());
    }

    // --- Saldo a favor y devoluciones ---

    public function test_overpayment_shows_credit_and_can_be_refunded(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();
        OrderPayment::register($order, 100, 'cash', $admin->id);

        // Baja la mano de obra después del anticipo.
        $this->actingAs($admin)->put(route('orders.update', $order), $this->editPayload($order->fresh(), ['labor_cost' => 60]));
        $order->refresh();
        $this->assertEquals(0, $order->balance_due);
        $this->assertEquals(40, $order->creditDue());
        $this->actingAs($admin)->get(route('orders.show', $order))->assertSee('Saldo a favor del cliente');

        $this->actingAs($admin)->post(route('orders.refunds.store', $order), ['amount' => 50, 'method' => 'cash'])
            ->assertSessionHas('error');
        $this->actingAs($admin)->post(route('orders.refunds.store', $order), ['amount' => 40, 'method' => 'cash'])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals(60, $order->amount_paid);
        $this->assertEquals(0, $order->creditDue());
        $this->assertEquals(-40, OrderPayment::where('amount', '<', 0)->value('amount'));
    }

    public function test_payment_never_exceeds_balance(): void
    {
        $order = $this->makeOrder();
        OrderPayment::register($order, 80, 'cash', null);

        $this->expectException(DomainException::class);
        OrderPayment::register(Order::find($order->id), 30, 'cash', null); // instancia con saldo viejo: se valida con la orden bloqueada
    }

    // --- Facturas ---

    public function test_invoice_locks_costs_and_parts(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();
        $this->actingAs($admin)->post(route('invoices.generate-from-order', $order));
        $this->assertSame(1, Invoice::count());

        $this->actingAs($admin)->put(route('orders.update', $order), $this->editPayload($order->fresh(), ['labor_cost' => 150]))
            ->assertSessionHasErrors('labor_cost');
        $this->assertEquals(100, $order->fresh()->labor_cost);

        $this->actingAs($admin)->patch(route('orders.work', $order), ['labor_cost' => 150])->assertSessionHas('error');

        $part = $this->part(5);
        $this->actingAs($admin)->post(route('orders.parts.store', $order), ['spare_part_id' => $part->id, 'quantity' => 1])
            ->assertSessionHas('error');
        $this->assertSame(0, $order->parts()->count());

        // Editar otros datos sí se puede.
        $this->actingAs($admin)->put(route('orders.update', $order), $this->editPayload($order->fresh(), ['notes' => 'Cliente llamó']))
            ->assertSessionHasNoErrors();
    }

    public function test_generating_twice_keeps_one_active_invoice(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();

        $this->actingAs($admin)->post(route('invoices.generate-from-order', $order));
        $this->actingAs($admin)->post(route('invoices.generate-from-order', $order))->assertSessionHas('info');

        $this->assertSame(1, Invoice::where('order_id', $order->id)->count());
    }

    public function test_invoice_follows_payments(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();
        $this->actingAs($admin)->post(route('invoices.generate-from-order', $order));
        $invoice = Invoice::first();

        $this->actingAs($admin)->patch(route('invoices.mark-paid', $invoice))->assertSessionHas('error');
        $this->assertSame('emitida', $invoice->fresh()->status);

        $payment = OrderPayment::register($order, 100, 'cash', $admin->id);
        $this->assertSame('pagada', $invoice->fresh()->status);

        $this->actingAs($admin)->delete(route('orders.payments.destroy', [$order, $payment]));
        $this->assertSame('emitida', $invoice->fresh()->status);
    }

    public function test_invoice_of_paid_order_is_born_paid(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();
        OrderPayment::register($order, 100, 'cash', $admin->id);

        $this->actingAs($admin)->post(route('invoices.generate-from-order', $order));

        $this->assertSame('pagada', Invoice::first()->status);
    }

    public function test_iva_excluded_is_added_to_order_balance_and_removed_on_cancel(): void
    {
        Setting::put('iva_percent', '15');
        Setting::put('prices_include_iva', '0');
        $admin = $this->admin();
        $order = $this->makeOrder();
        OrderPayment::register($order, 100, 'cash', $admin->id);

        $this->actingAs($admin)->post(route('invoices.generate-from-order', $order));
        $invoice = Invoice::first();
        $order->refresh();

        $this->assertEquals(115, $invoice->total);
        $this->assertEquals(15, $order->tax_amount);
        $this->assertEquals(115, $order->total_amount);
        $this->assertEquals(15, $order->balance_due);
        $this->assertSame('emitida', $invoice->status);

        OrderPayment::register($order, 15, 'cash', $admin->id);
        $this->assertSame('pagada', $invoice->fresh()->status);

        $this->actingAs($admin)->patch(route('invoices.cancel', $invoice));
        $order->refresh();
        $this->assertEquals(0, $order->tax_amount);
        $this->assertEquals(100, $order->total_amount);
        $this->assertEquals(15, $order->creditDue());
    }

    // --- Anular órdenes con dinero ---

    public function test_order_with_money_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();
        $payment = OrderPayment::register($order, 20, 'cash', $admin->id);

        $this->actingAs($admin)->delete(route('orders.destroy', $order))->assertSessionHas('error');
        $this->assertNotSoftDeleted($order);

        $this->actingAs($admin)->delete(route('orders.payments.destroy', [$order, $payment]));
        $this->actingAs($admin)->post(route('invoices.generate-from-order', $order));
        $this->actingAs($admin)->delete(route('orders.destroy', $order))->assertSessionHas('error');
        $this->assertNotSoftDeleted($order);

        $this->actingAs($admin)->patch(route('invoices.cancel', Invoice::first()));
        $this->actingAs($admin)->delete(route('orders.destroy', $order));
        $this->assertSoftDeleted($order);
    }

    // --- Caja cerrada ---

    public function test_closed_past_day_cannot_receive_or_lose_money(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();
        $yesterday = today()->subDay();
        $old = OrderPayment::register($order, 10, 'cash', $admin->id, null, null, $yesterday->copy()->setTime(10, 0));
        CashClosing::create(['branch_id' => \App\Models\Branch::mainId(), 'closing_date' => $yesterday, 'expected_cash' => 10, 'counted_cash' => 10, 'difference' => 0, 'closed_by' => $admin->id]);

        $this->actingAs($admin)->post(route('orders.payments.store', $order), ['amount' => 5, 'method' => 'cash', 'paid_at' => $yesterday->toDateString()])
            ->assertSessionHas('error');
        $this->actingAs($admin)->post(route('orders.payments.store', $order), ['amount' => 5, 'method' => 'cash', 'paid_at' => today()->addDay()->toDateString()])
            ->assertSessionHasErrors('paid_at');
        $this->actingAs($admin)->delete(route('orders.payments.destroy', [$order, $old]))->assertSessionHas('error');
        $this->assertSame(1, OrderPayment::count());

        $this->actingAs($admin)->post(route('cash.movements.store'), ['date' => $yesterday->toDateString(), 'type' => 'expense', 'amount' => 3, 'description' => 'Taxi'])
            ->assertSessionHas('error');
        $this->assertSame(0, CashMovement::count());

        // Hoy (aunque se haya cerrado) sigue recibiendo cobros: el cierre se vuelve a hacer.
        $this->actingAs($admin)->post(route('orders.payments.store', $order), ['amount' => 5, 'method' => 'cash'])
            ->assertSessionHas('success');
    }

    // --- Adelantos ---

    public function test_advance_from_month_without_commissions_is_discounted_later(): void
    {
        $admin = $this->admin();
        $tech = User::factory()->role('technician')->create(['commission_type' => 'fixed', 'commission_value' => 20]);
        $previous = now()->subMonthNoOverflow()->format('Y-m');
        CommissionAdvance::create(['user_id' => $tech->id, 'amount' => 15, 'period' => $previous, 'registered_by' => $admin->id]);

        $order = $this->makeOrder(['user_id' => $tech->id, 'status' => 'ready']);
        $order->markDelivered(null);
        $this->assertEquals(20, Commission::first()->commission_amount);

        $this->actingAs($admin)->post(route('commissions.pay'), ['user_id' => $tech->id, 'period' => now()->format('Y-m')])
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'menos adelantos'));

        $this->assertTrue(CommissionAdvance::first()->is_settled);
    }
}
