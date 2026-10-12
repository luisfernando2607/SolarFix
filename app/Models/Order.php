<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class Order extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'client_id',
        'user_id',
        'created_by',
        'order_number',
        'device_type',
        'brand_id',
        'model_id',
        'brand_text',
        'model_text',
        'serial_imei',
        'physical_condition',
        'declared_fault',
        'diagnosis',
        'work_done',
        'unlock_type',
        'unlock_value',
        'status',
        'entry_date',
        'estimated_delivery',
        'delivery_date',
        'diagnosis_cost',
        'labor_cost',
        'parts_cost',
        'surcharge_percent',
        'surcharge_amount',
        'total_amount',
        'amount_paid',
        'balance_due',
        'warranty_days',
        'parent_order_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'estimated_delivery' => 'date',
            'delivery_date' => 'date',
            'diagnosis_cost' => 'decimal:2',
            'labor_cost' => 'decimal:2',
            'parts_cost' => 'decimal:2',
            'surcharge_percent' => 'decimal:2',
            'surcharge_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'warranty_days' => 'integer',
            'unlock_value' => 'encrypted',
            'deleted_at' => 'datetime',
        ];
    }

    public static function statuses(): array
    {
        return [
            'received' => 'Recibido',
            'diagnosing' => 'En Diagnóstico',
            'waiting_approval' => 'Esperando Aprobación',
            'repairing' => 'En Reparación',
            'ready' => 'Listo',
            'delivered' => 'Entregado',
            'closed_no_repair' => 'Cerrado sin Reparación',
            'warranty' => 'En Garantía',
        ];
    }

    public static function deviceTypes(): array
    {
        return DeviceBrand::deviceTypes();
    }

    public static function unlockTypes(): array
    {
        return [
            'none' => 'Sin bloqueo',
            'pin' => 'PIN',
            'pattern' => 'Patrón',
            'unknown' => 'Desconocido',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->tracking_token ??= Str::random(32);
        });
    }

    /**
     * Enlace público de seguimiento para el cliente.
     */
    public function trackingUrl(): string
    {
        return route('tracking.show', $this->tracking_token);
    }

    /**
     * Crea la orden reintentando si otro usuario tomó el mismo número al mismo tiempo.
     */
    public static function createWithUniqueNumber(array $attributes): self
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return self::create($attributes + ['order_number' => self::nextOrderNumber()]);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 5) {
                    throw $e;
                }
            }
        }
    }

    private static function nextOrderNumber(): string
    {
        $prefix = Setting::get('order_prefix', 'ORD') . '-' . now()->format('Ymd') . '-';
        // withTrashed: las órdenes anuladas conservan su número.
        $last = self::withTrashed()
            ->where('order_number', 'like', $prefix . '%')
            ->orderBy('order_number', 'desc')
            ->value('order_number');

        $next = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Marca la orden como entregada (con historial) y emite su garantía.
     */
    public function markDelivered(?int $userId, ?string $notes = null): void
    {
        if ($this->status !== 'delivered') {
            OrderStatusHistory::create([
                'order_id' => $this->id,
                'from_status' => $this->status,
                'to_status' => 'delivered',
                'changed_by' => $userId,
                'notes' => $notes,
            ]);
            $this->status = 'delivered';
        }

        $this->delivery_date ??= today();
        $this->save();

        $this->issueWarranty();
        Commission::syncFor($this);
    }

    /**
     * Devolución del equipo sin reparar: queda la fecha y la nota, sin garantía ni comisión.
     */
    public function markReturnedWithoutRepair(?int $userId): void
    {
        OrderStatusHistory::create([
            'order_id' => $this->id,
            'from_status' => $this->status,
            'to_status' => $this->status,
            'changed_by' => $userId,
            'notes' => 'Devolución sin reparación firmada por el cliente',
        ]);

        $this->delivery_date ??= today();
        $this->save();
    }

    public function isReturnWithoutRepair(): bool
    {
        return $this->status === 'closed_no_repair';
    }

    /**
     * Crea la garantía al entregar, o ajusta sus fechas si cambian los días o la fecha de entrega.
     */
    public function issueWarranty(): ?Warranty
    {
        $warranty = $this->warranty()->first();

        if ($this->status !== 'delivered' || $this->warranty_days <= 0) {
            return $warranty;
        }

        $start = $this->delivery_date ?? today();
        $dates = ['start_date' => $start, 'end_date' => $start->copy()->addDays($this->warranty_days)];

        if (! $warranty) {
            return $this->warranty()->create($dates + ['warranty_type' => 'full', 'status' => 'active']);
        }

        if ($warranty->status !== 'claimed') {
            $warranty->update($dates);
        }

        return $warranty;
    }

    /**
     * Recalcula repuestos (si vienen del inventario), recargo, total, pagado y saldo.
     */
    public function recalculateTotals(): void
    {
        if ($this->parts()->exists()) {
            $this->parts_cost = round((float) $this->parts()->sum('subtotal'), 2);
        }

        $subtotal = (float) $this->diagnosis_cost + (float) $this->labor_cost + (float) $this->parts_cost;
        $surcharge = round($subtotal * (float) $this->surcharge_percent / 100, 2);
        $paid = round((float) $this->payments()->sum('amount'), 2);

        $this->surcharge_amount = $surcharge;
        $this->total_amount = round($subtotal + $surcharge, 2);
        $this->amount_paid = $paid;
        $this->balance_due = round(max(0, $this->total_amount - $paid), 2);
        $this->save();
    }

    public function isWarrantyOrder(): bool
    {
        return $this->parent_order_id !== null;
    }

    /**
     * Tipo de garantía de la orden original que cubre este reclamo (full, labor o parts).
     */
    public function warrantyCoverage(): ?string
    {
        if (! $this->isWarrantyOrder()) {
            return null;
        }

        return Warranty::where('order_id', $this->parent_order_id)->value('warranty_type') ?? 'full';
    }

    /**
     * En un reclamo, los repuestos son gratis salvo que la garantía cubra solo mano de obra.
     */
    public function warrantyCoversParts(): bool
    {
        return in_array($this->warrantyCoverage(), ['full', 'parts'], true);
    }

    /**
     * Los técnicos ven las órdenes asignadas a ellos y las que aún no tienen técnico.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isTechnician()) {
            $query->where(fn ($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'));
        }

        return $query;
    }

    public function isVisibleTo(User $user): bool
    {
        return ! $user->isTechnician() || $this->user_id === null || (int) $this->user_id === $user->id;
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // withTrashed: las órdenes conservan el nombre del cliente o usuario aunque se elimine.
    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function brand()
    {
        return $this->belongsTo(DeviceBrand::class);
    }

    public function deviceModel()
    {
        return $this->belongsTo(DeviceModel::class, 'model_id');
    }

    public function parentOrder()
    {
        return $this->belongsTo(Order::class, 'parent_order_id')->withTrashed();
    }

    public function childOrders()
    {
        return $this->hasMany(Order::class, 'parent_order_id');
    }

    public function payments()
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function photos()
    {
        return $this->hasMany(OrderPhoto::class);
    }

    public function accessories()
    {
        return $this->belongsToMany(Accessory::class, 'order_accessories');
    }

    public function parts()
    {
        return $this->hasMany(OrderPart::class);
    }

    public function warranty()
    {
        return $this->hasOne(Warranty::class);
    }

    public function signatures()
    {
        return $this->hasMany(OrderSignature::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class)->orderBy('scheduled_at');
    }

    public function commission()
    {
        return $this->hasOne(Commission::class);
    }

    // Bitácora técnica ("notes" ya es una columna de la orden).
    public function workNotes()
    {
        return $this->hasMany(OrderNote::class)->latest('created_at')->latest('id');
    }

    public function checklistResults()
    {
        return $this->hasMany(OrderChecklistResult::class);
    }

    /**
     * Avance del checklist de una etapa para el tipo de equipo de la orden (null si no hay checklist).
     */
    public function checklistProgress(string $stage): ?array
    {
        $checklist = Checklist::for($this->device_type, $stage);
        if (! $checklist) {
            return null;
        }

        $items = $checklist->activeItems()->get();
        $results = $this->checklistResults()->whereIn('checklist_item_id', $items->pluck('id'))->get()->keyBy('checklist_item_id');
        $answered = $results->where('result', '!=', 'pending');

        return [
            'checklist' => $checklist,
            'total' => $items->count(),
            'answered' => $answered->count(),
            'damaged' => $answered->where('result', 'damaged')->count(),
            'complete' => $items->where('is_required', true)->every(fn ($item) => isset($results[$item->id]) && $results[$item->id]->result !== 'pending'),
        ];
    }

    public function whatsappLogs()
    {
        return $this->hasMany(WhatsappLog::class)->latest('sent_at');
    }

    public function invoices()
    {
        return $this->hasMany(\App\Models\Invoice::class);
    }

    public function auditDescription(): string
    {
        return 'Orden ' . $this->order_number;
    }
}
