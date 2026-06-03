<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

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

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
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
        return $this->belongsTo(Order::class, 'parent_order_id');
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
}
