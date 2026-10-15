<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_sees_its_manual(): void
    {
        $tech = User::factory()->role('technician')->create();
        $this->actingAs($tech)->get(route('help.index'))->assertRedirect(route('help.show', 'tecnico'));
        $this->actingAs($tech)->get(route('help.show', 'tecnico'))->assertOk()->assertSee('Manual del Técnico')->assertSee('<h2>', false);
        $this->actingAs($tech)->get(route('help.show', 'administrador'))->assertNotFound();
        $this->flushSession();

        $desk = User::factory()->role('receptionist')->create();
        $this->actingAs($desk)->get(route('help.index'))->assertRedirect(route('help.show', 'recepcion'));
        $this->actingAs($desk)->get(route('help.show', 'cliente'))->assertOk()->assertSee('Guía para el cliente');
        $this->actingAs($desk)->get(route('help.show', 'tecnico'))->assertNotFound();
        $this->flushSession();

        $admin = User::factory()->role('admin')->create();
        foreach (['recepcion', 'tecnico', 'administrador', 'cliente'] as $manual) {
            $this->actingAs($admin)->get(route('help.show', $manual))->assertOk();
        }
    }

    public function test_manual_downloads_as_pdf(): void
    {
        $this->actingAs(User::factory()->role('receptionist')->create())->get(route('help.pdf', 'recepcion'))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_menu_links_to_help(): void
    {
        $this->actingAs(User::factory()->role('technician')->create())->get(route('dashboard'))->assertSee(route('help.index'));
    }
}
