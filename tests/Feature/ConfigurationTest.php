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
        $this->assertSame('SF-' . now()->format('Ymd') . '-0001', $order->order_number);

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
}
