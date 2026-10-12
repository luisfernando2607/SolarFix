<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stock de cada repuesto por sucursal. spare_parts.stock_quantity queda como total.
        Schema::create('spare_part_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spare_part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->index()->constrained()->restrictOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();
            $table->unique(['spare_part_id', 'branch_id']);
        });

        Schema::table('spare_part_movements', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('spare_part_id')->index()->constrained()->restrictOnDelete();
        });

        // Hasta ahora había un solo local: todo el stock y los movimientos pasan a la sucursal principal.
        $main = DB::table('branches')->orderBy('id')->value('id');
        DB::table('spare_parts')->orderBy('id')->select(['id', 'stock_quantity'])->each(function ($part) use ($main) {
            DB::table('spare_part_stocks')->insert([
                'spare_part_id' => $part->id, 'branch_id' => $main, 'quantity' => $part->stock_quantity,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        DB::table('spare_part_movements')->whereNull('branch_id')->update(['branch_id' => $main]);
    }

    public function down(): void
    {
        Schema::table('spare_part_movements', fn (Blueprint $table) => $table->dropConstrainedForeignId('branch_id'));
        Schema::dropIfExists('spare_part_stocks');
    }
};
