<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\EmailLog;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Limpieza de registros que crecen sin límite: notificaciones ya leídas, registro de correos
 * y auditoría. Los plazos se configuran en Configuración (0 = conservar siempre).
 * Programada a diario en routes/console.php.
 */
class PruneCommand extends Command
{
    protected $signature = 'solarfix:prune';

    protected $description = 'Borra notificaciones leídas, registros de correo y auditoría más antiguos que lo configurado';

    public function handle(): int
    {
        $removed = [
            'notificaciones leídas' => $this->prune(
                DB::table('notifications')->whereNotNull('read_at'), 'read_at', (int) Setting::get('notification_retention_days', 90)),
            'registros de correo' => $this->prune(EmailLog::query(), 'sent_at', (int) Setting::get('email_log_retention_days', 365)),
            'registros de auditoría' => $this->prune(AuditLog::query(), 'created_at', (int) Setting::get('audit_retention_days', 730)),
        ];

        foreach ($removed as $label => $count) {
            $this->line($count === null ? "{$label}: se conservan siempre" : "{$label}: {$count} eliminados");
        }

        return self::SUCCESS;
    }

    /**
     * Borra por bloques (sin bloquear la tabla mucho tiempo). Devuelve null si el plazo es 0.
     */
    private function prune($query, string $column, int $days): ?int
    {
        if ($days <= 0) {
            return null;
        }

        $total = 0;
        do {
            $ids = (clone $query)->where($column, '<', now()->subDays($days))->limit(1000)->pluck('id');
            if ($ids->isNotEmpty()) {
                $total += (clone $query)->whereIn('id', $ids)->delete();
            }
        } while ($ids->count() === 1000);

        return $total;
    }
}
