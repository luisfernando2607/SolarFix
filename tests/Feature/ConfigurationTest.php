<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\MaintenancePlan;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->role('admin')->create();
    }

    private function settingsPayload(array $overrides = []): array
    {
        return $overrides + [
            'company_name' => 'SolarFix', 'default_warranty_days' => 30, 'warranty_alert_days' => 15,
            'iva_percent' => 15, 'prices_include_iva' => '1', 'stale_order_days' => 5, 'approval_alert_hours' => 72,
            'commission_base' => 'service', 'order_prefix' => 'ORD', 'invoice_prefix' => 'FAC',
            'currency_symbol' => '$', 'diagnosis_cost_default' => 0, 'maintenance_due_days' => 15,
            'payment_methods' => [
                ['key' => 'cash', 'label' => 'Efectivo'], ['key' => 'transfer', 'label' => 'Transferencia'],
                ['key' => 'card', 'label' => 'Tarjeta'], ['key' => 'other', 'label' => 'Otro'],
            ],
        ];
    }

    private function orderPayload(): array
    {
        return [
            'client_name' => 'Ana', 'client_phone' => '0991234567', 'device_type' => 'celular',
            'declared_fault' => 'x', 'unlock_type' => 'none', 'entry_date' => now()->toDateString(),
        ];
    }

    public function test_prefixes_are_saved_uppercase_and_used(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('settings.update'), $this->settingsPayload(['order_prefix' => 'sf', 'invoice_prefix' => 'fe']))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('orders.store'), $this->orderPayload());
        $order = Order::latest('id')->first();
        $this->assertSame('SF-MAT-' . now()->format('Ymd') . '-0001', $order->order_number);

        $invoice = Invoice::createWithUniqueNumber(['client_name' => 'X', 'subtotal' => 1, 'issued_at' => now(), 'status' => 'emitida']);
        $this->assertSame('FE-0001', $invoice->invoice_number);
    }

    public function test_invalid_prefix_is_rejected(): void
    {
        $this->actingAs($this->admin())->put(route('settings.update'), $this->settingsPayload(['order_prefix' => 'TALLER-1']))
            ->assertSessionHasErrors('order_prefix');
    }

    public function test_currency_symbol_is_used_in_screens_and_messages(): void
    {
        Setting::put('currency_symbol', 'US$', 'general');
        $admin = $this->admin();
        $client = Client::create(['name' => 'Ana', 'phone' => '0991234567', 'client_type' => 'individual']);
        $order = Order::create([
            'client_id' => $client->id, 'order_number' => 'T-1', 'device_type' => 'celular', 'declared_fault' => 'x',
            'unlock_type' => 'none', 'status' => 'diagnosing', 'entry_date' => now()->toDateString(),
            'labor_cost' => 40, 'total_amount' => 40, 'balance_due' => 40,
        ]);

        $this->actingAs($admin)->get(route('orders.show', $order))->assertSee('US$40.00');
        $this->assertStringContainsString('US$40.00', \App\Services\WhatsAppMessage::build('budget', $order));
        $this->assertSame('US$1,234.50', money(1234.5));
    }

    public function test_default_diagnosis_cost_is_proposed(): void
    {
        Setting::put('diagnosis_cost_default', '12.5', 'orders');

        $this->actingAs($this->admin())->get(route('orders.create'))->assertSee('value="12.5"', false);
    }

    public function test_maintenance_due_window_is_configurable(): void
    {
        $client = Client::create(['name' => 'Ana', 'phone' => '0991234567', 'client_type' => 'individual']);
        $plan = MaintenancePlan::create([
            'client_id' => $client->id, 'title' => 'Split', 'interval_months' => 6, 'next_due_date' => today()->addDays(20), 'is_active' => true,
        ]);

        $this->assertSame('ok', $plan->dueStatus());
        Setting::put('maintenance_due_days', '30', 'alerts');
        $this->assertSame('due_soon', $plan->fresh()->dueStatus());
        $this->assertSame(1, MaintenancePlan::due()->count());
    }

    public function test_settings_screen_shows_new_fields(): void
    {
        $this->actingAs($this->admin())->get(route('settings.edit'))
            ->assertOk()->assertSee('Prefijo de orden')->assertSee('Símbolo de moneda');
    }

    // --- Métodos de pago ---

    public function test_admin_adds_renames_and_removes_payment_methods(): void
    {
        $this->actingAs($this->admin())->put(route('settings.update'), $this->settingsPayload(['payment_methods' => [
            ['key' => 'cash', 'label' => ''],                          // efectivo no se puede quitar
            ['key' => 'transfer', 'label' => 'Transferencia bancaria'], // renombrar conserva la clave
            ['key' => 'card', 'label' => ''],                           // quitar
            ['key' => '', 'label' => 'Deuna'],                          // nuevo
            ['key' => '', 'label' => 'Pago Móvil Ñ'],
        ]]))->assertSessionHasNoErrors();

        $this->assertSame([
            'cash' => 'Efectivo',
            'transfer' => 'Transferencia bancaria',
            'deuna' => 'Deuna',
            'pago_movil_n' => 'Pago Móvil Ñ',
        ], \App\Support\PaymentMethods::all());
    }

    public function test_new_method_used_in_payments_cash_and_reports(): void
    {
        Setting::put('payment_methods', json_encode(['cash' => 'Efectivo', 'deuna' => 'Deuna']), 'billing');
        $receptionist = User::factory()->role('receptionist')->create();
        $client = Client::create(['name' => 'Ana', 'phone' => '0991234567', 'client_type' => 'individual']);
        $order = Order::create([
            'client_id' => $client->id, 'order_number' => 'T-1', 'device_type' => 'celular', 'declared_fault' => 'x',
            'unlock_type' => 'none', 'status' => 'repairing', 'entry_date' => now()->toDateString(),
            'labor_cost' => 50, 'total_amount' => 50, 'balance_due' => 50,
        ]);

        $this->actingAs($receptionist)->get(route('orders.show', $order))->assertSee('<option value="deuna">Deuna</option>', false);
        $this->actingAs($receptionist)->post(route('orders.payments.store', $order), ['amount' => 20, 'method' => 'deuna'])->assertSessionHasNoErrors();
        $this->actingAs($receptionist)->post(route('orders.payments.store', $order), ['amount' => 5, 'method' => 'card'])->assertSessionHasErrors('method');
        $this->actingAs($receptionist)->post(route('orders.payments.store', $order), ['amount' => 10, 'method' => 'cash']);

        $this->actingAs($receptionist)->get(route('cash.index'))
            ->assertViewHas('totals', fn ($t) => $t['deuna'] == 20 && $t['cash'] == 10 && $t['total'] == 30)
            ->assertViewHas('cash', fn ($c) => $c['expected'] == 10);

        $this->actingAs($receptionist)->post(route('cash.store'), ['counted_cash' => 10]);
        $this->assertEquals(20, \App\Models\CashClosing::first()->expected_other);

        $this->actingAs($this->admin())->get(route('reports.index'))
            ->assertViewHas('byMethod', fn ($m) => $m->firstWhere('label', 'Deuna')['amount'] == 20);
    }

    public function test_removed_method_keeps_its_label_in_history(): void
    {
        $client = Client::create(['name' => 'Ana', 'phone' => '0991234567', 'client_type' => 'individual']);
        $order = Order::create([
            'client_id' => $client->id, 'order_number' => 'T-2', 'device_type' => 'celular', 'declared_fault' => 'x',
            'unlock_type' => 'none', 'status' => 'repairing', 'entry_date' => now()->toDateString(),
        ]);
        $payment = \App\Models\OrderPayment::create(['order_id' => $order->id, 'amount' => 10, 'method' => 'card', 'paid_at' => now()]);

        Setting::put('payment_methods', json_encode(['cash' => 'Efectivo']), 'billing');

        $this->assertSame('Tarjeta', $payment->method_label);
        $this->actingAs($this->admin())->get(route('orders.show', $order))->assertSee('Tarjeta');
    }

    // --- Textos de WhatsApp ---

    private function budgetOrder(): Order
    {
        $client = Client::create(['name' => 'Ana', 'phone' => '0991234567', 'client_type' => 'individual']);

        return Order::create([
            'client_id' => $client->id, 'order_number' => 'T-9', 'device_type' => 'celular', 'declared_fault' => 'x',
            'unlock_type' => 'none', 'status' => 'diagnosing', 'entry_date' => now()->toDateString(),
            'labor_cost' => 40, 'total_amount' => 40, 'balance_due' => 40,
        ]);
    }

    public function test_custom_template_is_used_and_empty_variable_lines_dropped(): void
    {
        $this->actingAs($this->admin())->put(route('settings.update'), $this->settingsPayload(['wa_templates' => [
            'budget' => "Buenas {cliente}! Orden {orden}\n{diagnostico}\nTotal: {total}\nLlámenos al {telefono_negocio}",
        ]]))->assertSessionHasNoErrors();
        Setting::put('company_phone', '042-123456', 'general');

        $message = \App\Services\WhatsAppMessage::build('budget', $this->budgetOrder());

        $this->assertSame("Buenas Ana! Orden T-9\nTotal: $40.00\nLlámenos al 042-123456", $message);
    }

    public function test_unknown_variable_is_rejected(): void
    {
        $this->actingAs($this->admin())->put(route('settings.update'), $this->settingsPayload(['wa_templates' => [
            'ready' => 'Hola {clinete}, su equipo está listo',
        ]]))->assertSessionHasErrors(['wa_templates.ready' => 'Variables que no existen en este mensaje: {clinete}.']);
    }

    public function test_empty_or_default_template_falls_back_to_original(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('settings.update'), $this->settingsPayload(['wa_templates' => [
            'ready' => \App\Services\WhatsAppMessage::TEMPLATES['ready'],
            'budget' => '',
        ]]))->assertSessionHasNoErrors();

        $this->assertSame('', Setting::get('wa_template_ready', ''));
        $this->assertStringContainsString('ya fue diagnosticado', \App\Services\WhatsAppMessage::build('budget', $this->budgetOrder()));
        $this->actingAs($admin)->get(route('settings.edit'))->assertOk()->assertSee('Mensajes de WhatsApp')->assertSee('{detalle_presupuesto}');
    }
}
