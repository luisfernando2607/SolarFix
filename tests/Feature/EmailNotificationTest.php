<?php

namespace Tests\Feature;

use App\Mail\OrderUpdateMail;
use App\Models\Client;
use App\Models\EmailLog;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer(); // en las pruebas, el envío "después de responder" ocurre en el momento
    }

    private function order(array $attributes = [], ?string $email = 'ana@correo.com'): Order
    {
        $client = Client::create(['name' => 'Ana', 'phone' => '0991234567', 'email' => $email, 'client_type' => 'individual']);
        $order = Order::create($attributes + [
            'client_id' => $client->id, 'order_number' => 'T-' . uniqid(), 'device_type' => 'celular', 'declared_fault' => 'x',
            'unlock_type' => 'none', 'status' => 'repairing', 'entry_date' => today(), 'labor_cost' => 40,
        ]);
        $order->recalculateTotals();

        return $order;
    }

    public function test_status_changes_email_the_client_when_enabled(): void
    {
        Mail::fake();
        Setting::put('email_notifications', '1', 'portal');
        $tech = User::factory()->role('technician')->create();
        $order = $this->order(['user_id' => $tech->id]);

        $this->actingAs($tech)->patch(route('orders.quick-status', $order), ['status' => 'ready'])->assertSessionHasNoErrors();

        Mail::assertSent(OrderUpdateMail::class, fn ($mail) => $mail->hasTo('ana@correo.com') && $mail->type === 'ready');
        $this->assertSame('sent', EmailLog::first()->status);

        $order->fresh()->markDelivered(null);
        Mail::assertSent(OrderUpdateMail::class, fn ($mail) => $mail->type === 'delivery');
        $order->fresh()->markDelivered(null); // volver a marcar no reenvía
        Mail::assertSent(OrderUpdateMail::class, 2);
    }

    public function test_no_email_when_disabled_or_client_without_email(): void
    {
        Mail::fake();
        $tech = User::factory()->role('technician')->create();
        $order = $this->order(['user_id' => $tech->id]);
        $this->actingAs($tech)->patch(route('orders.quick-status', $order), ['status' => 'ready']);
        Mail::assertNothingSent();

        Setting::put('email_notifications', '1', 'portal');
        $other = $this->order(['user_id' => $tech->id], email: null);
        $this->actingAs($tech)->patch(route('orders.quick-status', $other), ['status' => 'ready']);
        Mail::assertNothingSent();
    }

    public function test_manual_send_and_failure_is_logged(): void
    {
        $desk = User::factory()->role('receptionist')->create();
        $order = $this->order(['status' => 'waiting_approval', 'branch_id' => $desk->branch_id]);

        Mail::fake();
        $this->actingAs($desk)->post(route('orders.email', [$order, 'budget']))->assertSessionHas('success');
        Mail::assertSent(OrderUpdateMail::class, fn ($mail) => $mail->type === 'budget');

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connection refused'));
        $this->actingAs($desk)->post(route('orders.email', [$order, 'budget']))->assertSessionHas('error');
        $this->assertSame('failed', EmailLog::latest('id')->first()->status);
        $this->assertStringContainsString('Connection refused', EmailLog::latest('id')->first()->error);
    }

    public function test_email_content_uses_template_and_tracking_button(): void
    {
        $order = $this->order(['status' => 'ready']);
        $html = (new OrderUpdateMail($order, 'ready'))->render();

        $this->assertStringContainsString('está listo para retirar', $html);
        $this->assertStringContainsString($order->trackingUrl(), $html);
        $this->assertStringContainsString('Ver el estado de mi orden', $html);
        $this->assertStringNotContainsString('*', strip_tags($html));
    }

    public function test_test_email_from_settings(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->role('admin')->create())->post(route('settings.test-email'))->assertSessionHas('success');
    }
}
