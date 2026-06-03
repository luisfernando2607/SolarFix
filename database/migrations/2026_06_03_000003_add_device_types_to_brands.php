<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_brands', function (Blueprint $table) {
            $table->json('device_types')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('device_brands', function (Blueprint $table) {
            $table->dropColumn('device_types');
        });
    }
};
