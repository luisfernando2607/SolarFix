<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_logs', function (Blueprint $table) {
            // link = abrió WhatsApp con el texto (envío manual); api = enviado por la Cloud API de Meta.
            $table->string('channel', 10)->default('link')->after('trigger_event');
            // out = mensaje del negocio; in = respuesta del cliente (recibida por el webhook).
            $table->string('direction', 3)->default('out')->after('channel');
            // sent → delivered → read, o failed (solo con la API).
            $table->string('status', 20)->nullable()->after('direction');
            $table->string('wamid', 150)->nullable()->unique()->after('status');
            $table->string('error', 500)->nullable()->after('wamid');
            $table->timestamp('status_at')->nullable()->after('error');
            // Un mensaje entrante puede venir de un número que no es cliente.
            $table->foreignId('client_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_logs', function (Blueprint $table) {
            $table->dropColumn(['channel', 'direction', 'status', 'wamid', 'error', 'status_at']);
        });
    }
};
