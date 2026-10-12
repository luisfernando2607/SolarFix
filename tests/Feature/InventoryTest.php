<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderPart;
use App\Models\SparePart;
use App\Models\SparePartMovement;
use App\Models\User;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->role('admin')->create();
    }

    private function part(int $stock = 5, array $attributes = []): SparePart
    {
        $part = SparePart::create($attributes + [
            'name' => 'Pantalla A15',
            'code' => 'PAN-' . uniqid(),
            'category' => 'Pantallas',
            'cost_price' => 20,
            'sale_price' => 45,
            'min_stock' => 2,
        ]);
        $part->setBranchStock(\App\Models\Branch::mainId(), $stock);

        return $part;
    }

    private function makeOrder(array $attributes = []): Order
    {
        $client = Client::create(['name' => 'Rosa Mora', 'phone' => '0991234567', 'client_type' => 'individual']);

        return Order::create($attributes + [
            'client_id' => $client->id,
            'order_number' => 'TEST-' . uniqid(),
            'device_type' => 'celular',
            'declared_fault' => 'Pantalla rota',
            'unlock_type' => 'none',
            'status' => 'repairing',
            'entry_date' => now()->toDateString(),
            'labor_cost' => 15,
            'total_amount' => 15,
            'balance_due' => 15,
        ]);
    }

    // --- Catálogo y movimientos ---

    public function test_admin_creates_part_with_initial_stock(): void
    {
        $this->actingAs($this->admin())->post(route('spare-parts.store'), [
            'name' => 'Batería iPhone 12', 'code' => 'BAT-IP12', 'cost_price' => 18, 'sale_price' => 40,
            'min_stock' => 2, 'initial_stock' => 6,
        ])->assertSessionHasNoErrors();

        $part = SparePart::where('code', 'BAT-IP12')->first();
        $this->assertSame(6, $part->stock_quantity);
        $this->assertSame('Stock inicial', $part->movements()->first()->reason);
    }

    public function test_stock_cannot_be_set_through_the_form(): void
    {
        $part = $this->part(5);

        $this->actingAs($this->admin())->put(route('spare-parts.update', $part), [
            'name' => $part->name, 'cost_price' => 20, 'sale_price' => 45, 'min_stock' => 2, 'stock_quantity' => 999,
        ]);

        $this->assertSame(5, $part->fresh()->stock_quantity);
    }

    public function test_purchase_entry_adds_stock_updates_cost_and_clears_low_stock(): void
    {
        $part = $this->part(1);
        $this->assertTrue($part->isLowStock());

        $this->actingAs(User::factory()->role('technician')->create())
            ->post(route('spare-parts.receive', $part), ['quantity' => 10, 'unit_cost' => 22, 'reason' => 'Proveedor XYZ'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Ya no está en stock bajo'));

        $part->refresh();
        $this->assertSame(11, $part->stock_quantity);
        $this->assertEquals(21.82, $part->cost_price); // costo promedio: (1×20 + 10×22) / 11
        $this->assertEquals(45, $part->sale_price); // el técnico no cambia precios
        $this->assertFalse($part->isLowStock());
    }

    public function test_adjustment_requires_reason_and_records_delta(): void
    {
        $part = $this->part(10);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('spare-parts.adjust', $part), ['counted_quantity' => 7])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post(route('spare-parts.adjust', $part), ['counted_quantity' => 7, 'reason' => 'Conteo mensual']);

        $this->assertSame(7, $part->fresh()->stock_quantity);
        $movement = SparePartMovement::where('type', 'adjustment')->first();
        $this->assertSame(-3, $movement->quantity);
    }

    public function test_technician_cannot_adjust_or_edit_prices(): void
    {
        $part = $this->part(10);
        $tech = User::factory()->role('technician')->create();

        $this->actingAs($tech)->post(route('spare-parts.adjust', $part), ['counted_quantity' => 0, 'reason' => 'x'])->assertForbidden();
        $this->actingAs($tech)->get(route('spare-parts.edit', $part))->assertForbidden();
        $this->actingAs($tech)->get(route('spare-parts.show', $part))->assertOk();
    }

    public function test_receptionist_cannot_register_entries(): void
    {
        $this->actingAs(User::factory()->role('receptionist')->create())
            ->post(route('spare-parts.receive', $this->part()), ['quantity' => 1, 'unit_cost' => 1])
            ->assertForbidden();
    }

    public function test_part_with_movements_cannot_be_deleted(): void
    {
        $part = $this->part(0);
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('spare-parts.receive', $part), ['quantity' => 1, 'unit_cost' => 20]);

        $this->actingAs($admin)->delete(route('spare-parts.destroy', $part))->assertSessionHas('error');
        $this->assertModelExists($part);
    }

    // --- Repuestos en órdenes ---

    public function test_adding_part_to_order_discounts_stock_and_updates_totals(): void
    {
        $part = $this->part(5);
        $order = $this->makeOrder();

        $this->actingAs($this->admin())->post(route('orders.parts.store', $order), ['spare_part_id' => $part->id, 'quantity' => 2])
            ->assertSessionHas('success');

        $this->assertSame(3, $part->fresh()->stock_quantity);
        $order->refresh();
        $this->assertEquals(90, $order->parts_cost);
        $this->assertEquals(105, $order->total_amount);
        $this->assertEquals(105, $order->balance_due);

        $movement = SparePartMovement::where('type', 'out')->first();
        $this->assertSame($order->id, $movement->order_id);
        $this->assertSame(2, $movement->quantity);
    }

    public function test_insufficient_stock_is_rejected(): void
    {
        $part = $this->part(1);
        $order = $this->makeOrder();

        $this->actingAs($this->admin())->post(route('orders.parts.store', $order), ['spare_part_id' => $part->id, 'quantity' => 3])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Stock insuficiente'));

        $this->assertSame(1, $part->fresh()->stock_quantity);
        $this->assertSame(0, OrderPart::count());
    }

    public function test_removing_part_returns_stock(): void
    {
        $part = $this->part(5);
        $order = $this->makeOrder();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('orders.parts.store', $order), ['spare_part_id' => $part->id, 'quantity' => 2]);

        $this->actingAs($admin)->delete(route('orders.parts.destroy', [$order, OrderPart::first()]));

        $this->assertSame(5, $part->fresh()->stock_quantity);
        $this->assertEquals(15, $order->fresh()->total_amount);
    }

    public function test_warranty_order_parts_are_free_but_discount_stock(): void
    {
        $part = $this->part(5);
        $original = $this->makeOrder(['status' => 'delivered']);
        $claim = $this->makeOrder(['parent_order_id' => $original->id, 'status' => 'warranty', 'labor_cost' => 0, 'total_amount' => 0]);

        $this->actingAs($this->admin())->post(route('orders.parts.store', $claim), ['spare_part_id' => $part->id, 'quantity' => 1]);

        $this->assertSame(4, $part->fresh()->stock_quantity);
        $this->assertEquals(0, $claim->fresh()->total_amount);
    }

    public function test_closed_order_parts_cannot_change(): void
    {
        $order = $this->makeOrder(['status' => 'delivered']);

        $this->actingAs($this->admin())->post(route('orders.parts.store', $order), ['spare_part_id' => $this->part()->id, 'quantity' => 1])
            ->assertSessionHas('error');
    }

    public function test_technician_cannot_add_parts_to_other_technicians_order(): void
    {
        $order = $this->makeOrder(['user_id' => User::factory()->role('technician')->create()->id]);

        $this->actingAs(User::factory()->role('technician')->create())
            ->post(route('orders.parts.store', $order), ['spare_part_id' => $this->part()->id, 'quantity' => 1])
            ->assertForbidden();
    }

    public function test_editing_order_keeps_inventory_parts_cost(): void
    {
        $part = $this->part(5);
        $order = $this->makeOrder();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('orders.parts.store', $order), ['spare_part_id' => $part->id, 'quantity' => 1]);

        $this->actingAs($admin)->put(route('orders.update', $order), [
            'client_id' => $order->client_id, 'device_type' => 'celular', 'declared_fault' => 'Pantalla rota',
            'unlock_type' => 'none', 'entry_date' => now()->toDateString(), 'labor_cost' => 15,
            'parts_cost' => 1, // debe ignorarse
        ]);

        $this->assertEquals(45, $order->fresh()->parts_cost);
        $this->assertEquals(60, $order->fresh()->total_amount);
    }

    public function test_payment_recalculation_keeps_working(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin())->post(route('orders.payments.store', $order), ['amount' => 10, 'method' => 'cash']);

        $order->refresh();
        $this->assertEquals(10, $order->amount_paid);
        $this->assertEquals(5, $order->balance_due);
    }

    // --- Factura, WhatsApp, búsqueda y pantallas ---

    public function test_invoice_lists_each_part_and_matches_order_total(): void
    {
        $order = $this->makeOrder();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('orders.parts.store', $order), ['spare_part_id' => $this->part(5, ['name' => 'Pantalla A15'])->id, 'quantity' => 2]);
        $this->actingAs($admin)->post(route('orders.parts.store', $order), ['spare_part_id' => $this->part(5, ['name' => 'Flex de carga', 'sale_price' => 12])->id, 'quantity' => 1]);

        $this->actingAs($admin)->post(route('invoices.generate-from-order', $order));

        $invoice = Invoice::where('order_id', $order->id)->first();
        $descriptions = $invoice->items->pluck('description')->all();
        $this->assertContains('Pantalla A15', $descriptions);
        $this->assertContains('Flex de carga', $descriptions);
        $this->assertEquals(2, $invoice->items->firstWhere('description', 'Pantalla A15')->quantity);
        // Precios con IVA incluido: el total de la factura coincide con el de la orden.
        $this->assertEquals($order->fresh()->total_amount, $invoice->total);
        $this->assertEquals(round($invoice->items->sum('subtotal'), 2), (float) $invoice->subtotal);
    }

    public function test_whatsapp_budget_lists_parts(): void
    {
        $order = $this->makeOrder();
        $this->actingAs($this->admin())->post(route('orders.parts.store', $order), ['spare_part_id' => $this->part()->id, 'quantity' => 1]);

        $message = WhatsAppMessage::build('budget', $order->fresh());

        $this->assertStringContainsString('Pantalla A15 x1: $45.00', $message);
    }

    public function test_part_search_returns_active_parts_only(): void
    {
        $this->part(3, ['name' => 'Pantalla Moto G54']);
        $this->part(3, ['name' => 'Pantalla Moto G24', 'is_active' => false]);

        $this->actingAs(User::factory()->role('technician')->create())
            ->getJson(route('spare-parts.search', ['q' => 'Moto']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Pantalla Moto G54');
    }

    public function test_screens_render(): void
    {
        $part = $this->part(1);
        $order = $this->makeOrder();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('orders.parts.store', $order), ['spare_part_id' => $part->id, 'quantity' => 1]);

        $this->actingAs($admin)->get(route('spare-parts.index'))->assertOk()->assertSee('Pantalla A15');
        $this->actingAs($admin)->get(route('spare-parts.index', ['low' => 1, 'q' => 'Pant']))->assertOk();
        $this->actingAs($admin)->get(route('spare-parts.create'))->assertOk();
        $this->actingAs($admin)->get(route('spare-parts.show', $part))->assertOk()->assertSee($order->order_number);
        $this->actingAs($admin)->get(route('spare-parts.edit', $part))->assertOk();
        $this->actingAs($admin)->get(route('orders.show', $order))->assertOk()->assertSee('Agregar repuesto');
        $this->actingAs($admin)->get(route('orders.edit', $order))->assertOk()->assertSee('Suma de los repuestos del inventario');
        $this->actingAs($admin)->get(route('orders.pdf', $order))->assertOk();
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Repuestos con stock bajo');
    }

    public function test_parts_return_to_stock_when_order_closed_without_repair(): void
    {
        $part = $this->part(5);
        $order = $this->makeOrder(['status' => 'waiting_approval']);
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('orders.parts.store', $order), ['spare_part_id' => $part->id, 'quantity' => 2]);
        $this->actingAs($admin)->get(route('spare-parts.show', $part))->assertViewHas('reserved', 2);

        $this->actingAs($admin)->put(route('orders.update', $order), [
            'client_id' => $order->client_id, 'device_type' => 'celular', 'declared_fault' => 'Pantalla rota',
            'unlock_type' => 'none', 'entry_date' => now()->toDateString(), 'labor_cost' => 15, 'status' => 'closed_no_repair',
        ]);

        $this->assertSame(5, $part->fresh()->stock_quantity);
        $this->assertSame(0, $order->parts()->count());
        $this->assertEquals(0, $order->fresh()->parts_cost);
        $this->assertStringContainsString('cerrada sin reparación', SparePartMovement::latest('id')->first()->reason);
    }

    public function test_parts_return_to_stock_when_order_deleted(): void
    {
        $part = $this->part(5);
        $order = $this->makeOrder();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('orders.parts.store', $order), ['spare_part_id' => $part->id, 'quantity' => 3]);

        $this->actingAs($admin)->delete(route('orders.destroy', $order));

        $this->assertSame(5, $part->fresh()->stock_quantity);
    }
}
