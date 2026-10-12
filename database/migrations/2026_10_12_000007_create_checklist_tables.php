<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Las tablas ya existen en bases instaladas con docs/solarfix_schema.sql.
        if (! Schema::hasTable('checklists')) {
            Schema::create('checklists', function (Blueprint $table) {
                $table->id();
                $table->enum('device_type', ['celular', 'tablet', 'pc', 'aire_split', 'aire_central', 'televisor', 'lavador', 'otro']);
                $table->enum('stage', ['reception', 'diagnosis', 'delivery'])->default('reception');
                $table->string('name', 120);
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['device_type', 'stage']);
            });
        }

        if (! Schema::hasTable('checklist_items')) {
            Schema::create('checklist_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('checklist_id')->index()->constrained()->cascadeOnDelete();
                $table->string('label', 150);
                $table->smallInteger('sort_order')->default(0);
                $table->boolean('is_required')->default(true);
            });
        }

        // Los ítems quitados se desactivan en lugar de borrarse, para no perder resultados de órdenes anteriores.
        if (! Schema::hasColumn('checklist_items', 'is_active')) {
            Schema::table('checklist_items', function (Blueprint $table) {
                $table->boolean('is_active')->default(true);
            });
        }

        if (! Schema::hasTable('order_checklist_results')) {
            Schema::create('order_checklist_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('checklist_item_id')->constrained()->cascadeOnDelete();
                $table->enum('result', ['ok', 'damaged', 'not_applicable', 'pending'])->default('pending');
                $table->string('notes', 255)->nullable();
                $table->unique(['order_id', 'checklist_item_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('checklist_items', 'is_active')) {
            Schema::table('checklist_items', fn (Blueprint $table) => $table->dropColumn('is_active'));
        }
    }
};
