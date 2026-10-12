<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\MaintenancePlan;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use App\Models\SparePart;
use App\Models\User;
use App\Services\BusinessMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminInsightsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->role('admin')->create();
    }

    private function client(array $attributes = []): Client
    {
        return Client::create($attributes + ['name' => 'Ana', 'phone' => '0991234567', 'client_type' => 'individual']);
    }

    private function makeOrder(array $attributes = []): Order
    {
        return Order::create($attributes + [
            'client_id' => $attributes['client_id'] ?? $this->client()->id, 'order_number' => 'T-' . uniqid(),
            'device_type' => 'celular', 'declared_fault' => 'x', 'unlock_type' => 'none', 'status' => 'received',
            'entry_date' => now()->toDateString(),
        ]);
    }

    private function history(Order $order, ?string $from, string $to, $at): void
    {
        OrderStatusHistory::create(['order_id' => $order->id, 'from_status' => $from, 'to_status' => $to, 'changed_at' => $at]);
    }

    // --- Indicadores ---

    public function test_monthly_metrics_and_comparison(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 12:00'));

        $a = $this->makeOrder(['status' => 'delivered', 'total_amount' => 100, 'entry_date' => '2026-10-01', 'delivery_date' => '2026-10-05']);
        $b = $this->makeOrder(['status' => 'delivered', 'total_amount' => 50, 'entry_date' => '2026-10-02', 'delivery_date' => '2026-10-04']);
        $this->history($a, 'ready', 'delivered', '2026-10-05 10:00');
        $this->history($b, 'ready', 'delivered', '2026-10-04 10:00');
        OrderPayment::create(['order_id' => $a->id, 'amount' => 100, 'method' => 'cash', 'paid_at' => '2026-10-05 10:00']);
        OrderPayment::create(['order_id' => $a->id, 'amount' => 70, 'method' => 'cash', 'paid_at' => '2026-09-15 10:00']);

        // 3 presupuestos resueltos en octubre: 2 aprobados, 1 rechazado.
        foreach (['repairing', 'repairing', 'closed_no_repair'] as $to) {
            $this->history($this->makeOrder(), 'waiting_approval', $to, '2026-10-10 10:00');
        }
        $this->makeOrder(['parent_order_id' => $a->id, 'status' => 'warranty']); // 1 reclamo

        $m = app(BusinessMetrics::class)->forMonth(now());

        $this->assertEquals(100, $m['current']['revenue']);
        $this->assertEquals(70, $m['previous']['revenue']);
        $this->assertSame(2, $m['current']['delivered']);
        $this->assertEquals(75, $m['current']['avg_ticket']);
        $this->assertEquals(3, $m['current']['avg_days']);     // (4 + 2) / 2
        $this->assertEquals(67, $m['current']['approval_rate']);
        $this->assertEquals(50, $m['current']['rework_rate']);  // 1 reclamo / 2 entregas
    }

    public function test_receivables_and_inventory_value(): void
    {
        $debtor = $this->makeOrder(['status' => 'delivered', 'total_amount' => 80, 'balance_due' => 30, 'delivery_date' => today()]);
        $this->makeOrder(['status' => 'delivered', 'total_amount' => 80, 'balance_due' => 0]);
        $this->makeOrder(['status' => 'ready', 'balance_due' => 99]); // aún no entregada: no es cuenta por cobrar
        $part = SparePart::create(['name' => 'Batería', 'cost_price' => 12.5, 'sale_price' => 30, 'min_stock' => 0]);
        $part->setBranchStock(\App\Models\Branch::mainId(), 4);

        $m = app(BusinessMetrics::class)->forMonth(now());

        $this->assertEquals(30, $m['receivables']['total']);
        $this->assertSame([$debtor->id], $m['receivables']['orders']->pluck('id')->all());
        $this->assertEquals(50, $m['inventory_value']);
    }

    public function test_indicators_visible_only_to_admins(): void
    {
        $this->makeOrder(['status' => 'delivered', 'total_amount' => 80, 'balance_due' => 30, 'delivery_date' => today()]);

        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertSee('Indicadores de')->assertSee('Cuentas por cobrar')->assertSee('$30.00');
        $this->actingAs(User::factory()->role('receptionist')->create())->get(route('dashboard'))
            ->assertViewHas('metrics', null)->assertDontSee('Cuentas por cobrar');
    }

    // --- Clientes duplicados ---

    public function test_duplicates_detected_by_normalized_phone_and_document(): void
    {
        $this->client(['name' => 'Ana Torres', 'phone' => '0991234567']);
        $this->client(['name' => 'Ana T.', 'phone' => '+593 99 123 4567']);
        $this->client(['name' => 'Luis', 'phone' => '0911111111', 'id_document' => '1712345678']);
        $this->client(['name' => 'Luis Vera', 'phone' => '0922222222', 'id_document' => '1712345678']);
        $this->client(['name' => 'Único', 'phone' => '0933333333']);

        $this->actingAs($this->admin())->get(route('clients.duplicates'))
            ->assertOk()
            ->assertViewHas('groups', fn ($g) => $g->count() === 2
                && $g->pluck('reason')->sort()->values()->all() === ['cédula', 'teléfono']);
    }

    public function test_merge_moves_history_and_fills_missing_data(): void
    {
        $keep = $this->client(['name' => 'Ana Torres', 'email' => null]);
        $dup = $this->client(['name' => 'Ana T.', 'email' => 'ana@mail.com', 'address' => 'Calle 1', 'notes' => 'Prefiere WhatsApp']);
        $order = $this->makeOrder(['client_id' => $dup->id]);
        $appointment = Appointment::create(['client_id' => $dup->id, 'type' => 'maintenance', 'title' => 'x', 'scheduled_at' => now()->addDay(), 'duration_min' => 60, 'status' => 'pending']);
        $plan = MaintenancePlan::create(['client_id' => $dup->id, 'title' => 'Split', 'interval_months' => 6, 'next_due_date' => now()->addMonth(), 'is_active' => true]);

        $this->actingAs($this->admin())->post(route('clients.merge', $dup), ['target_id' => $keep->id])
            ->assertRedirect(route('clients.show', $keep));

        $this->assertSame($keep->id, $order->fresh()->client_id);
        $this->assertSame($keep->id, $appointment->fresh()->client_id);
        $this->assertSame($keep->id, $plan->fresh()->client_id);
        $keep->refresh();
        $this->assertSame('ana@mail.com', $keep->email);
        $this->assertSame('Calle 1', $keep->address);
        $this->assertStringContainsString('Prefiere WhatsApp', $keep->notes);
        $this->assertSoftDeleted($dup);
        $this->assertTrue(AuditLog::where('event', 'merged')->exists());
    }

    public function test_group_merge_keeps_chosen_client(): void
    {
        $a = $this->client(['name' => 'A']);
        $b = $this->client(['name' => 'B']);
        $c = $this->client(['name' => 'C']);
        $this->makeOrder(['client_id' => $a->id]);
        $this->makeOrder(['client_id' => $c->id]);

        $this->actingAs($this->admin())->post(route('clients.merge-group'), ['target_id' => $b->id, 'source_ids' => [$a->id, $c->id]]);

        $this->assertSame(2, Order::where('client_id', $b->id)->count());
        $this->assertSoftDeleted($a);
        $this->assertSoftDeleted($c);
        $this->assertNotSoftDeleted($b);
    }

    public function test_cannot_merge_into_itself_and_only_admins(): void
    {
        $a = $this->client();

        $this->actingAs($this->admin())->post(route('clients.merge', $a), ['target_id' => $a->id])->assertStatus(422);
        $this->actingAs(User::factory()->role('receptionist')->create())
            ->post(route('clients.merge', $a), ['target_id' => $this->client()->id])->assertForbidden();
        $this->assertNotSoftDeleted($a);
    }
}
