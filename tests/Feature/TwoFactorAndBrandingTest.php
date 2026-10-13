<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TwoFactorAndBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function enable(User $user): string
    {
        $this->actingAs($user)->post(route('two-factor.start'));
        $secret = session('two_factor.pending_secret');
        $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => Totp::code($secret, intdiv(now()->timestamp, 30))])
            ->assertSessionHas('two_factor.codes');

        return $secret;
    }

    public function test_totp_matches_rfc_vectors(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // "12345678901234567890"
        $this->assertSame('287082', Totp::code($secret, intdiv(59, 30)));
        $this->assertSame('081804', Totp::code($secret, intdiv(1111111109, 30)));
        $this->assertNotNull(Totp::verify($secret, '287082', null, 59));
        $this->assertNull(Totp::verify($secret, '287082', 1, 59)); // mismo intervalo: no se acepta dos veces
    }

    public function test_user_enables_two_factor_and_login_requires_code(): void
    {
        $user = User::factory()->role('admin')->create(['email' => 'jefe@taller.ec', 'password' => bcrypt('Secreta123')]);
        $this->actingAs($user)->get(route('two-factor.show'))->assertSee('Configurar con mi celular');

        // Código equivocado: no se activa.
        $this->actingAs($user)->post(route('two-factor.start'));
        $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasTwoFactor());

        $secret = $this->enable($user);
        $this->assertTrue($user->fresh()->hasTwoFactor());
        $this->assertCount(8, $user->fresh()->two_factor_recovery_codes);

        // Nuevo ingreso: la contraseña sola no alcanza.
        $this->post(route('logout'));
        $this->flushSession();
        $this->post('/login', ['email' => 'jefe@taller.ec', 'password' => 'Secreta123'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->travel(31)->seconds(); // intervalo siguiente (el código de la activación ya se usó)
        $this->post('/two-factor-challenge', ['code' => Totp::code($secret, intdiv(now()->timestamp, 30))])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_recovery_code_works_once(): void
    {
        $user = User::factory()->role('admin')->create(['email' => 'jefe@taller.ec', 'password' => bcrypt('Secreta123')]);
        $this->enable($user);
        $code = $user->fresh()->two_factor_recovery_codes[0];
        $this->post(route('logout'));
        $this->flushSession();

        $this->post('/login', ['email' => 'jefe@taller.ec', 'password' => 'Secreta123']);
        $this->post('/two-factor-challenge', ['code' => strtolower($code)])->assertRedirect(route('dashboard'));
        $this->assertCount(7, $user->fresh()->two_factor_recovery_codes);

        $this->post(route('logout'));
        $this->flushSession();
        $this->post('/login', ['email' => 'jefe@taller.ec', 'password' => 'Secreta123']);
        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_admins_can_be_required_to_use_two_factor(): void
    {
        Setting::put('require_admin_2fa', '1', 'security');
        $admin = User::factory()->role('admin')->create(['password' => bcrypt('Secreta123')]);

        $this->actingAs($admin)->get(route('orders.index'))->assertRedirect(route('two-factor.show'));
        $this->flushSession();
        $this->actingAs(User::factory()->role('receptionist')->create())->get(route('orders.index'))->assertOk();
        $this->flushSession();

        $this->enable($admin);
        $admin = $admin->fresh();
        $this->actingAs($admin)->get(route('orders.index'))->assertOk();
        $this->actingAs($admin)->delete(route('two-factor.disable'), ['current_password' => 'Secreta123'])->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->hasTwoFactor());
    }

    public function test_secret_is_encrypted_and_not_audited(): void
    {
        $user = User::factory()->role('admin')->create();
        $secret = $this->enable($user);

        $raw = \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $this->assertNotSame($secret, $raw);
        $this->assertStringNotContainsString($secret, \App\Models\AuditLog::where('auditable_type', User::class)->get()->toJson());
    }

    public function test_logo_appears_on_documents_and_public_pages(): void
    {
        Storage::fake('local');
        $admin = User::factory()->role('admin')->create();
        $settings = $this->actingAs($admin)->get(route('settings.edit'))->viewData('settings');

        $this->actingAs($admin)->put(route('settings.update'), array_merge(array_filter($settings, fn ($v) => $v !== null), [
            'company_name' => 'Taller Norte', 'default_warranty_days' => 30, 'warranty_alert_days' => 15, 'iva_percent' => 15,
            'stale_order_days' => 5, 'approval_alert_hours' => 72, 'commission_base' => 'service', 'order_prefix' => 'ORD',
            'invoice_prefix' => 'FAC', 'currency_symbol' => '$', 'diagnosis_cost_default' => 10, 'maintenance_due_days' => 7,
            'payment_methods' => [['key' => 'cash', 'label' => 'Efectivo']],
            'logo' => UploadedFile::fake()->image('logo.png', 1200, 600),
        ]))->assertSessionHasNoErrors();

        Branding::flush();
        $logo = Branding::logoDataUri();
        $this->assertNotNull($logo);
        [$width] = getimagesizefromstring(base64_decode(substr($logo, strlen('data:image/png;base64,'))));
        $this->assertSame(400, $width);

        $client = Client::create(['name' => 'Ana', 'phone' => '0991234567', 'client_type' => 'individual']);
        $order = Order::create(['client_id' => $client->id, 'order_number' => 'T-1', 'device_type' => 'celular', 'declared_fault' => 'x',
            'unlock_type' => 'none', 'status' => 'received', 'entry_date' => today()]);
        $this->get(route('tracking.show', $order->tracking_token))->assertSee('data:image/png;base64', false);
        $this->actingAs($admin)->get(route('orders.ticket', $order))->assertSee('data:image/png;base64', false);
    }
}
