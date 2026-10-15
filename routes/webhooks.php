<?php

use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// WhatsApp Cloud API: verificación (GET) y avisos de estados y respuestas de clientes (POST).
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->middleware('throttle:30,1');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->middleware('throttle:600,1')->name('webhooks.whatsapp');
