<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceptionTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function receptionist(): User
    {
        return User::factory()->role('receptionist')->create();
    }

    private function makeOrder(array $attributes = [], array $client = []): Order
    {
        $client = Client::create($client + ['name' => 'Patricia Benítez', 'phone' => '0990123456', 'id_document' => '1702345678', 'client_type' => 'individual']);

        return Order::create($attributes + [
            'client_id' => $client->id, 'order_number' => 'ORD-R-' . uniqid(), 'device_type' => 'celular',
            'brand_text' => 'Xiaomi', 'model_text' => 'Redmi 13', 'serial_imei' => '356789123456789',
            'declared_fault' => 'Pantalla rota', 'unlock_type' => 'none', 'status' => 'repairing',
            'entry_date' => now()->toDateString(),
        ]);
    }

    // --- Búsqueda global ---

    public function test_search_by_phone_document_imei_and_name(): void
    {
        $order = $this->makeOrder();
        $this->makeOrder(['serial_imei' => '111111111111111'], ['name' => 'Otra Persona', 'phone' => '0911111111', 'id_document' => '0909090909']);
        $receptionist = $this->receptionist();

        foreach (['0990123456', '1702345678', '356789123456789', 'Benítez'] as $q) {
            $this->actingAs($receptionist)->get(route('search', ['q' => $q]))
                ->assertOk()
                ->assertViewHas('orders', fn ($o) => $o->pluck('id')->all() === [$order->id]);
        }
        $this->actingAs($receptionist)->get(route('search', ['q' => 'Patricia']))->assertViewHas('clients', fn ($c) => $c->count() === 1);
    }

    public function test_exact_order_number_goes_straight_to_order(): void
    {
        $order = $this->makeOrder(['order_number' => 'ORD-20261012-0042']);

        $this->actingAs($this->receptionist())->get(route('search', ['q' => 'ORD-20261012-0042']))
            ->assertRedirect(route('orders.show', $order));
    }

    public function test_technician_search_respects_visibility(): void
    {
        $tech = User::factory()->role('technician')->create();
        $mine = $this->makeOrder(['user_id' => $tech->id]);
        $theirs = $this->makeOrder(['user_id' => User::factory()->role('technician')->create()->id]);

        $this->actingAs($tech)->get(route('search', ['q' => 'Xiaomi']))
            ->assertViewHas('orders', fn ($o) => $o->pluck('id')->all() === [$mine->id])
            ->assertViewHas('clients', fn ($c) => $c->isEmpty());
        $this->actingAs($tech)->get(route('search', ['q' => $theirs->order_number]))->assertOk();
    }

    public function test_search_bar_is_in_layout(): void
    {
        $this->actingAs($this->receptionist())->get(route('dashboard'))->assertSee('id="searchDesktop"', false)->assertSee('id="searchMobile"', false);
    }

    // --- Mostrador ---

    public function test_counter_panel_lists_ready_waiting_and_today(): void
    {
        $ready = $this->makeOrder(['status' => 'ready', 'total_amount' => 50, 'balance_due' => 20]);
        OrderStatusHistory::create(['order_id' => $ready->id, 'to_status' => 'ready', 'changed_at' => now()->subDays(2)]);
        $waiting = $this->makeOrder(['status' => 'waiting_approval']);
        $this->makeOrder(['status' => 'delivered']);

        $this->actingAs($this->receptionist())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mostrador')
            ->assertViewHas('counter', fn ($c) => $c['ready']->pluck('id')->all() === [$ready->id]
                && $c['waiting']->pluck('id')->all() === [$waiting->id]
                && $c['today']->count() === 3)
            ->assertSee('Saldo $20.00');

        $this->actingAs(User::factory()->role('technician')->create())->get(route('dashboard'))->assertViewHas('counter', null);
    }

    // --- Entrega en un solo paso ---

    public function test_receptionist_collects_balance_and_delivers_in_one_step(): void
    {
        Storage::fake('local');
        $order = $this->makeOrder(['status' => 'ready', 'labor_cost' => 50, 'total_amount' => 50, 'balance_due' => 50]);

        $this->actingAs($this->receptionist())->get(route('orders.signatures.create', [$order, 'delivery']))
            ->assertSee('Cobrar el saldo antes de entregar');

        $this->actingAs($this->receptionist())->post(route('orders.signatures.store', [$order, 'delivery']), [
            'signature' => self::PNG, 'payment_amount' => 50, 'payment_method' => 'transfer', 'payment_reference' => 'TRX-99',
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertEquals(0, $order->balance_due);
        $payment = OrderPayment::first();
        $this->assertSame('transfer', $payment->method);
        $this->assertSame('TRX-99', $payment->reference);
    }

    public function test_delivery_payment_cannot_exceed_balance(): void
    {
        Storage::fake('local');
        $order = $this->makeOrder(['status' => 'ready', 'labor_cost' => 50, 'total_amount' => 50, 'balance_due' => 50]);

        $this->actingAs($this->receptionist())->post(route('orders.signatures.store', [$order, 'delivery']), [
            'signature' => self::PNG, 'payment_amount' => 80, 'payment_method' => 'cash',
        ])->assertSessionHasErrors('payment_amount');

        $this->assertSame('ready', $order->fresh()->status);
        $this->assertSame(0, OrderPayment::count());
    }

    public function test_technician_cannot_collect_at_delivery(): void
    {
        Storage::fake('local');
        $tech = User::factory()->role('technician')->create();
        $order = $this->makeOrder(['status' => 'ready', 'user_id' => $tech->id, 'labor_cost' => 50, 'total_amount' => 50, 'balance_due' => 50]);

        $this->actingAs($tech)->get(route('orders.signatures.create', [$order, 'delivery']))->assertDontSee('Cobrar el saldo antes de entregar');
        $this->actingAs($tech)->post(route('orders.signatures.store', [$order, 'delivery']), [
            'signature' => self::PNG, 'payment_amount' => 50, 'payment_method' => 'cash',
        ])->assertForbidden();
    }

    // --- Ticket de 80 mm ---

    public function test_thermal_ticket_with_qr_and_configured_terms(): void
    {
        Setting::put('ticket_terms', 'Plazo de retiro: 60 días.', 'orders');
        $order = $this->makeOrder(['status' => 'received']);

        $this->actingAs($this->receptionist())->get(route('orders.ticket', $order))
            ->assertOk()
            ->assertSee('size: 80mm auto', false)
            ->assertSee($order->order_number)
            ->assertSee('Pantalla rota')
            ->assertSee('Plazo de retiro: 60 días.')
            ->assertSee($order->trackingUrl());
    }

    public function test_ticket_respects_technician_visibility(): void
    {
        $order = $this->makeOrder(['user_id' => User::factory()->role('technician')->create()->id]);

        $this->actingAs(User::factory()->role('technician')->create())->get(route('orders.ticket', $order))->assertForbidden();
    }
}
