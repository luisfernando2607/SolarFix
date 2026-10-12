<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->index()->constrained()->restrictOnDelete();
            $table->string('title', 150);
            $table->string('address', 255)->nullable();
            $table->unsignedTinyInteger('interval_months');
            $table->date('next_due_date')->index();
            $table->date('last_done_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('maintenance_plan_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', fn (Blueprint $table) => $table->dropConstrainedForeignId('maintenance_plan_id'));
        Schema::dropIfExists('maintenance_plans');
    }
};
