<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Enlace privado para que el cliente consulte el estado de su orden.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tracking_token', 40)->nullable()->unique()->after('order_number');
        });

        DB::table('orders')->whereNull('tracking_token')->orderBy('id')->select('id')->each(function ($order) {
            DB::table('orders')->where('id', $order->id)->update(['tracking_token' => Str::random(32)]);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['tracking_token']);
            $table->dropColumn('tracking_token');
        });
    }
};
