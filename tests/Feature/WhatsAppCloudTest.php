<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppCloudTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'graph.facebook.com/*';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
        config(['services.whatsapp.token' => 'TOKEN', 'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.verify_token' => 'mi-token', 'services.whatsapp.app_secret' => 'secreto']);
        Setting::put('whatsapp_mode', 'api', 'whatsapp');
    }

    private function order(array $attributes = []): Order
    {
        $client = Client::create(['name' => 'Ana Pérez', 'phone' => '099 123 4567', 'client_type' => 'individual']);
        $order = Order::create($attributes + [
            'client_id' => $client->id, 'order_number' => 'ORD-W-1', 'device_type' => 'celular', 'brand_text' => 'Samsung', 'model_text' => 'A15',
            'declared_fault' => 'x', 'unlock_type' => 'none', 'status' => 'diagnosing', 'entry_date' => today(), 'labor_cost' => 40,
        ]);
        $order->recalculateTotals();

        return $order;
    }

    private function signed(array $payload): array
    {
        return ['X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', json_encode($payload), 'secreto')];
    }

    public function test_budget_is_sent_as_approved_template(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['messages' => [['id' => 'wamid.ABC']]])]);
        $admin = User::factory()->role('admin')->create();
        $order = $this->order();

        $this->actingAs($admin)->post(route('orders.whatsapp', [$order, 'budget']))->assertSessionHas('success');

        Http::assertSent(function (HttpRequest $request) use ($order) {
            $params = collect($request['template']['components'][0]['parameters'])->pluck('text')->all();

            return $request->url() === 'https://graph.facebook.com/v21.0/1234567890/messages'
                && $request->hasHeader('Authorization', 'Bearer TOKEN')
                && $request['to'] === '593991234567'
                && $request['template']['name'] === 'solarfix_presupuesto'
                && $request['template']['language']['code'] === 'es'
                && $params === ['Ana Pérez', 'Celular Samsung A15', 'ORD-W-1', '$40.00', $order->trackingUrl()];
        });

        $log = WhatsappLog::first();
        $this->assertSame(['api', 'sent', 'wamid.ABC'], [$log->channel, $log->status, $log->wamid]);
        $this->assertSame('waiting_approval', $order->fresh()->status);
    }

    public function test_rejected_message_is_logged_and_reported(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => ['message' => 'Template name does not exist', 'error_data' => ['details' => 'template not found']]], 400)]);
        $order = $this->order();

        $this->actingAs(User::factory()->role('admin')->create())->post(route('orders.whatsapp', [$order, 'budget']))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'template not found'));

        $this->assertSame('failed', WhatsappLog::first()->status);
        $this->assertSame('diagnosing', $order->fresh()->status); // no se envió: no cambia de estado
    }

    public function test_status_change_sends_automatically_when_enabled(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['messages' => [['id' => 'wamid.READY']]])]);
        Setting::put('whatsapp_auto', '1', 'whatsapp');
        $tech = User::factory()->role('technician')->create();
        $order = $this->order(['status' => 'repairing', 'user_id' => $tech->id]);

        $this->actingAs($tech)->patch(route('orders.quick-status', $order), ['status' => 'ready']);

        Http::assertSent(fn (HttpRequest $r) => $r['template']['name'] === 'solarfix_equipo_listo'
            && $r['template']['components'][0]['parameters'][3]['text'] === 'Saldo pendiente: $40.00.');
        $this->assertSame('ready', WhatsappLog::first()->trigger_event);
    }

    public function test_link_mode_keeps_opening_whatsapp(): void
    {
        Http::fake();
        Setting::put('whatsapp_mode', 'link', 'whatsapp');
        $order = $this->order();

        $this->actingAs(User::factory()->role('admin')->create())->post(route('orders.whatsapp', [$order, 'ready']))
            ->assertRedirectContains('wa.me/593991234567');
        Http::assertNothingSent();
    }

    public function test_webhook_verification(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=mi-token&hub.challenge=12345')->assertOk()->assertSee('12345');
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=otro&hub.challenge=12345')->assertForbidden();
    }

    public function test_webhook_updates_status_without_going_back_and_rejects_bad_signature(): void
    {
        $order = $this->order();
        WhatsappLog::create(['order_id' => $order->id, 'client_id' => $order->client_id, 'trigger_event' => 'ready', 'channel' => 'api',
            'status' => 'sent', 'wamid' => 'wamid.X', 'phone_to' => '593991234567', 'message_body' => 'x', 'sent_at' => now()]);
        $status = fn ($s) => ['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.X', 'status' => $s]]]]]]]];

        $this->postJson('/webhooks/whatsapp', $status('read'), ['X-Hub-Signature-256' => 'sha256=falsa'])->assertForbidden();
        $this->assertSame('sent', WhatsappLog::first()->status);

        $this->postJson('/webhooks/whatsapp', $status('read'), $this->signed($status('read')))->assertOk();
        $this->postJson('/webhooks/whatsapp', $status('delivered'), $this->signed($status('delivered')))->assertOk();
        $this->assertSame('read', WhatsappLog::first()->status);   // llegó tarde "entregado": sigue "leído"

        $this->postJson('/webhooks/whatsapp', $status('failed'), $this->signed($status('failed')))->assertOk();
        $this->assertSame('failed', WhatsappLog::first()->status);
        $this->assertNull(session('_token'));   // el webhook no abre sesión
    }

    public function test_client_reply_is_attached_to_the_order_and_staff_notified(): void
    {
        $desk = User::factory()->role('receptionist')->create();
        $order = $this->order(['branch_id' => $desk->branch_id, 'status' => 'waiting_approval']);
        $payload = ['entry' => [['changes' => [['value' => [
            'contacts' => [['profile' => ['name' => 'Ana']]],
            'messages' => [['from' => '593991234567', 'id' => 'wamid.IN1', 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => 'Sí, apruebo la reparación']]],
        ]]]]]];

        $this->postJson('/webhooks/whatsapp', $payload, $this->signed($payload))->assertOk();
        $this->postJson('/webhooks/whatsapp', $payload, $this->signed($payload))->assertOk(); // Meta reintenta: no se duplica

        $reply = WhatsappLog::where('direction', 'in')->sole();
        $this->assertSame($order->id, $reply->order_id);
        $this->assertSame('Sí, apruebo la reparación', $reply->message_body);
        $this->assertSame(1, $desk->notifications()->count());

        $this->actingAs($desk)->get(route('orders.show', $order))->assertSee('Respuesta del cliente')->assertSee('Sí, apruebo la reparación');
    }

    public function test_daily_reminders_for_tomorrow_and_uncollected_devices(): void
    {
        Http::fake([self::ENDPOINT => fn () => Http::response(['messages' => [['id' => 'wamid.' . uniqid()]]])]);
        Setting::put('whatsapp_auto', '1', 'whatsapp');
        $client = Client::create(['name' => 'Luis', 'phone' => '0998887777', 'client_type' => 'individual']);
        $appointment = Appointment::create(['client_id' => $client->id, 'type' => 'home_visit', 'title' => 'Visita',
            'scheduled_at' => today()->addDay()->setTime(10, 0), 'duration_min' => 60, 'status' => 'confirmed']);
        $ready = $this->order(['status' => 'ready']);
        OrderStatusHistory::create(['order_id' => $ready->id, 'from_status' => 'repairing', 'to_status' => 'ready', 'changed_at' => now()->subDays(10)]);
        Order::whereKey($ready->id)->update(['updated_at' => now()->subDays(10)]);

        $this->artisan('solarfix:whatsapp-reminders')->expectsOutputToContain('Recordatorios enviados: 2')->assertSuccessful();
        $this->assertTrue($appointment->fresh()->reminder_sent);

        // Al día siguiente no se repiten.
        $this->artisan('solarfix:whatsapp-reminders')->expectsOutputToContain('Recordatorios enviados: 0');
        Http::assertSentCount(2);
    }

    public function test_settings_test_message_uses_hello_world(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['messages' => [['id' => 'wamid.T']]])]);

        $this->actingAs(User::factory()->role('admin')->create())->post(route('settings.test-whatsapp'), ['test_phone' => '0991234567'])
            ->assertSessionHas('success');
        Http::assertSent(fn (HttpRequest $r) => $r['template']['name'] === 'hello_world' && $r['to'] === '593991234567');
    }

    public function test_webhook_rejects_everything_without_app_secret(): void
    {
        config(['services.whatsapp.app_secret' => null]);
        $payload = ['entry' => [['changes' => [['value' => ['messages' => [['from' => '593991234567', 'id' => 'wamid.Z', 'type' => 'text', 'text' => ['body' => 'falso']]]]]]]]];

        $this->postJson('/webhooks/whatsapp', $payload)->assertForbidden();
        $this->assertSame(0, WhatsappLog::count());
    }
}
