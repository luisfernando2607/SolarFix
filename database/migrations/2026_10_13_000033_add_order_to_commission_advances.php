<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_advances', function (Blueprint $table) {
            // Ajustes automáticos de una comisión ya pagada (la orden cambió después del pago).
            // amount > 0 se descuenta del próximo pago; amount < 0 se suma.
            $table->foreignId('order_id')->nullable()->after('user_id')->index()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commission_advances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
