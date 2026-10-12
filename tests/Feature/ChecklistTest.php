<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderChecklistResult;
use App\Models\User;
use Database\Seeders\ChecklistSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChecklistSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->role('admin')->create();
    }

    private function makeOrder(array $attributes = []): Order
    {
        $client = Client::create(['name' => 'Andrés Loor', 'phone' => '0991234567', 'client_type' => 'individual']);

        return Order::create($attributes + [
            'client_id' => $client->id,
            'order_number' => 'TEST-' . uniqid(),
            'device_type' => 'celular',
            'declared_fault' => 'No carga',
            'unlock_type' => 'none',
            'status' => 'received',
            'entry_date' => now()->toDateString(),
        ]);
    }

    private function receptionItems()
    {
        return Checklist::for('celular', 'reception')->activeItems()->get();
    }

    private function allOk(array $overrides = []): array
    {
        $results = [];
        foreach ($this->receptionItems() as $item) {
            $results[$item->id] = 'ok';
        }

        return ['results' => $overrides + $results];
    }

    public function test_seeder_creates_checklists_once(): void
    {
        $this->assertSame(9, Checklist::count());
        $this->seed(ChecklistSeeder::class);
        $this->assertSame(9, Checklist::count());
    }

    public function test_reception_checklist_is_saved_and_audited(): void
    {
        $order = $this->makeOrder();
        $first = $this->receptionItems()->first();

        $payload = $this->allOk([$first->id => 'damaged']);
        $payload['notes'] = [$first->id => 'Rayón en esquina superior'];

        $this->actingAs($this->admin())->put(route('orders.checklists.update', [$order, 'reception']), $payload)
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame(6, OrderChecklistResult::where('order_id', $order->id)->count());
        $result = OrderChecklistResult::where('checklist_item_id', $first->id)->first();
        $this->assertSame('damaged', $result->result);
        $this->assertSame('Rayón en esquina superior', $result->notes);

        $progress = $order->checklistProgress('reception');
        $this->assertTrue($progress['complete']);
        $this->assertSame(1, $progress['damaged']);

        $this->assertTrue(AuditLog::where('event', 'checklist')->where('order_id', $order->id)->exists());
    }

    public function test_required_items_must_be_answered(): void
    {
        $order = $this->makeOrder();
        $item = $this->receptionItems()->first();

        $this->actingAs($this->admin())->put(route('orders.checklists.update', [$order, 'reception']), ['results' => [$item->id => 'ok']])
            ->assertSessionHasErrors();

        $this->assertSame(0, OrderChecklistResult::count());
    }

    public function test_closed_order_checklist_is_read_only(): void
    {
        $order = $this->makeOrder(['status' => 'delivered']);

        $this->actingAs($this->admin())->put(route('orders.checklists.update', [$order, 'reception']), $this->allOk())
            ->assertSessionHas('error');
        $this->actingAs($this->admin())->get(route('orders.checklists.edit', [$order, 'reception']))->assertOk()->assertSee('solo lectura');
    }

    public function test_device_without_checklist_returns_404(): void
    {
        $order = $this->makeOrder(['device_type' => 'lavador']);

        $this->actingAs($this->admin())->get(route('orders.checklists.edit', [$order, 'reception']))->assertNotFound();
    }

    public function test_technician_cannot_fill_other_technicians_order(): void
    {
        $order = $this->makeOrder(['user_id' => User::factory()->role('technician')->create()->id]);

        $this->actingAs(User::factory()->role('technician')->create())
            ->get(route('orders.checklists.edit', [$order, 'reception']))->assertForbidden();
    }

    public function test_damaged_items_appear_in_signature_screen_and_pdf(): void
    {
        $order = $this->makeOrder();
        $admin = $this->admin();
        $first = $this->receptionItems()->first();
        $this->actingAs($admin)->put(route('orders.checklists.update', [$order, 'reception']), $this->allOk([$first->id => 'damaged']));

        $this->actingAs($admin)->get(route('orders.signatures.create', [$order, 'reception']))
            ->assertOk()->assertSee('Daños registrados')->assertSee($first->label);
        $this->actingAs($admin)->get(route('orders.pdf', $order))->assertOk();
    }

    public function test_ready_order_warns_when_quality_check_missing(): void
    {
        $order = $this->makeOrder(['status' => 'ready']);

        $this->actingAs($this->admin())->get(route('orders.show', $order))
            ->assertSee('falta el control de calidad');
    }

    // --- Administración ---

    public function test_admin_edits_items_and_removed_items_are_deactivated(): void
    {
        $checklist = Checklist::for('celular', 'reception');
        $items = $checklist->activeItems()->get();
        $order = $this->makeOrder();
        $this->actingAs($this->admin())->put(route('orders.checklists.update', [$order, 'reception']), $this->allOk());

        // Se queda con los dos primeros (el segundo renombrado), agrega uno nuevo y quita el resto.
        $this->actingAs($this->admin())->put(route('checklists.update', $checklist), [
            'name' => 'Recepción celular',
            'is_active' => '1',
            'items' => [
                ['id' => $items[1]->id, 'label' => 'Carcasa trasera', 'is_required' => '1'],
                ['id' => $items[0]->id, 'label' => $items[0]->label, 'is_required' => '0'],
                ['id' => '', 'label' => 'Cámara sin rayones', 'is_required' => '1'],
            ],
        ])->assertRedirect(route('checklists.index'));

        $active = $checklist->activeItems()->get();
        $this->assertSame(['Carcasa trasera', $items[0]->label, 'Cámara sin rayones'], $active->pluck('label')->all());
        $this->assertFalse($active[1]->is_required);
        $this->assertSame(4, ChecklistItem::where('checklist_id', $checklist->id)->where('is_active', false)->count());
        // Los resultados de la orden anterior se conservan.
        $this->assertSame(6, OrderChecklistResult::where('order_id', $order->id)->count());
    }

    public function test_admin_creates_checklist_for_new_device_type(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('checklists.create', ['device_type' => 'lavador', 'stage' => 'reception']))->assertOk();

        $this->actingAs($admin)->post(route('checklists.store'), [
            'device_type' => 'lavador', 'stage' => 'reception', 'name' => 'Recepción lavadora', 'is_active' => '1',
            'items' => [['label' => 'Tambor sin golpes', 'is_required' => '1'], ['label' => '', 'is_required' => '1']],
        ])->assertRedirect(route('checklists.index'));

        $this->assertSame(['Tambor sin golpes'], Checklist::for('lavador', 'reception')->activeItems()->pluck('label')->all());
    }

    public function test_items_of_other_checklists_cannot_be_hijacked(): void
    {
        $celular = Checklist::for('celular', 'reception');
        $pcItem = Checklist::for('pc', 'reception')->activeItems()->first();

        $this->actingAs($this->admin())->put(route('checklists.update', $celular), [
            'name' => 'X', 'is_active' => '1',
            'items' => [['id' => $pcItem->id, 'label' => 'Robado', 'is_required' => '1']],
        ]);

        $this->assertNotSame('Robado', $pcItem->fresh()->label);
        $this->assertSame($pcItem->checklist_id, $pcItem->fresh()->checklist_id);
    }

    public function test_only_admins_manage_checklists(): void
    {
        $this->actingAs($this->admin())->get(route('checklists.index'))->assertOk();
        $this->actingAs(User::factory()->role('receptionist')->create())->get(route('checklists.index'))->assertForbidden();
    }

    public function test_screens_render(): void
    {
        $order = $this->makeOrder();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('orders.show', $order))->assertOk()->assertSee('Control de calidad');
        $this->actingAs($admin)->get(route('orders.checklists.edit', [$order, 'diagnosis']))->assertOk();
        $this->actingAs($admin)->get(route('checklists.edit', Checklist::first()))->assertOk();
    }
}
