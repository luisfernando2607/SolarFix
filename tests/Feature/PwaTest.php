<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_valid_and_icons_exist(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/dashboard', $manifest['start_url']);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
    }

    public function test_pages_link_manifest_and_register_service_worker(): void
    {
        $this->get(route('login'))->assertSee('rel="manifest"', false);

        $this->actingAs(User::factory()->role('admin')->create())->get(route('dashboard'))
            ->assertSee('rel="manifest"', false)
            ->assertSee("navigator.serviceWorker.register('/sw.js')", false);
    }

    public function test_service_worker_does_not_cache_private_pages(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        // Solo se precachean la página sin conexión y su ícono.
        $this->assertStringContainsString("cache.addAll([OFFLINE_URL, '/icons/icon-192.png'])", $sw);
        $this->assertStringNotContainsString('cache.put', $sw);
    }
}
