<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('device_type', 20);
            $table->string('logo_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_brands');
    }
};
