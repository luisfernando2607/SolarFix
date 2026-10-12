<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'event', 'auditable_type', 'auditable_id', 'order_id',
        'old_values', 'new_values', 'description', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function record(string $event, ?Model $model = null, ?array $old = null, ?array $new = null, ?string $description = null, ?int $orderId = null, ?int $userId = null): self
    {
        return self::create([
            'user_id' => $userId ?? Auth::id(),
            'event' => $event,
            'auditable_type' => $model ? class_basename($model) : null,
            'auditable_id' => $model?->getKey(),
            'order_id' => $orderId,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'description' => $description,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    public static function events(): array
    {
        return [
            'created' => 'Creó',
            'updated' => 'Modificó',
            'deleted' => 'Eliminó',
            'login' => 'Inició sesión',
            'login_failed' => 'Intento de acceso fallido',
            'logout' => 'Cerró sesión',
        ];
    }

    public static function types(): array
    {
        return [
            'Order' => 'Orden',
            'OrderPayment' => 'Pago',
            'Invoice' => 'Factura',
            'Client' => 'Cliente',
            'User' => 'Usuario',
            'Warranty' => 'Garantía',
            'CashClosing' => 'Cierre de caja',
            'Setting' => 'Configuración',
        ];
    }

    public static function fieldLabel(string $field): string
    {
        return [
            'status' => 'Estado',
            'client_id' => 'Cliente',
            'user_id' => 'Técnico',
            'diagnosis_cost' => 'Costo diagnóstico',
            'labor_cost' => 'Mano de obra',
            'parts_cost' => 'Repuestos',
            'surcharge_percent' => 'Recargo %',
            'surcharge_amount' => 'Recargo',
            'total_amount' => 'Total',
            'amount_paid' => 'Pagado',
            'balance_due' => 'Saldo',
            'amount' => 'Monto',
            'method' => 'Método',
            'reference' => 'Referencia',
            'subtotal' => 'Subtotal',
            'iva_percent' => 'IVA %',
            'iva_amount' => 'IVA',
            'total' => 'Total',
            'role' => 'Rol',
            'is_active' => 'Activo',
            'password' => 'Contraseña',
            'unlock_value' => 'PIN/Patrón',
            'warranty_days' => 'Días de garantía',
            'delivery_date' => 'Fecha de entrega',
            'deleted_at' => 'Eliminado',
            'counted_cash' => 'Efectivo contado',
            'difference' => 'Diferencia',
            'value' => 'Valor',
            'end_date' => 'Vence',
            'notes' => 'Notas',
            'name' => 'Nombre',
            'phone' => 'Teléfono',
            'email' => 'Email',
        ][$field] ?? $field;
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
