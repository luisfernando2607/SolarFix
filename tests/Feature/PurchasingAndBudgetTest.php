<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\SparePart;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingAndBudgetTest extends TestCase
{
    use RefreshDatabase;

    private function part(string $name, int $stock, float $cost, int $min = 2): SparePart
    {
        $part = SparePart::create(['name' => $name, 'code' => strtoupper(substr($name, 0, 3)) . '-' . uniqid(), 'category' => 'X', 'cost_price' => $cost, 'sale_price' => $cost * 2, 'min_stock' => $min]);
        $part->setBranchStock(Branch::mainId(), $stock);
        $part->update(['stock_quantity' => $stock]);

        return $part;
    }

    public function test_budget_pdf_for_staff_and_client(): void
    {
        $desk = User::factory()->role('receptionist')->create();
        $client = Client::create(['name' => 'Ana', 'phone' => '0991234567', 'client_type' => 'individual']);
        $order = Order::create(['client_id' => $client->id, 'order_number' => 'T-1', 'device_type' => 'celular', 'declared_fault' => 'No carga',
            'unlock_type' => 'none', 'status' => 'waiting_approval', 'entry_date' => today(), 'diagnosis' => 'Pin dañado', 'labor_cost' => 30]);
        $order->recalculateTotals();

        $this->actingAs($desk)->get(route('orders.budget', $order))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($desk)->get(route('orders.show', $order))->assertSee(route('orders.budget', $order));

        $this->flushSession();
        $this->get(route('tracking.show', $order->tracking_token))->assertSee('Descargar presupuesto');
        $this->get(route('tracking.budget', $order->tracking_token))->assertOk()->assertHeader('content-type', 'application/pdf');

        $order->update(['status' => 'repairing']);
        $this->get(route('tracking.budget', $order->tracking_token))->assertNotFound(); // solo mientras espera respuesta

        $html = view('orders.budget', [
            'order' => $order, 'company' => \App\Support\Company::info(), 'concepts' => \App\Services\WhatsAppMessage::budgetConcepts($order),
            'validDays' => 15, 'pricesIncludeIva' => true, 'onlineApproval' => true,
        ])->render();
        $this->assertStringContainsString('Pin dañado', $html);
        $this->assertStringContainsString(now()->addDays(15)->format('d/m/Y'), $html);
    }

    public function test_purchase_order_from_suggestion_to_reception(): void
    {
        $admin = User::factory()->role('admin')->create();
        $supplier = Supplier::create(['name' => 'Repuestos Andinos', 'phone' => '0998887777', 'is_active' => true]);
        $battery = $this->part('Batería', 0, 10);
        app(InventoryService::class)->receive($battery, 12, 9.5, null, null, $admin->id, $supplier->id);
        app(InventoryService::class)->sell(Branch::mainId(), [['spare_part_id' => $battery->id, 'quantity' => 9]], ['method' => 'cash'], null);

        // Desde compras sugeridas: borrador con lo sugerido (2 unidades a 9,50).
        $this->actingAs($admin)->post(route('purchase-orders.store'), ['supplier_id' => $supplier->id, 'from_suggestion' => 1]);
        $po = PurchaseOrder::first();
        $this->assertSame('draft', $po->status);
        $this->assertStringStartsWith('OC-', $po->number);
        $item = $po->items()->first();
        $this->assertSame(2, $item->quantity);
        $this->assertEquals(9.5, $item->unit_cost);

        // Editar el borrador: más cantidad y otro repuesto.
        $screen = $this->part('Pantalla', 0, 20);
        $this->actingAs($admin)->put(route('purchase-orders.update', $po), [
            'supplier_id' => $supplier->id, 'items' => [$item->id => ['quantity' => 5, 'unit_cost' => 9.5]],
            'add_part_id' => $screen->id, 'add_quantity' => 3,
        ])->assertSessionHas('success');
        $this->assertSame(2, $po->items()->count());

        $this->actingAs($admin)->get(route('purchase-orders.pdf', $po))->assertOk();
        $this->actingAs($admin)->patch(route('purchase-orders.send', $po));
        $this->assertSame('sent', $po->fresh()->status);
        $this->actingAs($admin)->post(route('purchase-orders.whatsapp', $po))->assertRedirectContains('wa.me/593998887777');

        // Llegaron 5 baterías y solo 2 pantallas, a otro costo.
        $screenItem = $po->items()->where('spare_part_id', $screen->id)->first();
        $this->actingAs($admin)->post(route('purchase-orders.receive', $po), ['received' => [
            $item->id => ['quantity' => 5, 'unit_cost' => 9.5],
            $screenItem->id => ['quantity' => 2, 'unit_cost' => 22],
        ]])->assertSessionHas('success');

        $po->refresh();
        $this->assertSame('received', $po->status);
        $this->assertSame(8, $battery->fresh()->stockIn(Branch::mainId()));
        $this->assertSame(2, $screen->fresh()->stockIn(Branch::mainId()));
        $this->assertEquals(22, $screen->fresh()->cost_price);
        $this->assertSame($supplier->id, $screen->movements()->latest('id')->value('supplier_id'));

        // Una orden recibida no se recibe otra vez.
        $this->actingAs($admin)->post(route('purchase-orders.receive', $po), ['received' => [$item->id => ['quantity' => 5, 'unit_cost' => 9.5]]])->assertStatus(422);
        $this->assertSame(8, $battery->fresh()->stockIn(Branch::mainId()));
    }

    public function test_purchase_orders_are_admin_only_and_per_branch(): void
    {
        $this->actingAs(User::factory()->role('technician')->create())->get(route('purchase-orders.index'))->assertForbidden();

        $admin = User::factory()->role('admin')->create();
        $this->actingAs($admin)->post(route('purchase-orders.store'));
        $this->actingAs($admin)->get(route('purchase-orders.index'))->assertOk()->assertSee(PurchaseOrder::first()->number);
        $this->actingAs($admin)->patch(route('purchase-orders.cancel', PurchaseOrder::first()));
        $this->assertSame('cancelled', PurchaseOrder::first()->status);
    }
}
