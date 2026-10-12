<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderSignature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModuleImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function admin(): User
    {
        return User::factory()->role('admin')->create();
    }

    private function client(array $attributes = []): Client
    {
        return Client::create($attributes + ['name' => 'Valentina Paz', 'phone' => '0991234567', 'client_type' => 'individual']);
    }

    private function makeOrder(array $attributes = []): Order
    {
        return Order::create($attributes + [
            'client_id' => $this->client()->id,
            'order_number' => 'TEST-' . uniqid(),
            'device_type' => 'celular',
            'declared_fault' => 'No enciende',
            'unlock_type' => 'none',
            'status' => 'received',
            'entry_date' => now()->subDays(3)->toDateString(),
        ]);
    }

    // --- 1. Devolución sin reparación ---

    public function test_return_without_repair_can_be_signed(): void
    {
        Storage::fake('local');
        $tech = User::factory()->role('technician')->create(['commission_type' => 'fixed', 'commission_value' => 5]);
        $order = $this->makeOrder([
            'status' => 'closed_no_repair', 'user_id' => $tech->id, 'warranty_days' => 30,
            'diagnosis' => 'Placa dañada, cliente rechazó presupuesto', 'diagnosis_cost' => 10, 'total_amount' => 10, 'balance_due' => 10,
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('orders.signatures.create', [$order, 'delivery']))
            ->assertOk()->assertSee('Devolución sin reparación')->assertSee('Cobra el diagnóstico');

        $this->actingAs($admin)->post(route('orders.signatures.store', [$order, 'delivery']), ['signature' => self::PNG])
            ->assertSessionHas('success', 'Devolución sin reparación firmada.');

        $order->refresh();
        $this->assertSame('closed_no_repair', $order->status);
        $this->assertNotNull($order->delivery_date);
        $this->assertNull($order->warranty);
        $this->assertSame(0, Commission::count());
        $this->assertSame(1, OrderSignature::where('stage', 'delivery')->count());
        $this->assertTrue($order->statusHistory()->where('notes', 'like', 'Devolución sin reparación%')->exists());
        $this->actingAs($admin)->get(route('orders.show', $order))->assertOk();
    }

    // --- 2. Historial en la ficha del cliente ---

    public function test_client_profile_shows_history_and_totals(): void
    {
        $client = $this->client(['address' => 'Av. 6 de Diciembre']);
        $open = $this->makeOrder(['client_id' => $client->id, 'status' => 'repairing', 'total_amount' => 80, 'balance_due' => 30]);
        $done = $this->makeOrder(['client_id' => $client->id, 'status' => 'delivered', 'delivery_date' => today(), 'warranty_days' => 30, 'total_amount' => 50]);
        $done->issueWarranty();
        \App\Models\OrderPayment::create(['order_id' => $open->id, 'amount' => 50, 'method' => 'cash', 'paid_at' => now()]);
        \App\Models\OrderPayment::create(['order_id' => $done->id, 'amount' => 50, 'method' => 'cash', 'paid_at' => now()]);
        \App\Models\Appointment::create(['client_id' => $client->id, 'type' => 'maintenance', 'title' => 'Mantenimiento anual', 'scheduled_at' => now()->addDays(3), 'duration_min' => 60, 'status' => 'pending']);
        $this->makeOrder(); // de otro cliente

        $this->actingAs($this->admin())->get(route('clients.show', $client))
            ->assertOk()
            ->assertViewHas('stats', fn ($s) => $s['orders'] === 2 && $s['paid'] == 100 && $s['balance'] == 30)
            ->assertViewHas('warranties', fn ($w) => $w->count() === 1)
            ->assertSee($open->order_number)
            ->assertSee('Mantenimiento anual');
    }

    public function test_new_order_and_appointment_prefilled_from_client(): void
    {
        $client = $this->client(['name' => 'Cliente Precargado', 'address' => 'Calle Larga 123']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('orders.create', ['client_id' => $client->id]))
            ->assertOk()->assertSee('Cliente Precargado');
        $this->actingAs($admin)->get(route('appointments.create', ['client_id' => $client->id]))
            ->assertOk()->assertSee('Calle Larga 123');
    }

    // --- 3. Egresos y fondo inicial en la caja ---

    public function test_expected_cash_includes_opening_expenses_and_withdrawals(): void
    {
        $receptionist = User::factory()->role('receptionist')->create();
        $order = $this->makeOrder();
        \App\Models\OrderPayment::create(['order_id' => $order->id, 'amount' => 100, 'method' => 'cash', 'paid_at' => now()]);
        \App\Models\OrderPayment::create(['order_id' => $order->id, 'amount' => 40, 'method' => 'transfer', 'paid_at' => now()]);

        foreach ([['opening', 50, 'Fondo'], ['expense', 15, 'Pegamento'], ['withdrawal', 60, 'Depósito banco'], ['income', 5, 'Venta de funda']] as [$type, $amount, $description]) {
            $this->actingAs($receptionist)->post(route('cash.movements.store'), compact('type', 'amount', 'description'))->assertSessionHasNoErrors();
        }

        // 50 + 100 + 5 − 15 − 60 = 80 (la transferencia no cuenta como efectivo)
        $this->actingAs($receptionist)->get(route('cash.index'))
            ->assertOk()
            ->assertViewHas('cash', fn ($c) => $c['expected'] == 80 && $c['expenses'] == 15 && $c['opening'] == 50);

        $this->actingAs($receptionist)->post(route('cash.store'), ['counted_cash' => 80])->assertSessionHasNoErrors();

        $closing = \App\Models\CashClosing::first();
        $this->assertEquals(80, $closing->expected_cash);
        $this->assertEquals(0, $closing->difference);
        $this->assertEquals(15, $closing->expenses);
        $this->assertEquals(100, $closing->cash_payments);
    }

    public function test_only_admin_deletes_cash_movements(): void
    {
        $receptionist = User::factory()->role('receptionist')->create();
        $this->actingAs($receptionist)->post(route('cash.movements.store'), ['type' => 'expense', 'amount' => 10, 'description' => 'Taxi']);
        $movement = \App\Models\CashMovement::first();

        $this->actingAs($receptionist)->delete(route('cash.movements.destroy', $movement))->assertForbidden();
        $this->actingAs($this->admin())->delete(route('cash.movements.destroy', $movement))->assertRedirect();
        $this->assertModelMissing($movement);
        $this->assertTrue(\App\Models\AuditLog::where('auditable_type', 'CashMovement')->where('event', 'deleted')->exists());
    }

    public function test_cash_movement_validation(): void
    {
        $this->actingAs($this->admin())->post(route('cash.movements.store'), ['type' => 'robo', 'amount' => 0, 'description' => ''])
            ->assertSessionHasErrors(['type', 'amount', 'description']);
    }

    // --- 4. Exportación a Excel (CSV) ---

    public function test_orders_export_is_excel_friendly_csv(): void
    {
        $client = $this->client(['name' => 'José Peña', 'phone' => '0991234567']);
        $order = $this->makeOrder(['client_id' => $client->id, 'labor_cost' => 25.5, 'total_amount' => 25.5, 'balance_due' => 25.5]);

        $response = $this->actingAs($this->admin())->get(route('reports.export', ['dataset' => 'orders']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('ordenes-', $response->headers->get('Content-Disposition'));
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = array_map(fn ($l) => str_getcsv($l, ';'), array_filter(explode("\n", substr($csv, 3))));
        $this->assertSame('Orden', $lines[0][0]);
        $this->assertSame($order->order_number, $lines[1][0]);
        $this->assertSame('José Peña', $lines[1][2]);
        $this->assertSame('25,50', $lines[1][11]);
    }

    public function test_export_neutralizes_formula_injection(): void
    {
        $client = $this->client(['name' => '=HYPERLINK("http://malo")']);
        $this->makeOrder(['client_id' => $client->id]);

        $csv = $this->actingAs($this->admin())->get(route('reports.export', ['dataset' => 'orders']))->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_payments_export_respects_period_and_admin_only(): void
    {
        $order = $this->makeOrder();
        \App\Models\OrderPayment::create(['order_id' => $order->id, 'amount' => 12, 'method' => 'card', 'reference' => 'VOUCHER-1', 'paid_at' => now()]);
        \App\Models\OrderPayment::create(['order_id' => $order->id, 'amount' => 99, 'method' => 'cash', 'reference' => 'VIEJO', 'paid_at' => now()->subYear()]);

        $csv = $this->actingAs($this->admin())->get(route('reports.export', ['dataset' => 'payments']))->streamedContent();
        $this->assertStringContainsString('VOUCHER-1', $csv);
        $this->assertStringContainsString('Tarjeta', $csv);
        $this->assertStringNotContainsString('VIEJO', $csv);

        $this->actingAs(User::factory()->role('receptionist')->create())->get(route('reports.export', ['dataset' => 'payments']))->assertForbidden();
        $this->actingAs($this->admin())->get(route('reports.export', ['dataset' => 'otro']))->assertNotFound();
    }
}
