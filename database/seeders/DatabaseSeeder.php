<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Order;
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
                // En producción la contraseña inicial se cambia en el primer ingreso.
                'must_change_password' => app()->isProduction(),
                'phone' => '0999999999',
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        $this->call(CatalogSeeder::class);
        $this->call(ChecklistSeeder::class);

        // Datos de demostración solo fuera de producción y en una base sin órdenes.
        if (! app()->isProduction() && Order::count() === 0) {
            $this->call(TestDataSeeder::class);
        }
    }
}
