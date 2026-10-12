<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ya existe en bases instaladas con docs/solarfix_schema.sql.
        if (! Schema::hasTable('commissions')) {
            Schema::create('commissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->index()->constrained()->restrictOnDelete();
                $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
                $table->decimal('order_total', 10, 2);
                $table->enum('commission_type', ['fixed', 'percent']);
                $table->decimal('commission_value', 8, 2);
                $table->decimal('commission_amount', 10, 2);
                $table->boolean('is_paid')->default(false);
                $table->timestamp('paid_at')->nullable();
                $table->string('period', 7)->nullable()->index();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! DB::table('settings')->whereNull('branch_id')->where('key', 'commission_base')->exists()) {
            DB::table('settings')->insert(['key' => 'commission_base', 'value' => 'service', 'group' => 'commissions']);
        }
    }

    public function down(): void
    {
        // Sin efecto: la tabla puede venir del script SQL original y contener datos reales.
    }
};
