<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Las tres tablas ya existen en bases instaladas con docs/solarfix_schema.sql.
        if (! Schema::hasTable('spare_parts')) {
            Schema::create('spare_parts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->nullable()->index()->constrained()->nullOnDelete();
                $table->string('code', 60)->nullable()->unique();
                $table->string('name', 200);
                $table->string('category', 100)->nullable()->index();
                $table->string('compatible_brand', 100)->nullable();
                $table->string('compatible_model', 100)->nullable();
                $table->decimal('cost_price', 10, 2)->default(0);
                $table->decimal('sale_price', 10, 2)->default(0);
                $table->integer('stock_quantity')->default(0);
                $table->integer('min_stock')->default(1);
                $table->string('location', 100)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }

        if (! Schema::hasTable('order_parts')) {
            Schema::create('order_parts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->index()->constrained()->cascadeOnDelete();
                $table->foreignId('spare_part_id')->index()->constrained()->restrictOnDelete();
                $table->integer('quantity')->default(1);
                $table->decimal('unit_price', 10, 2);
                $table->decimal('subtotal', 10, 2);
            });
        }

        if (! Schema::hasTable('spare_part_movements')) {
            Schema::create('spare_part_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('spare_part_id')->index()->constrained()->cascadeOnDelete();
                $table->enum('type', ['in', 'out', 'adjustment']);
                $table->integer('quantity');
                $table->string('reason', 200)->nullable();
                $table->foreignId('order_id')->nullable()->index()->constrained()->nullOnDelete();
                $table->decimal('unit_cost', 10, 2)->nullable();
                $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('moved_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        // Sin efecto: las tablas pueden venir del script SQL original y contener datos reales.
    }
};
