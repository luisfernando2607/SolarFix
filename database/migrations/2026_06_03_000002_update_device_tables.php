<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE device_brands MODIFY device_type VARCHAR(20) NULL");
        DB::statement("ALTER TABLE device_models ADD COLUMN device_type VARCHAR(20) NOT NULL DEFAULT 'otro' AFTER name");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE device_models DROP COLUMN device_type");
    }
};
