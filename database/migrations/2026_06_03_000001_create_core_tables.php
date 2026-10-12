<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas principales de SolarFix. Reflejan el esquema que antes se creaba a mano
 * desde docs/solarfix_schema.sql; cada tabla se omite si ya existe, para que las
 * bases instaladas con el script SQL puedan ejecutar esta migración sin cambios.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('address', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $this->timestamps($table);
        });

        $this->create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 180)->unique();
            $table->string('password', 255);
            $table->string('phone', 30)->nullable();
            $table->enum('role', ['super_admin', 'admin', 'technician', 'receptionist'])->default('technician');
            $table->enum('commission_type', ['fixed', 'percent'])->nullable();
            $table->decimal('commission_value', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $this->timestamps($table);
            $table->softDeletes();
        });

        $this->create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->index();
            $table->string('phone', 30)->index();
            $table->string('email', 180)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('id_document', 20)->nullable();
            $table->enum('client_type', ['individual', 'business', 'frequent'])->default('individual');
            $table->text('notes')->nullable();
            $this->timestamps($table);
            $table->softDeletes();
        });

        $this->create('device_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->json('device_types')->nullable();
            $table->string('device_type', 20)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
        });

        $this->create('device_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('device_brands')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('device_type', 20)->default('otro');
            $table->boolean('is_active')->default(true);
        });

        $this->create('accessories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
        });

        $this->create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_number', 20)->unique();

            $table->enum('device_type', ['celular', 'tablet', 'pc', 'aire_split', 'aire_central', 'televisor', 'lavador', 'otro'])->default('otro');
            $table->foreignId('brand_id')->nullable()->constrained('device_brands')->nullOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('device_models')->nullOnDelete();
            $table->string('brand_text', 100)->nullable();
            $table->string('model_text', 100)->nullable();
            $table->string('serial_imei', 100)->nullable();

            $table->text('physical_condition')->nullable();
            $table->text('declared_fault');
            $table->text('diagnosis')->nullable();
            $table->text('work_done')->nullable();

            $table->enum('unlock_type', ['pin', 'pattern', 'none', 'unknown'])->default('unknown');
            $table->text('unlock_value')->nullable();

            $table->enum('status', ['received', 'diagnosing', 'waiting_approval', 'repairing', 'ready', 'delivered', 'closed_no_repair', 'warranty'])
                ->default('received')->index();

            $table->date('entry_date')->index();
            $table->date('estimated_delivery')->nullable();
            $table->date('delivery_date')->nullable();

            $table->decimal('diagnosis_cost', 10, 2)->default(0);
            $table->decimal('labor_cost', 10, 2)->default(0);
            $table->decimal('parts_cost', 10, 2)->default(0);
            $table->decimal('surcharge_percent', 5, 2)->default(0);
            $table->decimal('surcharge_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('balance_due', 10, 2)->default(0);

            $table->unsignedSmallInteger('warranty_days')->default(0);
            $table->foreignId('parent_order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->text('notes')->nullable();
            $this->timestamps($table);
            $table->softDeletes();
        });

        $this->create('order_accessories', function (Blueprint $table) {
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accessory_id')->constrained('accessories')->restrictOnDelete();
            $table->primary(['order_id', 'accessory_id']);
        });

        $this->create('order_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('stage', ['reception', 'process', 'delivery'])->default('reception');
            $table->string('file_path', 255);
            $table->string('caption', 200)->nullable();
            $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        $this->create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['cash', 'transfer', 'card', 'other'])->default('cash');
            $table->string('reference', 120)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
        });

        $this->create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamp('changed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        // Sin efecto a propósito: en bases instaladas con el script SQL estas tablas
        // no las creó esta migración, y un rollback borraría datos reales.
        // Para empezar de cero usa `php artisan migrate:fresh`.
    }

    private function create(string $name, Closure $callback): void
    {
        if (! Schema::hasTable($name)) {
            Schema::create($name, $callback);
        }
    }

    private function timestamps(Blueprint $table): void
    {
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
    }
};
