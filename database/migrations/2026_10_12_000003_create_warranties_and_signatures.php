<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ambas tablas ya existen en bases instaladas con docs/solarfix_schema.sql.
        if (! Schema::hasTable('warranties')) {
            Schema::create('warranties', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
                $table->enum('warranty_type', ['labor', 'parts', 'full'])->default('full');
                $table->date('start_date');
                $table->date('end_date')->index();
                $table->enum('status', ['active', 'expiring', 'expired', 'claimed'])->default('active')->index();
                $table->text('conditions')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }

        if (! Schema::hasTable('order_signatures')) {
            Schema::create('order_signatures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->enum('stage', ['reception', 'delivery']);
                $table->string('signature_path', 255);
                $table->timestamp('signed_at')->useCurrent();
                $table->string('ip_address', 45)->nullable();
                $table->unique(['order_id', 'stage']);
            });
        }

        if (! DB::table('settings')->whereNull('branch_id')->where('key', 'warranty_alert_days')->exists()) {
            DB::table('settings')->insert(['key' => 'warranty_alert_days', 'value' => '15', 'group' => 'warranty']);
        }
    }

    public function down(): void
    {
        // Sin efecto: las tablas pueden venir del script SQL original y contener datos reales.
    }
};
