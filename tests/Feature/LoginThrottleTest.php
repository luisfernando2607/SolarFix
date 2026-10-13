<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_many_staff_from_same_office_ip_can_log_in(): void
    {
        $users = User::factory()->count(8)->role('receptionist')->create(['password' => bcrypt('Clave12345')]);

        foreach ($users as $user) {
            $this->post('/login', ['email' => $user->email, 'password' => 'Clave12345'])->assertRedirect(route('dashboard'));
            $this->post(route('logout'));
            $this->flushSession();
        }
    }

    public function test_guessing_one_account_is_limited(): void
    {
        $user = User::factory()->role('admin')->create(['password' => bcrypt('Clave12345')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'mala' . $i])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'Clave12345'])->assertStatus(429);
    }
}
