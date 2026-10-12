<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderNote;
use App\Models\User;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(array $attributes = []): Order
    {
        $client = Client::create(['name' => 'Gabriela Torres', 'phone' => '0998887766', 'id_document' => '1712345678', 'client_type' => 'individual']);

        return Order::create($attributes + [
            'client_id' => $client->id, 'order_number' => 'ORD-T-1', 'device_type' => 'celular', 'brand_text' => 'Samsung',
            'model_text' => 'A15', 'declared_fault' => 'No carga', 'unlock_type' => 'pin', 'unlock_value' => '4321',
            'status' => 'repairing', 'entry_date' => now()->toDateString(), 'labor_cost' => 40,
            'total_amount' => 40, 'amount_paid' => 10, 'balance_due' => 30, 'notes' => 'Nota interna secreta',
        ]);
    }

    public function test_every_order_gets_a_private_token(): void
    {
        $a = $this->makeOrder();
        $b = $this->makeOrder(['order_number' => 'ORD-T-2']);

        $this->assertSame(32, strlen($a->tracking_token));
        $this->assertNotSame($a->tracking_token, $b->tracking_token);
    }

    public function test_client_sees_status_without_login(): void
    {
        $order = $this->makeOrder();

        $this->get($order->trackingUrl())
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('ORD-T-1')
            ->assertSee('En Reparación')
            ->assertSee('Samsung A15')
            ->assertSee('$30.00');
    }

    public function test_tracking_page_does_not_leak_private_data(): void
    {
        $tech = User::factory()->role('technician')->create(['name' => 'Técnico Secreto']);
        $order = $this->makeOrder(['user_id' => $tech->id, 'diagnosis' => 'Diagnóstico interno']);
        OrderNote::create(['order_id' => $order->id, 'user_id' => $tech->id, 'body' => 'Bitácora interna']);

        $this->get($order->trackingUrl())
            ->assertOk()
            ->assertDontSee('4321')
            ->assertDontSee('Nota interna secreta')
            ->assertDontSee('Bitácora interna')
            ->assertDontSee('Técnico Secreto')
            ->assertDontSee('0998887766')
            ->assertDontSee('1712345678')
            ->assertDontSee('Gabriela');
    }

    public function test_unknown_or_malformed_token_is_not_found(): void
    {
        $this->makeOrder();

        $this->get('/seguimiento/' . str_repeat('a', 32))->assertNotFound();
        $this->get('/seguimiento/corto')->assertNotFound();
    }

    public function test_tracking_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get('/seguimiento/' . str_repeat('b', 32));
        }

        $this->get('/seguimiento/' . str_repeat('b', 32))->assertStatus(429);
    }

    public function test_link_in_whatsapp_order_detail_and_pdf(): void
    {
        $order = $this->makeOrder(['status' => 'ready']);
        $admin = User::factory()->role('admin')->create();

        $this->assertStringContainsString('Estado de su orden: ' . $order->trackingUrl(), WhatsAppMessage::build('ready', $order));
        $this->actingAs($admin)->get(route('orders.show', $order))->assertSee($order->trackingUrl());
        $this->actingAs($admin)->get(route('orders.pdf', $order))->assertOk();
    }
}
