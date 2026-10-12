<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->whereNotNull('unlock_value')->orderBy('id')->each(function ($order) {
            try {
                Crypt::decryptString($order->unlock_value);
                return; // ya cifrado
            } catch (DecryptException) {
                DB::table('orders')->where('id', $order->id)
                    ->update(['unlock_value' => Crypt::encryptString($order->unlock_value)]);
            }
        });
    }

    public function down(): void
    {
        DB::table('orders')->whereNotNull('unlock_value')->orderBy('id')->each(function ($order) {
            try {
                DB::table('orders')->where('id', $order->id)
                    ->update(['unlock_value' => Crypt::decryptString($order->unlock_value)]);
            } catch (DecryptException) {
                // ya en texto plano
            }
        });
    }
};
