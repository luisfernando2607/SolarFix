<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // IVA que se suma al facturar cuando los precios no lo incluyen; así el cliente lo paga
            // en la orden y la factura cuadra con los cobros.
            $table->decimal('tax_amount', 10, 2)->default(0)->after('surcharge_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tax_amount');
        });
    }
};
