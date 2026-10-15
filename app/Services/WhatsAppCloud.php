<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\WhatsappLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

/**
 * Envío por la WhatsApp Cloud API de Meta. Sin configurar (o en modo "enlace"), la app sigue
 * abriendo WhatsApp con el texto listo para que una persona lo envíe.
 */
class WhatsAppCloud
{
    public static function configured(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    /**
     * ¿Se envía por la API? (credenciales en el .env y modo "API" elegido en Configuración).
     */
    public static function enabled(): bool
    {
        return self::configured() && Setting::get('whatsapp_mode', 'link') === 'api';
    }

    /**
     * ¿Se envían solos los avisos al cambiar el estado de la orden y los recordatorios?
     */
    public static function automatic(): bool
    {
        return self::enabled() && Setting::get('whatsapp_auto', '0') === '1';
    }

    /**
     * Envía una plantilla y deja el registro (enviado o falló). Nunca lanza excepciones.
     */
    public static function sendTemplate(string $type, string $phone, array $params, array $log = []): WhatsappLog
    {
        $entry = [
            'trigger_event' => $type,
            'channel' => 'api',
            'direction' => 'out',
            'phone_to' => $phone,
            'message_body' => WhatsAppTemplates::preview($type, $params),
            'sent_by' => Auth::id(),
            'sent_at' => now(),
        ] + $log;

        try {
            $response = Http::withToken(config('services.whatsapp.token'))
                ->acceptJson()
                ->timeout(15)
                ->post(self::endpoint(), [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'template',
                    'template' => [
                        'name' => WhatsAppTemplates::name($type),
                        'language' => ['code' => WhatsAppTemplates::language()],
                        'components' => [[
                            'type' => 'body',
                            'parameters' => array_map(fn ($value) => ['type' => 'text', 'text' => $value], $params),
                        ]],
                    ],
                ]);

            $id = $response->successful() ? $response->json('messages.0.id') : null;
            $error = $id ? null : ($response->json('error.error_data.details') ?? $response->json('error.message') ?? ('HTTP ' . $response->status()));
        } catch (\Throwable $e) {
            report($e);
            [$id, $error] = [null, $e->getMessage()];
        }

        // El registro se guarda aparte: si el mensaje salió, queda "enviado" aunque falle algo después.
        return WhatsappLog::create($entry + ($id
            ? ['status' => 'sent', 'wamid' => $id, 'status_at' => now()]
            : ['status' => 'failed', 'error' => mb_substr((string) $error, 0, 500), 'status_at' => now()]));
    }

    /**
     * Prueba de conexión con la plantilla "hello_world" que Meta trae en toda cuenta nueva.
     */
    public static function sendTest(string $phone): array
    {
        try {
            $response = Http::withToken(config('services.whatsapp.token'))->acceptJson()->timeout(15)->post(self::endpoint(), [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'template' => ['name' => 'hello_world', 'language' => ['code' => 'en_US']],
            ]);
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }

        return $response->successful()
            ? [true, null]
            : [false, $response->json('error.message') ?? ('HTTP ' . $response->status())];
    }

    private static function endpoint(): string
    {
        return 'https://graph.facebook.com/' . config('services.whatsapp.api_version', 'v21.0') . '/'
            . config('services.whatsapp.phone_number_id') . '/messages';
    }
}
