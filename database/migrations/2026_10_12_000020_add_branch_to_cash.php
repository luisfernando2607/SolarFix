<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $main = DB::table('branches')->orderBy('id')->value('id');

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->index()->constrained()->restrictOnDelete();
        });
        DB::table('cash_movements')->whereNull('branch_id')->update(['branch_id' => $main]);

        // Un cierre por día y por sucursal (antes, uno por día).
        Schema::table('cash_closings', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });
        DB::table('cash_closings')->whereNull('branch_id')->update(['branch_id' => $main]);
        Schema::table('cash_closings', function (Blueprint $table) {
            $table->dropUnique(['closing_date']);
            $table->unique(['closing_date', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cash_closings', function (Blueprint $table) {
            $table->dropUnique(['closing_date', 'branch_id']);
            $table->unique('closing_date');
            $table->dropConstrainedForeignId('branch_id');
        });
        Schema::table('cash_movements', fn (Blueprint $table) => $table->dropConstrainedForeignId('branch_id'));
    }
};
