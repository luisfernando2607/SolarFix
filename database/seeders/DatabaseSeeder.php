<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::firstOrCreate(
            ['name' => 'Matriz'],
            [
                'address' => 'Av. Principal 123',
                'phone' => '0999999999',
                'email' => 'info@solarfix.ec',
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@solarfix.ec'],
            [
                'branch_id' => $branch->id,
                'name' => 'Administrador',
                'password' => bcrypt('password'),
                'phone' => '0999999999',
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        // Only seed test data if no orders exist yet
        if (Order::count() === 0) {
            $this->call(TestDataSeeder::class);
        }
    }
}
