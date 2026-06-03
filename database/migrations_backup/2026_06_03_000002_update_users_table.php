<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete()->after('id');
            $table->string('phone', 30)->nullable()->after('password');
            $table->string('role', 20)->default('technician')->after('phone');
            $table->string('commission_type', 10)->nullable()->after('role');
            $table->decimal('commission_value', 8, 2)->nullable()->after('commission_type');
            $table->boolean('is_active')->default(true)->after('commission_value');
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn([
                'branch_id', 'phone', 'role', 'commission_type',
                'commission_value', 'is_active', 'deleted_at',
            ]);
        });
    }
};
