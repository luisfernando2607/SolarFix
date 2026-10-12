<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para las consultas más frecuentes (caja, panel, reportes, indicadores),
 * que crecen con cada orden y cada pago.
 */
return new class extends Migration
{
    private array $indexes = [
        'order_payments' => [['paid_at']],
        'order_status_history' => [['to_status', 'changed_at'], ['order_id', 'to_status']],
        'orders' => [['branch_id', 'status'], ['branch_id', 'created_at'], ['status', 'balance_due'], ['created_at']],
        'clients' => [['id_document']],
        'notifications' => [['notifiable_type', 'notifiable_id', 'read_at']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($indexes) {
                foreach ($indexes as $columns) {
                    $blueprint->index($columns);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($indexes) {
                foreach ($indexes as $columns) {
                    $blueprint->dropIndex($columns);
                }
            });
        }
    }
};
