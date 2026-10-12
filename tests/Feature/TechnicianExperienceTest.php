<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderNote;
use App\Models\OrderPhoto;
use App\Models\OrderStatusHistory;
use App\Models\Setting;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TechnicianExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function tech(): User
    {
        return User::factory()->role('technician')->create();
    }

    private function makeOrder(array $attributes = []): Order
    {
        $client = Client::create(['name' => 'Rosa', 'phone' => '0991234567', 'client_type' => 'individual']);

        return Order::create($attributes + [
            'client_id' => $client->id, 'order_number' => 'TEST-' . uniqid(), 'device_type' => 'celular',
            'declared_fault' => 'No carga', 'unlock_type' => 'none', 'status' => 'received',
            'entry_date' => now()->subDays(2)->toDateString(),
        ]);
    }

    // --- Acciones rápidas de estado ---

    public function test_technician_advances_status_with_note(): void
    {
        $tech = $this->tech();
        $order = $this->makeOrder(['user_id' => $tech->id]);

        $this->actingAs($tech)->patch(route('orders.quick-status', $order), ['status' => 'diagnosing', 'note' => 'Revisando puerto de carga'])
            ->assertSessionHas('success');

        $this->assertSame('diagnosing', $order->fresh()->status);
        $history = OrderStatusHistory::where('order_id', $order->id)->latest('id')->first();
        $this->assertSame('Revisando puerto de carga', $history->notes);
        $this->assertSame($tech->id, $history->changed_by);
    }

    public function test_only_next_steps_are_allowed(): void
    {
        $tech = $this->tech();
        $order = $this->makeOrder(['user_id' => $tech->id]);

        $this->actingAs($tech)->patch(route('orders.quick-status', $order), ['status' => 'delivered'])->assertSessionHasErrors('status');
        $this->actingAs($tech)->patch(route('orders.quick-status', $order), ['status' => 'ready'])->assertSessionHasErrors('status');
        $this->assertSame('received', $order->fresh()->status);
    }

    public function test_quick_ready_respects_required_quality_check(): void
    {
        $this->seed(\Database\Seeders\ChecklistSeeder::class);
        Setting::put('require_quality_check', '1', 'orders');
        $tech = $this->tech();
        $order = $this->makeOrder(['user_id' => $tech->id, 'status' => 'repairing']);

        $this->actingAs($tech)->patch(route('orders.quick-status', $order), ['status' => 'ready'])->assertSessionHas('error');
        $this->assertSame('repairing', $order->fresh()->status);
    }

    public function test_quick_close_without_repair_returns_parts(): void
    {
        $tech = $this->tech();
        $order = $this->makeOrder(['user_id' => $tech->id, 'status' => 'waiting_approval']);
        $part = SparePart::create(['name' => 'Puerto USB-C', 'cost_price' => 2, 'sale_price' => 8, 'min_stock' => 0]);
        $part->setBranchStock(\App\Models\Branch::mainId(), 3);
        $this->actingAs($tech)->post(route('orders.parts.store', $order), ['spare_part_id' => $part->id, 'quantity' => 1]);

        $this->actingAs($tech)->patch(route('orders.quick-status', $order), ['status' => 'closed_no_repair', 'note' => 'Cliente rechazó']);

        $this->assertSame('closed_no_repair', $order->fresh()->status);
        $this->assertSame(3, $part->fresh()->stock_quantity);
    }

    public function test_cannot_act_on_other_technicians_order(): void
    {
        $order = $this->makeOrder(['user_id' => $this->tech()->id]);

        $this->actingAs($this->tech())->patch(route('orders.quick-status', $order), ['status' => 'diagnosing'])->assertForbidden();
        $this->actingAs($this->tech())->patch(route('orders.work', $order), ['diagnosis' => 'x'])->assertForbidden();
        $this->actingAs($this->tech())->post(route('orders.notes.store', $order), ['body' => 'x'])->assertForbidden();
    }

    // --- Tomar orden ---

    public function test_technician_takes_unassigned_order(): void
    {
        $tech = $this->tech();
        $order = $this->makeOrder();

        $this->actingAs($tech)->post(route('orders.take', $order))->assertRedirect(route('orders.show', $order));
        $this->assertSame($tech->id, $order->fresh()->user_id);

        $this->actingAs($this->tech())->post(route('orders.take', $order))->assertSessionHas('error');
        $this->assertSame($tech->id, $order->fresh()->user_id);
    }

    public function test_only_technicians_take_orders(): void
    {
        $this->actingAs(User::factory()->role('receptionist')->create())
            ->post(route('orders.take', $this->makeOrder()))->assertForbidden();
    }

    // --- Trabajo técnico ---

    public function test_work_panel_saves_and_recalculates_totals(): void
    {
        $tech = $this->tech();
        $order = $this->makeOrder(['user_id' => $tech->id, 'status' => 'diagnosing', 'parts_cost' => 20, 'total_amount' => 20, 'balance_due' => 20]);

        $this->actingAs($tech)->patch(route('orders.work', $order), [
            'diagnosis' => 'Puerto de carga sulfatado', 'work_done' => '', 'diagnosis_cost' => 5, 'labor_cost' => 25,
        ])->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('Puerto de carga sulfatado', $order->diagnosis);
        $this->assertEquals(50, $order->total_amount);
        $this->assertEquals(50, $order->balance_due);
    }

    public function test_work_panel_locked_on_closed_orders(): void
    {
        $tech = $this->tech();
        $order = $this->makeOrder(['user_id' => $tech->id, 'status' => 'delivered', 'labor_cost' => 10, 'total_amount' => 10]);

        $this->actingAs($tech)->patch(route('orders.work', $order), ['labor_cost' => 999])->assertSessionHas('error');
        $this->assertEquals(10, $order->fresh()->labor_cost);
    }

    // --- Bitácora ---

    public function test_log_notes_and_delete_permissions(): void
    {
        $tech = $this->tech();
        $order = $this->makeOrder(['user_id' => $tech->id]);

        $this->actingAs($tech)->post(route('orders.notes.store', $order), ['body' => 'Repuesto pedido al proveedor']);
        $note = OrderNote::first();
        $this->assertSame($tech->id, $note->user_id);

        $receptionist = User::factory()->role('receptionist')->create();
        $this->actingAs($receptionist)->get(route('orders.show', $order))->assertSee('Repuesto pedido al proveedor');
        $this->actingAs($receptionist)->delete(route('orders.notes.destroy', [$order, $note]))->assertForbidden();

        $this->actingAs($tech)->delete(route('orders.notes.destroy', [$order, $note]))->assertSessionHas('success');
        $this->assertModelMissing($note);
    }

    // --- Fotos desde el detalle ---

    public function test_photo_from_detail_with_stage(): void
    {
        Storage::fake('local');
        $tech = $this->tech();
        $order = $this->makeOrder(['user_id' => $tech->id, 'status' => 'repairing']);

        $this->actingAs($tech)->get(route('orders.show', $order))->assertSee('capture="environment"', false);
        $this->actingAs($tech)->post(route('orders.photo.upload', $order), ['photo' => UploadedFile::fake()->image('antes.jpg'), 'stage' => 'process'])
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame('process', OrderPhoto::first()->stage);
    }

    // --- Mi trabajo ---

    public function test_technician_dashboard_queue(): void
    {
        $tech = $this->tech();
        $old = $this->makeOrder(['user_id' => $tech->id, 'status' => 'repairing', 'entry_date' => now()->subDays(9)->toDateString()]);
        $new = $this->makeOrder(['user_id' => $tech->id, 'status' => 'repairing', 'entry_date' => now()->toDateString()]);
        $ready = $this->makeOrder(['user_id' => $tech->id, 'status' => 'ready']);
        $this->makeOrder(['user_id' => $tech->id, 'status' => 'delivered']);
        $free = $this->makeOrder();
        $this->makeOrder(['user_id' => $this->tech()->id]);

        $this->actingAs($tech)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mi trabajo')
            ->assertDontSee('Órdenes Totales')
            ->assertViewHas('myWork', fn ($w) => $w['stages']['En reparación']->pluck('id')->all() === [$old->id, $new->id]
                && $w['stages']['Listas para entregar']->pluck('id')->all() === [$ready->id]
                && $w['available']->pluck('id')->all() === [$free->id]);

        $this->actingAs(User::factory()->role('admin')->create())->get(route('dashboard'))->assertViewHas('myWork', null);
    }

    public function test_detail_shows_quick_actions_for_technician(): void
    {
        $tech = $this->tech();
        $order = $this->makeOrder(['status' => 'ready', 'balance_due' => 15, 'total_amount' => 15]);

        $this->actingAs($tech)->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Tomar esta orden')
            ->assertSee('Volver a reparación')
            ->assertSee('Cobrar $15.00 y entregar');
    }
}
