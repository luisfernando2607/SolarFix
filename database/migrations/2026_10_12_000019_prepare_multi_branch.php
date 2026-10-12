<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas con branch_id que deben apuntar a una sucursal existente.
     */
    private const BRANCH_TABLES = ['users', 'orders', 'appointments', 'spare_parts', 'settings'];

    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('code', 6)->nullable()->unique()->after('name');
        });

        // El número con código de sucursal (ORD-GYE-20261012-0001) no cabe en 20 caracteres.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number', 30)->change();
        });

        // Sucursales duplicadas por nombre (el script SQL y el seeder creaban "Matriz" dos veces):
        // se conserva la más antigua y se reasignan sus referencias.
        foreach (DB::table('branches')->orderBy('id')->get()->groupBy('name') as $branches) {
            $keep = $branches->first()->id;
            foreach ($branches->slice(1) as $duplicate) {
                foreach (self::BRANCH_TABLES as $table) {
                    if (Schema::hasColumn($table, 'branch_id')) {
                        DB::table($table)->where('branch_id', $duplicate->id)->update(['branch_id' => $keep]);
                    }
                }
                DB::table('branches')->where('id', $duplicate->id)->delete();
            }
        }

        if (! DB::table('branches')->exists()) {
            DB::table('branches')->insert(['name' => 'Matriz', 'is_active' => true]);
        }

        // Códigos iniciales a partir del nombre (MATRIZ → MAT), sin repetir.
        $used = [];
        foreach (DB::table('branches')->orderBy('id')->get() as $branch) {
            $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $branch->name)) ?: 'SUC', 0, 3));
            $code = $base;
            for ($i = 2; in_array($code, $used, true); $i++) {
                $code = substr($base, 0, 2) . $i;
            }
            $used[] = $code;
            DB::table('branches')->where('id', $branch->id)->update(['code' => $code]);
        }

        // Todo queda en una sucursal: las órdenes y usuarios sin sucursal van a la principal.
        $main = DB::table('branches')->orderBy('id')->value('id');
        DB::table('orders')->whereNull('branch_id')->update(['branch_id' => $main]);
        DB::table('users')->whereNull('branch_id')->update(['branch_id' => $main]);
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
