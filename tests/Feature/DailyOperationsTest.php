<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderReview;
use App\Models\OrderStatusHistory;
use App\Models\SparePart;
use App\Models\User;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DailyOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attributes = []): Order
    {
        $client = Client::create(['name' => 'Ana', 'phone' => '0991234567', 'client_type' => 'individual']);

        return Order::create($attributes + [
            'client_id' => $client->id, 'order_number' => 'T-' . uniqid(), 'device_type' => 'celular', 'declared_fault' => 'x',
            'unlock_type' => 'none', 'status' => 'repairing', 'entry_date' => today(), 'labor_cost' => 40, 'total_amount' => 40, 'balance_due' => 40,
        ]);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('datos.csv', $content);
    }

    public function test_large_photos_are_resized_to_jpeg(): void
    {
        Storage::fake('local');
        $admin = User::factory()->role('admin')->create();
        $order = $this->order();

        $this->actingAs($admin)->post(route('orders.photo.upload', $order), [
            'photo' => UploadedFile::fake()->image('camara.png', 4000, 3000),
        ]);

        $path = $order->photos()->value('file_path');
        $this->assertStringEndsWith('.jpg', $path);
        [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($path));
        $this->assertSame([1600, 1200], [$width, $height]);
    }

    public function test_import_clients_skips_existing_and_reports_errors(): void
    {
        $admin = User::factory()->role('admin')->create();
        Client::create(['name' => 'Ya Existe', 'phone' => '0991111111', 'client_type' => 'individual']);

        $this->actingAs($admin)->get(route('import.template', 'clients'))->assertOk();
        $this->actingAs($admin)->post(route('import.store'), ['type' => 'clients', 'file' => $this->csv(
            "\xEF\xBB\xBFNombre;Cédula;Teléfono;Correo;Dirección\n"
            . "María Pérez;0912345678;099 222 2222;maria@correo.com;Av. 1\n"
            . "Repetido;;0991111111;;\n"
            . ";0911111111;;;\n"
            . "Mal Correo;;0993333333;no-es-correo;\n"
            . "Empresa SA;0991234567001;042555555;;\n"
            . "Sin Teléfono;;;;\n"
        )])->assertSessionMissing('error')->assertSessionHas('importResult', fn ($r) => $r['created'] === 2 && $r['skipped'] === 1 && count($r['errors']) === 3);

        $this->assertSame('business', Client::where('name', 'Empresa SA')->value('client_type'));
        $this->assertSame('maria@correo.com', Client::where('name', 'María Pérez')->value('email'));
    }

    public function test_import_parts_creates_updates_and_sets_initial_stock(): void
    {
        $admin = User::factory()->role('admin')->create();
        $existing = SparePart::create(['name' => 'Viejo', 'code' => 'PAN-A15', 'category' => 'X', 'cost_price' => 1, 'sale_price' => 2, 'min_stock' => 0]);

        $this->actingAs($admin)->post(route('import.store'), ['type' => 'parts', 'file' => $this->csv(
            "nombre,codigo,categoria,costo,precio,stock_minimo,stock\n"
            . "Pantalla A15,PAN-A15,Pantallas,\"18,50\",45,2,5\n"
            . "Funda,FUN-1,Accesorios,1.234.5,8,1,10\n"
            . "Cargador,CAR-1,Accesorios,3,9,1,20\n"
        )])->assertSessionHas('importResult', fn ($r) => $r['created'] === 1 && $r['updated'] === 1 && count($r['errors']) === 1);

        $existing->refresh();
        $this->assertSame('Pantalla A15', $existing->name);
        $this->assertEquals(18.5, $existing->cost_price);
        $this->assertSame(5, $existing->stockIn(Branch::mainId()));
        $this->assertSame(20, SparePart::where('code', 'CAR-1')->first()->stock_quantity);
        $this->assertSame(0, SparePart::where('code', 'FUN-1')->count());

        $this->actingAs(User::factory()->role('receptionist')->create())->get(route('import.index'))->assertForbidden();
    }

    public function test_technician_report_shows_days_rating_and_reworks(): void
    {
        $admin = User::factory()->role('admin')->create();
        $tech = User::factory()->role('technician')->create(['name' => 'Carlos Técnico']);
        $order = $this->order(['user_id' => $tech->id, 'status' => 'delivered', 'entry_date' => today()->subDays(3), 'delivery_date' => today()]);
        OrderStatusHistory::create(['order_id' => $order->id, 'from_status' => 'ready', 'to_status' => 'delivered', 'changed_at' => now()]);
        OrderReview::create(['order_id' => $order->id, 'rating' => 4, 'created_at' => now()]);
        $this->order(['parent_order_id' => $order->id, 'status' => 'warranty']);

        $this->actingAs($admin)->get(route('reports.index'))
            ->assertViewHas('byTechnician', fn ($rows) => ($row = $rows->firstWhere('name', 'Carlos Técnico'))
                && $row['avg_days'] == 3 && $row['rating'] == 4 && $row['reworks'] === 1);
    }

    public function test_pickup_reminder_for_orders_waiting_too_long(): void
    {
        $desk = User::factory()->role('receptionist')->create();
        $order = $this->order(['status' => 'ready', 'branch_id' => $desk->branch_id]);
        OrderStatusHistory::create(['order_id' => $order->id, 'from_status' => 'repairing', 'to_status' => 'ready', 'changed_at' => now()->subDays(10)]);

        $this->assertStringContainsString('desde hace 10 días', WhatsAppMessage::build('pickup_reminder', $order));
        $this->actingAs($desk)->get(route('dashboard'))->assertSee('Recordar retiro');
        $this->actingAs($desk)->get(route('orders.show', $order))->assertSee('Recordatorio de retiro');

        $this->actingAs($desk)->post(route('orders.whatsapp', [$order, 'pickup_reminder']))->assertRedirect();
        $this->assertSame('pickup_reminder', $order->whatsappLogs()->value('trigger_event'));
    }
}
