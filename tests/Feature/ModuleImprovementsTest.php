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

    // --- 5. Costo promedio ponderado ---

    public function test_purchase_updates_weighted_average_cost(): void
    {
        $part = \App\Models\SparePart::create(['name' => 'Capacitor 35uF', 'cost_price' => 20, 'sale_price' => 45, 'min_stock' => 1]);
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('spare-parts.receive', $part), ['quantity' => 10, 'unit_cost' => 20]);

        $this->actingAs($admin)->post(route('spare-parts.receive', $part), ['quantity' => 2, 'unit_cost' => 30]);

        $part->refresh();
        $this->assertSame(12, $part->stock_quantity);
        $this->assertEquals(21.67, $part->cost_price); // (10×20 + 2×30) / 12
        $this->assertEquals(30, $part->movements()->where('type', 'in')->latest('id')->first()->unit_cost);
    }

    public function test_purchase_with_no_stock_takes_new_cost(): void
    {
        $part = \App\Models\SparePart::create(['name' => 'Filtro', 'cost_price' => 5, 'sale_price' => 12, 'min_stock' => 1]);

        $this->actingAs($this->admin())->post(route('spare-parts.receive', $part), ['quantity' => 3, 'unit_cost' => 7]);

        $this->assertEquals(7, $part->fresh()->cost_price);
    }

    // --- 6. Mantenimientos preventivos recurrentes ---

    private function plan(array $attributes = []): \App\Models\MaintenancePlan
    {
        return \App\Models\MaintenancePlan::create($attributes + [
            'client_id' => $this->client(['address' => 'Urb. Los Pinos 12'])->id,
            'title' => 'Split sala 12000 BTU',
            'address' => 'Urb. Los Pinos 12',
            'interval_months' => 6,
            'next_due_date' => today()->addDays(5),
            'is_active' => true,
        ]);
    }

    public function test_receptionist_creates_plan(): void
    {
        $client = $this->client();

        $this->actingAs(User::factory()->role('receptionist')->create())->post(route('maintenance-plans.store'), [
            'client_id' => $client->id, 'title' => 'Split dormitorio', 'interval_months' => 4,
            'next_due_date' => today()->addMonths(4)->toDateString(), 'is_active' => '1',
        ])->assertRedirect(route('clients.show', $client));

        $this->assertSame(4, \App\Models\MaintenancePlan::first()->interval_months);
    }

    public function test_due_list_and_dashboard(): void
    {
        $due = $this->plan(['title' => 'Vence pronto']);
        $overdue = $this->plan(['title' => 'Ya vencido', 'next_due_date' => today()->subDays(3)]);
        $this->plan(['title' => 'Falta mucho', 'next_due_date' => today()->addMonths(3)]);
        $this->plan(['title' => 'Inactivo', 'next_due_date' => today(), 'is_active' => false]);
        $receptionist = User::factory()->role('receptionist')->create();

        $this->actingAs($receptionist)->get(route('maintenance-plans.index'))
            ->assertOk()
            ->assertViewHas('plans', fn ($p) => $p->pluck('id')->all() === [$overdue->id, $due->id]);
        $this->assertSame('overdue', $overdue->dueStatus());
        $this->assertSame('due_soon', $due->dueStatus());

        $this->actingAs($receptionist)->get(route('dashboard'))->assertSee('2 mantenimientos preventivos vencidos o por vencer');
    }

    public function test_completing_plan_appointment_schedules_next_service(): void
    {
        $plan = $this->plan();
        $receptionist = User::factory()->role('receptionist')->create();

        $this->actingAs($receptionist)->get(route('appointments.create', ['plan_id' => $plan->id]))
            ->assertOk()->assertSee('Mantenimiento preventivo · Split sala 12000 BTU')->assertSee('Urb. Los Pinos 12');

        $this->actingAs($receptionist)->post(route('appointments.store'), [
            'client_id' => $plan->client_id, 'maintenance_plan_id' => $plan->id, 'type' => 'maintenance',
            'title' => 'Mantenimiento preventivo', 'scheduled_at' => '2026-11-20T09:00', 'duration_min' => 60,
        ])->assertSessionHasNoErrors();
        $appointment = \App\Models\Appointment::first();
        $this->assertSame($plan->id, $appointment->maintenance_plan_id);

        $this->actingAs($receptionist)->patch(route('appointments.status', $appointment), ['status' => 'completed']);

        $plan->refresh();
        $this->assertSame('2026-11-20', $plan->last_done_at->toDateString());
        $this->assertSame('2027-05-20', $plan->next_due_date->toDateString());
    }

    public function test_plan_whatsapp_reminder_is_logged(): void
    {
        $plan = $this->plan(['next_due_date' => today()->subDay()]);

        $response = $this->actingAs(User::factory()->role('receptionist')->create())->post(route('maintenance-plans.whatsapp', $plan));

        $this->assertStringStartsWith('https://wa.me/593991234567', $response->headers->get('Location'));
        $log = \App\Models\WhatsappLog::first();
        $this->assertSame('maintenance_due', $log->trigger_event);
        $this->assertStringContainsString('ya le corresponde', $log->message_body);
        $this->assertStringContainsString('Split sala 12000 BTU', $log->message_body);
    }

    public function test_technicians_cannot_manage_plans(): void
    {
        $tech = User::factory()->role('technician')->create();

        $this->actingAs($tech)->get(route('maintenance-plans.index'))->assertForbidden();
        $this->actingAs($tech)->post(route('maintenance-plans.whatsapp', $this->plan()))->assertForbidden();
    }

    public function test_plan_screens_render(): void
    {
        $plan = $this->plan();
        $receptionist = User::factory()->role('receptionist')->create();

        $this->actingAs($receptionist)->get(route('maintenance-plans.create', ['client_id' => $plan->client_id]))->assertOk()->assertSee('Urb. Los Pinos 12');
        $this->actingAs($receptionist)->get(route('maintenance-plans.edit', $plan))->assertOk();
        $this->actingAs($receptionist)->get(route('maintenance-plans.index', ['filter' => 'all', 'q' => 'Split']))->assertOk()->assertSee('Split sala');
        $this->actingAs($receptionist)->get(route('clients.show', $plan->client_id))->assertOk()->assertSee('Mantenimientos preventivos');
    }

    // --- Cobro en recepción ---

    public function test_receptionist_records_payment_but_cannot_delete_it(): void
    {
        $receptionist = User::factory()->role('receptionist')->create();
        $order = $this->makeOrder(['labor_cost' => 50, 'total_amount' => 50, 'balance_due' => 50]);

        $this->actingAs($receptionist)->post(route('orders.payments.store', $order), ['amount' => 30, 'method' => 'cash'])
            ->assertSessionHas('success');
        $payment = \App\Models\OrderPayment::first();
        $this->assertEquals(20, $order->fresh()->balance_due);

        $this->actingAs($receptionist)->get(route('orders.payments.receipt', [$order, $payment]))->assertOk();
        $this->actingAs($receptionist)->delete(route('orders.payments.destroy', [$order, $payment]))->assertForbidden();
    }

    public function test_payment_cannot_exceed_balance(): void
    {
        $order = $this->makeOrder(['labor_cost' => 50, 'total_amount' => 50, 'balance_due' => 50]);

        $this->actingAs($this->admin())->post(route('orders.payments.store', $order), ['amount' => 60, 'method' => 'cash'])
            ->assertSessionHas('error');

        $this->assertSame(0, \App\Models\OrderPayment::count());
    }

    public function test_technician_cannot_record_payments(): void
    {
        $order = $this->makeOrder(['total_amount' => 50, 'balance_due' => 50]);

        $this->actingAs(User::factory()->role('technician')->create())
            ->post(route('orders.payments.store', $order), ['amount' => 10, 'method' => 'cash'])
            ->assertForbidden();
    }

    // --- Cobertura según el tipo de garantía ---

    public function test_labor_only_warranty_charges_parts(): void
    {
        $part = \App\Models\SparePart::create(['name' => 'Batería', 'cost_price' => 10, 'sale_price' => 25, 'min_stock' => 0]);
        $part->setBranchStock(\App\Models\Branch::mainId(), 5);
        $admin = $this->admin();

        foreach (['labor' => 25, 'parts' => 0, 'full' => 0] as $type => $expected) {
            $original = $this->makeOrder(['status' => 'delivered', 'delivery_date' => today(), 'warranty_days' => 30]);
            $original->issueWarranty()->update(['warranty_type' => $type]);
            $this->actingAs($admin)->post(route('warranties.claim.store', $original), ['declared_fault' => 'Falla otra vez']);
            $claim = Order::where('parent_order_id', $original->id)->first();

            $this->actingAs($admin)->post(route('orders.parts.store', $claim), ['spare_part_id' => $part->id, 'quantity' => 1]);

            $this->assertEquals($expected, $claim->fresh()->total_amount, "Garantía {$type}");
            $this->actingAs($admin)->get(route('orders.show', $claim))->assertOk()->assertSee(\App\Models\Warranty::types()[$type]);
        }
    }

    // --- Notificaciones internas ---

    public function test_technician_notified_when_order_assigned(): void
    {
        $tech = User::factory()->role('technician')->create();
        $receptionist = User::factory()->role('receptionist')->create();
        $order = $this->makeOrder();

        $payload = [
            'client_id' => $order->client_id, 'device_type' => 'celular', 'declared_fault' => 'No enciende',
            'unlock_type' => 'none', 'entry_date' => now()->toDateString(), 'user_id' => (string) $tech->id,
        ];
        $this->actingAs($receptionist)->put(route('orders.update', $order), $payload);
        // Guardar de nuevo sin cambiar el técnico no vuelve a notificar.
        $this->actingAs($receptionist)->put(route('orders.update', $order), $payload + ['notes' => 'otra edición']);

        $this->assertSame(1, $tech->notifications()->count());
        $notification = $tech->notifications()->first();
        $this->assertStringContainsString($order->order_number, $notification->data['body']);

        $this->actingAs($tech)->get(route('dashboard'))->assertSee('1 sin leer');
        $this->actingAs($tech)->get(route('notifications.open', $notification->id))->assertRedirect(route('orders.show', $order));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_self_assignment_does_not_notify(): void
    {
        $tech = User::factory()->role('technician')->create();

        $this->actingAs($tech)->post(route('orders.store'), [
            'client_name' => 'Nuevo', 'client_phone' => '0990000000', 'device_type' => 'celular',
            'declared_fault' => 'x', 'unlock_type' => 'none', 'entry_date' => now()->toDateString(),
        ]);

        $this->assertSame(0, $tech->notifications()->count());
    }

    public function test_technician_notified_for_appointment_and_warranty_claim(): void
    {
        $tech = User::factory()->role('technician')->create();
        $receptionist = User::factory()->role('receptionist')->create();
        $client = $this->client();

        $this->actingAs($receptionist)->post(route('appointments.store'), [
            'client_id' => $client->id, 'user_id' => $tech->id, 'type' => 'home_visit', 'title' => 'Visita técnica',
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'), 'duration_min' => 60,
        ]);

        $original = $this->makeOrder(['status' => 'delivered', 'delivery_date' => today(), 'warranty_days' => 30, 'user_id' => $tech->id]);
        $original->issueWarranty();
        $this->actingAs($receptionist)->post(route('warranties.claim.store', $original), ['declared_fault' => 'Volvió la falla']);

        $this->assertEqualsCanonicalizing(['Cita asignada', 'Reclamo de garantía asignado'], $tech->notifications->pluck('data.title')->all());
    }

    public function test_notification_page_and_mark_all_read(): void
    {
        $tech = User::factory()->role('technician')->create();
        \App\Notifications\AssignmentNotification::notify($tech->id, 'Prueba', 'Cuerpo', route('dashboard'));

        $this->actingAs($tech)->get(route('notifications.index'))->assertOk()->assertSee('Prueba');
        $this->actingAs($tech)->post(route('notifications.read-all'));

        $this->assertSame(0, $tech->unreadNotifications()->count());
    }

    public function test_cannot_open_other_users_notification(): void
    {
        $tech = User::factory()->role('technician')->create();
        \App\Notifications\AssignmentNotification::notify($tech->id, 'Privada', 'x', route('dashboard'));
        $id = $tech->notifications()->first()->id;

        $this->actingAs(User::factory()->role('technician')->create())->get(route('notifications.open', $id))->assertNotFound();
    }

    // --- Control de calidad obligatorio ---

    public function test_quality_check_can_be_required_to_mark_ready(): void
    {
        $this->seed(\Database\Seeders\ChecklistSeeder::class);
        $admin = $this->admin();
        $order = $this->makeOrder(['status' => 'repairing']);
        $payload = [
            'client_id' => $order->client_id, 'device_type' => 'celular', 'declared_fault' => 'No enciende',
            'unlock_type' => 'none', 'entry_date' => now()->toDateString(), 'status' => 'ready',
        ];

        // Desactivado (por defecto): se puede marcar como lista.
        $this->actingAs($admin)->put(route('orders.update', $order), $payload)->assertSessionHasNoErrors();
        $order->refresh()->update(['status' => 'repairing']);

        \App\Models\Setting::put('require_quality_check', '1', 'orders');
        $this->actingAs($admin)->put(route('orders.update', $order), $payload)->assertSessionHasErrors('status');
        $this->assertSame('repairing', $order->fresh()->status);

        $results = \App\Models\Checklist::for('celular', 'delivery')->activeItems()->pluck('id')->mapWithKeys(fn ($id) => [$id => 'ok'])->all();
        $this->actingAs($admin)->put(route('orders.checklists.update', [$order, 'delivery']), ['results' => $results]);

        $this->actingAs($admin)->put(route('orders.update', $order), $payload)->assertSessionHasNoErrors();
        $this->assertSame('ready', $order->fresh()->status);
    }

    public function test_required_quality_check_ignored_without_checklist(): void
    {
        \App\Models\Setting::put('require_quality_check', '1', 'orders');
        $order = $this->makeOrder(['status' => 'repairing', 'device_type' => 'lavador']);

        $this->actingAs($this->admin())->put(route('orders.update', $order), [
            'client_id' => $order->client_id, 'device_type' => 'lavador', 'declared_fault' => 'x',
            'unlock_type' => 'none', 'entry_date' => now()->toDateString(), 'status' => 'ready',
        ])->assertSessionHasNoErrors();
    }

    // --- Proveedores ---

    public function test_purchase_from_supplier_appears_in_its_history(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('suppliers.store'), ['name' => 'Repuestos Guayaquil', 'ruc' => '0991234567001', 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $supplier = \App\Models\Supplier::first();
        $part = \App\Models\SparePart::create(['name' => 'Pantalla A15', 'cost_price' => 20, 'sale_price' => 45, 'min_stock' => 1]);

        $this->actingAs(User::factory()->role('technician')->create())->post(route('spare-parts.receive', $part), [
            'quantity' => 4, 'unit_cost' => 18.5, 'supplier_id' => $supplier->id, 'reason' => 'Factura 001-001-123',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Pantalla A15')
            ->assertSee('Factura 001-001-123')
            ->assertViewHas('total', fn ($t) => $t == 74);
        $this->actingAs($admin)->get(route('spare-parts.show', $part))->assertSee('Repuestos Guayaquil');
        $this->actingAs($admin)->get(route('suppliers.index', ['q' => '0991234567001']))->assertOk()->assertSee('Repuestos Guayaquil');
    }

    public function test_only_admins_manage_suppliers(): void
    {
        $this->actingAs(User::factory()->role('technician')->create())->get(route('suppliers.index'))->assertForbidden();
        $this->actingAs(User::factory()->role('receptionist')->create())->post(route('suppliers.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->admin())->get(route('suppliers.create'))->assertOk();
    }
}
