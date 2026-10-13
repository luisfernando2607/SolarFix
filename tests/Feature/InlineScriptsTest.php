<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Revisa la sintaxis del JavaScript escrito dentro de las vistas (los tests de PHP no lo
 * ejecutan): un error de comillas deja sin funcionar toda la página en el navegador.
 */
class InlineScriptsTest extends TestCase
{
    use RefreshDatabase;

    public function test_inline_scripts_have_valid_syntax(): void
    {
        if (! Process::run(['node', '--version'])->successful()) {
            $this->markTestSkipped('Node no está instalado.');
        }

        $admin = User::factory()->role('admin')->create();
        $client = Client::create(['name' => 'Ana', 'phone' => '0991234567', 'client_type' => 'individual']);
        $order = Order::create(['client_id' => $client->id, 'order_number' => 'T-1', 'device_type' => 'celular', 'declared_fault' => 'x',
            'unlock_type' => 'none', 'status' => 'ready', 'entry_date' => today(), 'labor_cost' => 10]);
        $order->recalculateTotals();
        $this->actingAs($admin)->post(route('invoices.generate-from-order', $order));
        $appointment = Appointment::create(['client_id' => $client->id, 'type' => 'home_visit', 'title' => 'Visita', 'scheduled_at' => now()->addDay(), 'duration_min' => 60, 'status' => 'pending']);
        $this->actingAs($admin)->post(route('purchase-orders.store'));

        $pages = [
            route('dashboard'), route('orders.index'), route('orders.create'), route('orders.show', $order), route('orders.edit', $order),
            route('clients.show', $client), route('invoices.create'), route('invoices.show', Invoice::first()), route('sales.create'),
            route('appointments.create'), route('appointments.show', $appointment), route('maintenance-plans.create'),
            route('purchase-orders.show', PurchaseOrder::first()), route('settings.edit'), route('reports.index'), route('cash.index'),
            route('spare-parts.index'), route('two-factor.show'),
        ];

        $dir = sys_get_temp_dir() . '/solarfix-js-' . uniqid();
        mkdir($dir);
        $problems = [];
        foreach ($pages as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>#s', $html, $matches);
            foreach ($matches[1] as $i => $script) {
                $file = "{$dir}/script-{$i}.js";
                file_put_contents($file, $script);
                $check = Process::run(['node', '--check', $file]);
                if (! $check->successful()) {
                    $problems[] = $url . ' → ' . trim(strtok($check->errorOutput(), "\n")) . ' ' . trim(collect(explode("\n", $check->errorOutput()))->first(fn ($l) => str_contains($l, 'Error')) ?? '');
                }
            }
        }
        array_map('unlink', glob("{$dir}/*.js"));
        rmdir($dir);

        $this->assertSame([], $problems, "JavaScript con errores:\n" . implode("\n", $problems));
    }
}
