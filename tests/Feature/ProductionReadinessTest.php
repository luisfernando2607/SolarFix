<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Support\SystemCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_sent(): void
    {
        $this->get(route('login'))
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_system_check_flags_default_password_and_missing_data(): void
    {
        User::factory()->role('admin')->create(['name' => 'Admin Viejo', 'password' => bcrypt('password')]);

        $checks = collect(SystemCheck::run())->keyBy('label');
        $this->assertSame('fail', $checks['Sin contraseñas por defecto']['status']);
        $this->assertStringContainsString('Admin Viejo', $checks['Sin contraseñas por defecto']['help']);
        $this->assertSame('warn', $checks['RUC del negocio']['status']);

        Setting::put('company_tax_id', '0991234567001', 'general');
        $this->assertSame('ok', collect(SystemCheck::run())->keyBy('label')['RUC del negocio']['status']);
    }

    public function test_system_page_for_admins_only(): void
    {
        $this->actingAs(User::factory()->role('admin')->create())->get(route('system.index'))->assertOk()->assertSee('Estado del sistema');
        $this->actingAs(User::factory()->role('receptionist')->create())->get(route('system.index'))->assertForbidden();
        $this->artisan('solarfix:check')->expectsOutputToContain('Respaldo reciente');
    }

    public function test_documents_use_business_and_branch_data(): void
    {
        Setting::put('company_name', 'Taller Solar Norte', 'general');
        Setting::put('company_tax_id', '0991234567001', 'general');
        Setting::put('company_phone', '042000000', 'general');
        $quito = Branch::create(['name' => 'Quito', 'code' => 'QTO', 'is_active' => true, 'phone' => '022111111', 'address' => 'Av. Amazonas 1']);
        $client = Client::create(['name' => 'Ana', 'phone' => '0991234567', 'client_type' => 'individual']);
        $order = Order::create([
            'branch_id' => $quito->id, 'client_id' => $client->id, 'order_number' => 'ORD-QTO-1', 'device_type' => 'celular',
            'declared_fault' => 'x', 'unlock_type' => 'none', 'status' => 'ready', 'entry_date' => today(), 'labor_cost' => 50,
        ]);
        $order->recalculateTotals();
        $admin = User::factory()->role('admin')->create();

        $this->actingAs($admin)->get(route('orders.ticket', $order))
            ->assertSee('Taller Solar Norte')->assertSee('RUC 0991234567001')->assertSee('022111111')->assertSee('Av. Amazonas 1');
        $this->get(route('tracking.show', $order->tracking_token))->assertSee('022111111');
        $this->assertStringContainsString('Taller Solar Norte', \App\Services\WhatsAppMessage::build('ready', $order));
        $this->assertStringContainsString('Av. Amazonas 1', \App\Services\WhatsAppMessage::build('ready', $order));

        $this->actingAs($admin)->post(route('invoices.generate-from-order', $order));
        $html = view('invoices.pdf', ['invoice' => Invoice::first()->load(['items', 'order']), 'invoicePayments' => collect(), 'statuses' => Invoice::statuses()])->render();
        $this->assertStringContainsString('RUC: 0991234567001', $html);
        $this->assertStringNotContainsString('1234567890001', $html);
    }
}
