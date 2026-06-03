<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 30);
            $table->string('email', 180)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('id_document', 20)->nullable();
            $table->string('client_type', 20)->default('individual');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
