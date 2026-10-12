<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Venta de mostrador: accesorios y repuestos vendidos sin orden de servicio.
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->index()->constrained()->restrictOnDelete();
            $table->string('sale_number', 30)->unique();
            $table->foreignId('client_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('customer_name', 150)->nullable();
            $table->decimal('total', 10, 2);
            $table->string('method', 30);
            $table->string('reference', 100)->nullable();
            $table->string('notes', 255)->nullable();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sold_at')->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('spare_part_id')->index()->constrained()->restrictOnDelete();
            $table->string('description', 255);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);
        });

        Schema::table('spare_part_movements', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('order_id')->index()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('spare_part_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sale_id');
        });
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
