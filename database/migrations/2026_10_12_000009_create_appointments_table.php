<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ya existe en bases instaladas con docs/solarfix_schema.sql.
        if (! Schema::hasTable('appointments')) {
            Schema::create('appointments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('client_id')->index()->constrained()->restrictOnDelete();
                $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->enum('type', ['reception', 'home_visit', 'installation', 'maintenance', 'followup'])->default('reception');
                $table->string('title', 150);
                $table->string('address', 255)->nullable();
                $table->dateTime('scheduled_at')->index();
                $table->smallInteger('duration_min')->default(60);
                $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
                $table->text('notes')->nullable();
                $table->boolean('reminder_sent')->default(false);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }
    }

    public function down(): void
    {
        // Sin efecto: la tabla puede venir del script SQL original y contener datos reales.
    }
};
