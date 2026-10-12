<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = DB::table('settings')->whereNull('branch_id');

        // El script SQL original guardaba order_prefix = "SF", pero la app numeraba "ORD-...":
        // si ya hay órdenes así, se conserva "ORD" para no cambiar la numeración de golpe.
        $current = (clone $settings)->where('key', 'order_prefix')->value('value');
        $usesOrd = DB::table('orders')->where('order_number', 'like', 'ORD-%')->exists();
        if ($current === null) {
            DB::table('settings')->insert(['key' => 'order_prefix', 'value' => 'ORD', 'group' => 'orders']);
        } elseif ($usesOrd && $current !== 'ORD') {
            (clone $settings)->where('key', 'order_prefix')->update(['value' => 'ORD']);
        }

        foreach ([
            ['invoice_prefix', 'FAC', 'billing'],
            ['currency_symbol', '$', 'general'],
            ['diagnosis_cost_default', '0', 'orders'],
            ['maintenance_due_days', '15', 'alerts'],
        ] as [$key, $value, $group]) {
            if (! (clone $settings)->where('key', $key)->exists()) {
                DB::table('settings')->insert(['key' => $key, 'value' => $value, 'group' => $group]);
            }
        }
    }

    public function down(): void
    {
        // Sin efecto: son valores de configuración.
    }
};
