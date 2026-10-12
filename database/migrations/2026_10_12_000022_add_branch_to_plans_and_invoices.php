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

        foreach (['maintenance_plans', 'invoices'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('branch_id')->nullable()->after('id')->index()->constrained()->restrictOnDelete();
            });
        }

        // Facturas: la sucursal de su orden; las demás y los planes, la principal.
        DB::table('invoices')->whereNotNull('order_id')->orderBy('id')->select(['id', 'order_id'])->each(function ($invoice) {
            $branch = DB::table('orders')->where('id', $invoice->order_id)->value('branch_id');
            DB::table('invoices')->where('id', $invoice->id)->update(['branch_id' => $branch]);
        });
        DB::table('invoices')->whereNull('branch_id')->update(['branch_id' => $main]);
        DB::table('maintenance_plans')->whereNull('branch_id')->update(['branch_id' => $main]);
        DB::table('appointments')->whereNull('branch_id')->update(['branch_id' => $main]);
    }

    public function down(): void
    {
        foreach (['maintenance_plans', 'invoices'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropConstrainedForeignId('branch_id'));
        }
    }
};
