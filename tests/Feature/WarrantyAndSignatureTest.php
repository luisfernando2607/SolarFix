<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderSignature;
use App\Models\User;
use App\Models\Warranty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WarrantyAndSignatureTest extends TestCase
{
    use RefreshDatabase;

    // PNG válido de 1x1 píxel
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::factory()->role('admin')->create();
    }

    private function makeOrder(array $attributes = []): Order
    {
        $client = Client::create(['name' => 'Luis Vera', 'phone' => '0991234567', 'client_type' => 'individual']);

        return Order::create($attributes + [
            'client_id' => $client->id,
            'order_number' => 'TEST-' . uniqid(),
            'device_type' => 'celular',
            'declared_fault' => 'No carga',
            'unlock_type' => 'pin',
            'unlock_value' => '4321',
            'status' => 'ready',
            'entry_date' => now()->subDays(3)->toDateString(),
            'labor_cost' => 25,
            'total_amount' => 25,
            'warranty_days' => 30,
        ]);
    }

    private function updatePayload(Order $order, array $overrides = []): array
    {
        return $overrides + [
            'client_id' => $order->client_id,
            'device_type' => $order->device_type,
            'declared_fault' => $order->declared_fault,
            'unlock_type' => $order->unlock_type,
            'entry_date' => $order->entry_date->toDateString(),
            'labor_cost' => 25,
            'warranty_days' => $order->warranty_days,
        ];
    }

    // --- Emisión de garantía ---

    public function test_delivering_order_issues_warranty(): void
    {
        $order = $this->makeOrder();

        $order->markDelivered($this->admin()->id);

        $order->refresh();
        $this->assertEquals(today(), $order->delivery_date);
        $this->assertNotNull($order->warranty);
        $this->assertEquals(today()->addDays(30), $order->warranty->end_date);
        $this->assertSame('active', $order->warranty->currentStatus());
    }

    public function test_no_warranty_when_days_is_zero(): void
    {
        $order = $this->makeOrder(['warranty_days' => 0]);

        $order->markDelivered($this->admin()->id);

        $this->assertNull($order->fresh()->warranty);
    }

    public function test_changing_warranty_days_updates_end_date(): void
    {
        $order = $this->makeOrder(['status' => 'delivered', 'delivery_date' => today()]);
        $order->issueWarranty();

        $this->actingAs($this->admin())->put(route('orders.update', $order), $this->updatePayload($order, ['status' => 'delivered', 'warranty_days' => 90, 'delivery_date' => today()->toDateString()]));

        $this->assertEquals(today()->addDays(90), $order->fresh()->warranty->end_date);
    }

    public function test_warranty_status_by_date(): void
    {
        $warranty = new Warranty(['status' => 'active', 'start_date' => today()->subDays(30), 'end_date' => today()->addDays(60)]);
        $this->assertSame('active', $warranty->currentStatus());

        $warranty->end_date = today()->addDays(10);
        $this->assertSame('expiring', $warranty->currentStatus());

        $warranty->end_date = today()->subDay();
        $this->assertSame('expired', $warranty->currentStatus());
        $this->assertFalse($warranty->isValid());
    }

    // --- Reclamo de garantía ---

    public function test_claim_creates_linked_free_warranty_order(): void
    {
        $tech = User::factory()->role('technician')->create();
        $original = $this->makeOrder(['status' => 'delivered', 'delivery_date' => today()->subDays(5), 'user_id' => $tech->id]);
        $original->issueWarranty();

        $this->actingAs($this->admin())->post(route('warranties.claim.store', $original), ['declared_fault' => 'Volvió a fallar la carga']);

        $claim = Order::where('parent_order_id', $original->id)->first();
        $this->assertNotNull($claim);
        $this->assertSame('warranty', $claim->status);
        $this->assertEquals(0, $claim->total_amount);
        $this->assertSame($tech->id, $claim->user_id);
        $this->assertSame('4321', $claim->unlock_value);
        $this->assertSame('claimed', $original->fresh()->warranty->status);
        $this->assertTrue($original->statusHistory()->where('notes', 'like', '%' . $claim->order_number . '%')->exists());
    }

    public function test_expired_warranty_cannot_be_claimed(): void
    {
        $original = $this->makeOrder(['status' => 'delivered', 'delivery_date' => today()->subDays(60)]);
        $original->issueWarranty();

        $this->actingAs($this->admin())->post(route('warranties.claim.store', $original), ['declared_fault' => 'Falla'])
            ->assertSessionHas('error');

        $this->assertSame(0, Order::where('parent_order_id', $original->id)->count());
    }

    public function test_technician_cannot_open_claims(): void
    {
        $original = $this->makeOrder(['status' => 'delivered', 'delivery_date' => today()]);
        $original->issueWarranty();

        $this->actingAs(User::factory()->role('technician')->create())
            ->post(route('warranties.claim.store', $original), ['declared_fault' => 'Falla'])
            ->assertForbidden();
    }

    public function test_warranty_list_filters_by_status(): void
    {
        $active = $this->makeOrder(['status' => 'delivered', 'delivery_date' => today()]);
        $active->issueWarranty();
        $expiring = $this->makeOrder(['status' => 'delivered', 'delivery_date' => today()->subDays(25)]);
        $expiring->issueWarranty();

        $this->actingAs($this->admin())->get(route('warranties.index', ['status' => 'expiring']))
            ->assertOk()
            ->assertSee($expiring->order_number)
            ->assertDontSee($active->order_number);

        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertViewHas('expiringWarranties', fn ($w) => $w->pluck('order_id')->all() === [$expiring->id]);
    }

    // --- Firmas ---

    public function test_reception_signature_is_stored_privately(): void
    {
        $order = $this->makeOrder(['status' => 'received']);

        $this->actingAs($this->admin())->post(route('orders.signatures.store', [$order, 'reception']), ['signature' => self::PNG])
            ->assertRedirect(route('orders.show', $order));

        $signature = OrderSignature::first();
        $this->assertSame('reception', $signature->stage);
        Storage::disk('local')->assertExists($signature->signature_path);

        $this->actingAs($this->admin())->get(route('orders.signatures.image', [$order, 'reception']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_delivery_signature_marks_delivered_and_issues_warranty(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin())->post(route('orders.signatures.store', [$order, 'delivery']), ['signature' => self::PNG]);

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertNotNull($order->warranty);
        $this->assertTrue($order->statusHistory()->where('to_status', 'delivered')->exists());
    }

    public function test_delivery_signature_requires_ready_order(): void
    {
        $order = $this->makeOrder(['status' => 'repairing']);

        $this->actingAs($this->admin())->post(route('orders.signatures.store', [$order, 'delivery']), ['signature' => self::PNG])
            ->assertSessionHas('error');

        $this->assertSame(0, OrderSignature::count());
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $order = $this->makeOrder(['status' => 'received']);

        $this->actingAs($this->admin())->post(route('orders.signatures.store', [$order, 'reception']), [
            'signature' => 'data:image/png;base64,' . base64_encode('<script>no es png</script>'),
        ])->assertSessionHasErrors('signature');

        $this->assertSame(0, OrderSignature::count());
    }

    public function test_only_admin_can_replace_signature(): void
    {
        $order = $this->makeOrder(['status' => 'received']);
        $receptionist = User::factory()->role('receptionist')->create();

        $this->actingAs($receptionist)->post(route('orders.signatures.store', [$order, 'reception']), ['signature' => self::PNG]);
        $first = OrderSignature::first()->signature_path;

        $this->actingAs($receptionist)->post(route('orders.signatures.store', [$order, 'reception']), ['signature' => self::PNG])
            ->assertSessionHas('error');
        $this->assertSame($first, OrderSignature::first()->signature_path);

        $this->travel(2)->seconds();
        $this->actingAs($this->admin())->post(route('orders.signatures.store', [$order, 'reception']), ['signature' => self::PNG]);
        $this->assertNotSame($first, OrderSignature::first()->signature_path);
        Storage::disk('local')->assertMissing($first);
    }

    public function test_technician_cannot_see_signature_of_other_technicians_order(): void
    {
        $other = User::factory()->role('technician')->create();
        $order = $this->makeOrder(['status' => 'received', 'user_id' => $other->id]);
        $this->actingAs($this->admin())->post(route('orders.signatures.store', [$order, 'reception']), ['signature' => self::PNG]);

        $this->actingAs(User::factory()->role('technician')->create())
            ->get(route('orders.signatures.image', [$order, 'reception']))
            ->assertForbidden();
    }

    // --- Pantallas y PDF ---

    public function test_screens_and_pdf_render_with_warranty_and_signatures(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();
        $this->actingAs($admin)->post(route('orders.signatures.store', [$order, 'reception']), ['signature' => self::PNG]);
        $this->actingAs($admin)->post(route('orders.signatures.store', [$order, 'delivery']), ['signature' => self::PNG]);
        $this->actingAs($admin)->post(route('warranties.claim.store', $order), ['declared_fault' => 'Falla otra vez']);
        $claim = Order::where('parent_order_id', $order->id)->first();

        $this->actingAs($admin)->get(route('orders.show', $order))->assertOk()->assertSee('Reclamos de garantía')->assertSee('Firmas');
        $this->actingAs($admin)->get(route('orders.show', $claim))->assertOk()->assertSee('Orden de garantía');
        $this->actingAs($admin)->get(route('orders.signatures.create', [$order, 'reception']))->assertOk();
        $this->actingAs($admin)->get(route('warranties.index'))->assertOk();

        $pdf = $this->actingAs($admin)->get(route('orders.pdf', $order));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->actingAs($admin)->get(route('orders.pdf', $claim))->assertOk();
    }

    public function test_receptionist_sees_clients_menu(): void
    {
        $this->actingAs(User::factory()->role('receptionist')->create())->get(route('dashboard'))
            ->assertSee(route('clients.index'))
            ->assertDontSee(route('brands.index'));
    }
}
