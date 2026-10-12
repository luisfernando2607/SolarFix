<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Movimientos de efectivo que no vienen de cobros de órdenes.
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->date('movement_date')->index();
            $table->enum('type', ['opening', 'expense', 'withdrawal', 'income']);
            $table->decimal('amount', 10, 2);
            $table->string('description', 255);
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('cash_closings', function (Blueprint $table) {
            $table->decimal('opening_cash', 10, 2)->default(0)->after('closing_date');
            $table->decimal('cash_payments', 10, 2)->default(0)->after('opening_cash');
            $table->decimal('other_income', 10, 2)->default(0)->after('cash_payments');
            $table->decimal('expenses', 10, 2)->default(0)->after('other_income');
            $table->decimal('withdrawals', 10, 2)->default(0)->after('expenses');
        });
    }

    public function down(): void
    {
        Schema::table('cash_closings', function (Blueprint $table) {
            $table->dropColumn(['opening_cash', 'cash_payments', 'other_income', 'expenses', 'withdrawals']);
        });
        Schema::dropIfExists('cash_movements');
    }
};
