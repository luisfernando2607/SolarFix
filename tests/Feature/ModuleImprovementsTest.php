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
}
