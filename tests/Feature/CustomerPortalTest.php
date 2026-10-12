<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderReview;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(array $attributes = []): Order
    {
        $client = Client::create(['name' => 'Gabriela Torres', 'phone' => '0998887766', 'client_type' => 'individual']);

        $order = Order::create($attributes + [
            'client_id' => $client->id, 'order_number' => 'ORD-P-' . uniqid(), 'device_type' => 'celular', 'declared_fault' => 'No carga',
            'unlock_type' => 'none', 'status' => 'waiting_approval', 'entry_date' => today(), 'diagnosis' => 'Pin de carga dañado',
            'diagnosis_cost' => 10, 'labor_cost' => 30,
        ]);
        $order->recalculateTotals();

        return $order;
    }

    public function test_client_sees_budget_and_approves_online(): void
    {
        $tech = User::factory()->role('technician')->create();
        $order = $this->makeOrder(['user_id' => $tech->id]);

        $this->get(route('tracking.show', $order->tracking_token))
            ->assertOk()
            ->assertSee('Presupuesto de reparación')
            ->assertSee('Pin de carga dañado')
            ->assertSee('Aprobar reparación');

        $this->post(route('tracking.decide', $order->tracking_token), ['decision' => 'approve'])
            ->assertRedirect(route('tracking.show', $order->tracking_token));

        $order->refresh();
        $this->assertSame('repairing', $order->status);
        $this->assertTrue($order->statusHistory()->where('notes', 'like', '%aprobado por el cliente en línea%')->exists());
        $this->assertSame(1, $tech->notifications()->count());

        // Un segundo clic no cambia nada.
        $this->post(route('tracking.decide', $order->tracking_token), ['decision' => 'reject'])->assertSessionHas('info');
        $this->assertSame('repairing', $order->fresh()->status);
    }

    public function test_client_rejects_with_reason(): void
    {
        $desk = User::factory()->role('receptionist')->create();
        $order = $this->makeOrder(['branch_id' => $desk->branch_id]);

        $this->post(route('tracking.decide', $order->tracking_token), ['decision' => 'reject', 'reason' => 'Muy caro']);

        $this->assertSame('closed_no_repair', $order->fresh()->status);
        $this->assertTrue($order->statusHistory()->where('notes', 'like', '%Muy caro%')->exists());
        $this->assertSame(1, $desk->notifications()->count()); // sin técnico: avisa a recepción de la sucursal
    }

    public function test_online_approval_can_be_disabled(): void
    {
        Setting::put('online_approval', '0', 'portal');
        $order = $this->makeOrder();

        $this->get(route('tracking.show', $order->tracking_token))->assertOk()->assertDontSee('Aprobar reparación');
        $this->post(route('tracking.decide', $order->tracking_token), ['decision' => 'approve']);
        $this->assertSame('waiting_approval', $order->fresh()->status);
    }

    public function test_client_rates_service_once_after_delivery(): void
    {
        $admin = User::factory()->role('admin')->create();
        $order = $this->makeOrder(['status' => 'delivered', 'delivery_date' => today()]);

        $this->get(route('tracking.show', $order->tracking_token))->assertSee('¿Cómo le atendimos?');
        $this->post(route('tracking.review', $order->tracking_token), ['rating' => 2, 'comment' => 'Tardaron mucho'])
            ->assertSessionHas('success');
        $this->post(route('tracking.review', $order->tracking_token), ['rating' => 5]);

        $this->assertSame(1, OrderReview::count());
        $this->assertSame(2, OrderReview::first()->rating);
        $this->assertSame(1, $admin->notifications()->count()); // calificación baja: aviso a la administración
        $this->get(route('tracking.show', $order->tracking_token))->assertDontSee('¿Cómo le atendimos?')->assertSee('Su calificación');

        $this->actingAs($admin)->get(route('orders.show', $order))->assertSee('Tardaron mucho');
        $this->actingAs($admin)->get(route('reports.index'))
            ->assertViewHas('satisfaction', fn ($s) => $s['count'] === 1 && $s['average'] == 2.0);
    }

    public function test_no_review_before_delivery(): void
    {
        $order = $this->makeOrder(['status' => 'repairing']);

        $this->post(route('tracking.review', $order->tracking_token), ['rating' => 5]);
        $this->assertSame(0, OrderReview::count());
    }

    public function test_whatsapp_messages_invite_to_approve_and_rate(): void
    {
        $order = $this->makeOrder();

        $this->assertStringContainsString('Puede aprobar o rechazar la reparación aquí: ' . $order->trackingUrl(), \App\Services\WhatsAppMessage::build('budget', $order));
        $this->assertStringContainsString('Califíquenos aquí: ' . $order->trackingUrl(), \App\Services\WhatsAppMessage::build('delivery', $order));
    }

    public function test_device_label_prints_order_number_and_qr(): void
    {
        $order = $this->makeOrder(['status' => 'received']);

        $this->actingAs(User::factory()->role('receptionist')->create())->get(route('orders.label', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Gabriela Torres')
            ->assertSee('data:image/png;base64', false);
    }
}
