<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_parts', function (Blueprint $table) {
            // Costo del repuesto al usarlo (costo promedio de ese momento): base de la utilidad.
            $table->decimal('unit_cost', 10, 2)->default(0)->after('unit_price');
        });

        // Datos existentes: el costo registrado en el movimiento de salida; si no hay, el costo actual.
        DB::table('order_parts')->orderBy('id')->each(function ($row) {
            $cost = DB::table('spare_part_movements')
                ->where('order_id', $row->order_id)->where('spare_part_id', $row->spare_part_id)
                ->where('type', 'out')->whereNotNull('unit_cost')
                ->orderByDesc('id')->value('unit_cost')
                ?? DB::table('spare_parts')->where('id', $row->spare_part_id)->value('cost_price')
                ?? 0;
            DB::table('order_parts')->where('id', $row->id)->update(['unit_cost' => $cost]);
        });
    }

    public function down(): void
    {
        Schema::table('order_parts', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
