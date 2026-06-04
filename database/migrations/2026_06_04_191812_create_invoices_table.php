<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 20)->unique();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();

            $table->string('client_name', 150);
            $table->string('client_document', 20)->nullable();
            $table->string('client_phone', 30)->nullable();
            $table->string('client_address', 255)->nullable();
            $table->string('client_email', 180)->nullable();

            $table->string('device_type', 40)->nullable();
            $table->string('device_brand', 80)->nullable();
            $table->string('device_model', 120)->nullable();
            $table->string('device_serial', 100)->nullable();

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('iva_percent', 5, 2)->default(0);
            $table->decimal('iva_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->string('status', 20)->default('emitida');
            $table->date('issued_at');
            $table->date('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('issued_at');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
