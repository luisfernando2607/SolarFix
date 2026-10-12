<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // settings y whatsapp_logs ya existen en bases instaladas con docs/solarfix_schema.sql.
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('key', 80);
                $table->text('value')->nullable();
                $table->string('group', 60)->default('general');
                $table->unique(['branch_id', 'key']);
            });
        }

        if (! Schema::hasTable('whatsapp_logs')) {
            Schema::create('whatsapp_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->nullable()->index()->constrained()->nullOnDelete();
                $table->foreignId('client_id')->index()->constrained()->restrictOnDelete();
                $table->string('trigger_event', 80);
                $table->string('phone_to', 30);
                $table->text('message_body');
                $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('sent_at')->useCurrent();
            });
        }

        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->date('closing_date')->unique();
            $table->decimal('expected_cash', 10, 2)->default(0);
            $table->decimal('expected_transfer', 10, 2)->default(0);
            $table->decimal('expected_card', 10, 2)->default(0);
            $table->decimal('expected_other', 10, 2)->default(0);
            $table->decimal('counted_cash', 10, 2);
            $table->decimal('difference', 10, 2);
            $table->text('notes')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $defaults = [
            ['company_name', 'SolarFix', 'general'],
            ['company_phone', '', 'general'],
            ['company_email', '', 'general'],
            ['company_address', '', 'general'],
            ['default_warranty_days', '30', 'orders'],
            ['iva_percent', '15', 'billing'],
            ['prices_include_iva', '1', 'billing'],
            ['stale_order_days', '5', 'alerts'],
            ['approval_alert_hours', '72', 'alerts'],
        ];

        foreach ($defaults as [$key, $value, $group]) {
            if (! DB::table('settings')->whereNull('branch_id')->where('key', $key)->exists()) {
                DB::table('settings')->insert(['key' => $key, 'value' => $value, 'group' => $group]);
            }
        }
    }

    public function down(): void
    {
        // settings y whatsapp_logs pueden venir del script SQL original; solo se elimina lo nuevo.
        Schema::dropIfExists('cash_closings');
    }
};
