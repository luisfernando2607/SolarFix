<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pedido a un proveedor: borrador → enviado → recibido (suma el stock) o anulado.
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('number', 30)->unique();
            $table->string('status', 20)->default('draft')->index();
            $table->string('notes', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('spare_part_id')->index()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->unsignedInteger('received_quantity')->nullable();
            $table->unique(['purchase_order_id', 'spare_part_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
