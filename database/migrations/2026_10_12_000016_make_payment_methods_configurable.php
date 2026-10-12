<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // De ENUM fijo a texto: los métodos de pago se configuran en Configuración.
        Schema::table('order_payments', function (Blueprint $table) {
            $table->string('method', 30)->default('cash')->change();
        });

        if (! DB::table('settings')->whereNull('branch_id')->where('key', 'payment_methods')->exists()) {
            DB::table('settings')->insert([
                'key' => 'payment_methods',
                'value' => json_encode(['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'card' => 'Tarjeta', 'other' => 'Otro'], JSON_UNESCAPED_UNICODE),
                'group' => 'billing',
            ]);
        }
    }

    public function down(): void
    {
        // Sin efecto: volver al ENUM fallaría con pagos de métodos nuevos.
    }
};
