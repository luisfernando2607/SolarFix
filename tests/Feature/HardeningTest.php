<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::factory()->role('admin')->create();
    }

    private function makeOrder(array $attributes = []): Order
    {
        $client = Client::create(['name' => 'Pedro Gómez', 'phone' => '0987654321', 'id_document' => '1712345678', 'client_type' => 'individual']);

        return Order::create($attributes + [
            'client_id' => $client->id,
            'order_number' => 'TEST-' . uniqid(),
            'device_type' => 'celular',
            'declared_fault' => 'Pantalla rota',
            'unlock_type' => 'none',
            'status' => 'received',
            'entry_date' => now()->toDateString(),
        ]);
    }

    // --- Fotos privadas ---

    public function test_uploaded_photo_is_private_and_served_through_app(): void
    {
        $order = $this->makeOrder();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('orders.photo.upload', $order), [
            'photo' => UploadedFile::fake()->image('equipo.jpg'),
        ]);

        $photo = OrderPhoto::first();
        Storage::disk('local')->assertExists($photo->file_path);
        Storage::disk('public')->assertMissing($photo->file_path);

        $this->actingAs($admin)->get(route('orders.photo.show', [$order, $photo]))->assertOk();
    }

    public function test_photo_requires_login_and_visibility(): void
    {
        $other = User::factory()->role('technician')->create();
        $order = $this->makeOrder(['user_id' => $other->id]);
        Storage::disk('local')->put('order_photos/x.jpg', 'img');
        $photo = OrderPhoto::create(['order_id' => $order->id, 'stage' => 'reception', 'file_path' => 'order_photos/x.jpg']);

        $this->get(route('orders.photo.show', [$order, $photo]))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->role('technician')->create())
            ->get(route('orders.photo.show', [$order, $photo]))->assertForbidden();
    }

    public function test_photo_must_belong_to_order(): void
    {
        $order = $this->makeOrder();
        $otherOrder = $this->makeOrder();
        Storage::disk('local')->put('order_photos/y.jpg', 'img');
        $photo = OrderPhoto::create(['order_id' => $otherOrder->id, 'stage' => 'reception', 'file_path' => 'order_photos/y.jpg']);

        $this->actingAs($this->admin())->get(route('orders.photo.show', [$order, $photo]))->assertNotFound();
    }

    public function test_legacy_public_photo_is_still_served(): void
    {
        $order = $this->makeOrder();
        Storage::disk('public')->put('order_photos/viejo.jpg', 'img');
        $photo = OrderPhoto::create(['order_id' => $order->id, 'stage' => 'reception', 'file_path' => 'order_photos/viejo.jpg']);

        $this->actingAs($this->admin())->get(route('orders.photo.show', [$order, $photo]))->assertOk();
    }

    public function test_deleting_photo_removes_file(): void
    {
        $order = $this->makeOrder();
        Storage::disk('local')->put('order_photos/z.jpg', 'img');
        $photo = OrderPhoto::create(['order_id' => $order->id, 'stage' => 'reception', 'file_path' => 'order_photos/z.jpg']);

        $this->actingAs($this->admin())->delete(route('orders.photo.delete', [$order, $photo]));

        Storage::disk('local')->assertMissing('order_photos/z.jpg');
        $this->assertModelMissing($photo);
    }

    public function test_order_pages_link_photos_through_app(): void
    {
        $order = $this->makeOrder();
        Storage::disk('local')->put('order_photos/w.jpg', 'img');
        $photo = OrderPhoto::create(['order_id' => $order->id, 'stage' => 'reception', 'file_path' => 'order_photos/w.jpg']);
        $admin = $this->admin();

        $url = route('orders.photo.show', [$order, $photo]);
        $this->actingAs($admin)->get(route('orders.show', $order))->assertSee($url)->assertDontSee('/storage/order_photos');
        $this->actingAs($admin)->get(route('orders.edit', $order))->assertSee($url);
    }

    // --- Búsqueda de clientes ---

    public function test_client_search_by_name_document_or_phone(): void
    {
        $this->makeOrder();
        Client::create(['name' => 'Otra Persona', 'phone' => '0911111111', 'client_type' => 'individual']);
        $admin = $this->admin();

        foreach (['Pedro', '1712345678', '0987654321'] as $q) {
            $this->actingAs($admin)->getJson(route('clients.search', ['q' => $q]))
                ->assertOk()
                ->assertJsonCount(1)
                ->assertJsonPath('0.name', 'Pedro Gómez')
                ->assertJsonPath('0.label', 'Pedro Gómez · 1712345678');
        }
    }

    public function test_client_search_is_limited_and_available_to_technicians(): void
    {
        for ($i = 0; $i < 20; $i++) {
            Client::create(['name' => "Cliente $i", 'phone' => '09' . str_pad($i, 8, '0'), 'client_type' => 'individual']);
        }

        // Técnicos: pocos resultados (solo para elegir al cliente); recepción: hasta 15.
        $this->actingAs(User::factory()->role('technician')->create())
            ->getJson(route('clients.search', ['q' => 'Cliente']))
            ->assertOk()
            ->assertJsonCount(5);

        $this->actingAs(User::factory()->role('receptionist')->create())
            ->getJson(route('clients.search', ['q' => 'Cliente']))
            ->assertOk()
            ->assertJsonCount(15);
    }

    public function test_order_form_no_longer_embeds_all_clients(): void
    {
        Client::create(['name' => 'Cliente Secreto', 'phone' => '0900000000', 'client_type' => 'individual']);

        $this->actingAs($this->admin())->get(route('orders.create'))
            ->assertOk()
            ->assertDontSee('Cliente Secreto')
            ->assertSee('clients-search', false);
    }

    // --- Menú en celular ---

    public function test_layout_has_mobile_menu_toggle(): void
    {
        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertSee('id="sidebarToggle"', false)
            ->assertSee('id="sidebar"', false);
    }
}
