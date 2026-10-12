<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Models\Order;

/**
 * Registra en audit_logs quién crea, modifica o elimina el modelo y qué valores cambiaron.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->writeAudit('created', null, $model->auditValues($model->getAttributes())));

        static::updated(function ($model) {
            $changes = $model->auditValues($model->getChanges());
            if (! $changes) {
                return;
            }

            $old = [];
            foreach (array_keys($changes) as $key) {
                $old[$key] = $model->getRawOriginal($key);
            }

            $model->writeAudit('updated', $model->auditValues($old), $changes);
        });

        static::deleted(fn ($model) => $model->writeAudit('deleted', $model->auditValues($model->getAttributes()), null));
    }

    private function auditValues(array $values): array
    {
        $values = array_diff_key($values, array_flip(['created_at', 'updated_at', 'remember_token']));

        foreach (['password', 'unlock_value'] as $masked) {
            if (array_key_exists($masked, $values)) {
                $values[$masked] = $values[$masked] === null ? null : '[oculto]';
            }
        }

        return $values;
    }

    private function writeAudit(string $event, ?array $old, ?array $new): void
    {
        AuditLog::record(
            $event,
            $this,
            $old,
            $new,
            description: method_exists($this, 'auditDescription') ? $this->auditDescription() : null,
            orderId: $this instanceof Order ? $this->id : ($this->order_id ?? null),
        );
    }
}
