<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('device_brands', 'device_type')) {
            Schema::table('device_brands', function (Blueprint $table) {
                $table->string('device_type', 20)->nullable()->change();
            });
        }

        if (! Schema::hasColumn('device_models', 'device_type')) {
            Schema::table('device_models', function (Blueprint $table) {
                $table->string('device_type', 20)->default('otro')->after('name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('device_models', function (Blueprint $table) {
            $table->dropColumn('device_type');
        });
    }
};
