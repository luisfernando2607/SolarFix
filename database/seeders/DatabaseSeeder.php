<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::create([
            'name' => 'Matriz',
            'address' => 'Av. Principal 123',
            'phone' => '0999999999',
            'email' => 'info@solarfix.ec',
        ]);

        User::create([
            'branch_id' => $branch->id,
            'name' => 'Administrador',
            'email' => 'admin@solarfix.ec',
            'password' => bcrypt('password'),
            'phone' => '0999999999',
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }
}
