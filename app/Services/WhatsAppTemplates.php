<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\MaintenancePlan;
use App\Models\Order;
use App\Models\Setting;

/**
 * Plantillas para la WhatsApp Cloud API. WhatsApp exige que los mensajes que inicia el negocio
 * usen plantillas aprobadas por Meta con texto fijo y variables {{1}}, {{2}}…: estos textos se
 * registran una vez en Meta (docs/WHATSAPP.md) y aquí se completan las variables.
 */
class WhatsAppTemplates
{
    public const DEFINITIONS = [
        'budget' => [
            'name' => 'solarfix_presupuesto',
            'body' => 'Hola {{1}}, su equipo {{2}} (orden {{3}}) ya fue diagnosticado. Total del presupuesto: {{4}}. Puede ver el detalle y aprobarlo o rechazarlo aquí: {{5}}',
            'params' => ['cliente', 'equipo', 'orden', 'total', 'enlace de seguimiento'],
        ],
        'ready' => [
            'name' => 'solarfix_equipo_listo',
            'body' => 'Hola {{1}}, su equipo {{2}} (orden {{3}}) está listo para retirar. {{4}} Estado de su orden: {{5}}',
            'params' => ['cliente', 'equipo', 'orden', 'saldo', 'enlace de seguimiento'],
        ],
        'delivery' => [
            'name' => 'solarfix_entrega',
            'body' => 'Hola {{1}}, gracias por su confianza. Le entregamos su equipo {{2}} (orden {{3}}). {{4}} Califique nuestro servicio aquí: {{5}}',
            'params' => ['cliente', 'equipo', 'orden', 'garantía', 'enlace de seguimiento'],
        ],
        'pickup_reminder' => [
            'name' => 'solarfix_recordatorio_retiro',
            'body' => 'Hola {{1}}, le recordamos que su equipo {{2}} (orden {{3}}) está listo para retirar desde hace {{4}} días. Estado de su orden: {{5}}',
            'params' => ['cliente', 'equipo', 'orden', 'días', 'enlace de seguimiento'],
        ],
        'appointment_confirmation' => [
            'name' => 'solarfix_cita_confirmada',
            'body' => 'Hola {{1}}, su cita de {{2}} quedó agendada para el {{3}}. {{4}} Si necesita reprogramar, responda a este mensaje.',
            'params' => ['cliente', 'servicio', 'fecha y hora', 'dirección'],
        ],
        'appointment_reminder' => [
            'name' => 'solarfix_recordatorio_cita',
            'body' => 'Hola {{1}}, le recordamos su cita de {{2}} para mañana {{3}}. {{4}} Si necesita reprogramar, responda a este mensaje.',
            'params' => ['cliente', 'servicio', 'hora', 'dirección'],
        ],
        'maintenance_due' => [
            'name' => 'solarfix_mantenimiento',
            'body' => 'Hola {{1}}, según su plan de mantenimiento, {{2}} el mantenimiento de {{3}}. ¿Desea que agendemos una visita? Responda a este mensaje.',
            'params' => ['cliente', 'cuándo', 'equipo'],
        ],
    ];

    /**
     * Nombre de la plantilla en Meta (se puede cambiar en Configuración).
     */
    public static function name(string $type): string
    {
        return trim((string) Setting::get('wa_api_template_' . $type, '')) ?: self::DEFINITIONS[$type]['name'];
    }

    public static function language(): string
    {
        return Setting::get('wa_api_language', 'es') ?: 'es';
    }

    /**
     * Valores de las variables, en orden, para una orden.
     */
    public static function forOrder(string $type, Order $order): array
    {
        $order->loadMissing(['client', 'brand', 'deviceModel', 'warranty']);
        $equipo = trim(implode(' ', array_filter([
            Order::deviceTypes()[$order->device_type] ?? null,
            $order->brand?->name ?? $order->brand_text,
            $order->deviceModel?->name ?? $order->model_text,
        ]))) ?: 'su equipo';
        $link = $order->tracking_token ? $order->trackingUrl() : (Setting::get('company_phone') ?: '-');

        $fourth = match ($type) {
            'budget' => money($order->total_amount),
            'ready' => (float) $order->balance_due > 0 ? 'Saldo pendiente: ' . money($order->balance_due) . '.' : 'Su orden ya está pagada.',
            'delivery' => $order->warranty_days > 0
                ? "Garantía de {$order->warranty_days} días, hasta el " . ($order->delivery_date ?? now())->copy()->addDays($order->warranty_days)->format('d/m/Y') . '.'
                : 'Conserve su número de orden para cualquier consulta.',
            'pickup_reminder' => (string) WhatsAppMessage::daysReady($order),
        };

        return self::clean([$order->client?->name ?? 'cliente', $equipo, $order->order_number, $fourth, $link]);
    }

    public static function forAppointment(string $type, Appointment $appointment): array
    {
        $appointment->loadMissing('client');
        $when = $type === 'appointment_reminder'
            ? 'a las ' . $appointment->scheduled_at->format('H:i')
            : $appointment->scheduled_at->translatedFormat('l d \d\e F') . ' a las ' . $appointment->scheduled_at->format('H:i');

        return self::clean([
            $appointment->client?->name ?? 'cliente',
            mb_strtolower(Appointment::types()[$appointment->type] ?? 'visita'),
            $when,
            $appointment->address ? "Dirección: {$appointment->address}." : 'Le esperamos.',
        ]);
    }

    public static function forMaintenance(MaintenancePlan $plan): array
    {
        $plan->loadMissing('client');

        return self::clean([
            $plan->client?->name ?? 'cliente',
            $plan->next_due_date->lt(today()) ? 'ya le corresponde' : 'el ' . $plan->next_due_date->translatedFormat('d \d\e F') . ' le corresponde',
            $plan->title,
        ]);
    }

    /**
     * Meta rechaza variables vacías, con saltos de línea o con más de 4 espacios seguidos.
     */
    private static function clean(array $values): array
    {
        return array_map(function ($value) {
            $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

            return $value === '' ? '-' : mb_substr($value, 0, 1000);
        }, $values);
    }

    /**
     * Texto final (como lo verá el cliente), para el registro de la orden.
     */
    public static function preview(string $type, array $params): string
    {
        $body = self::DEFINITIONS[$type]['body'];
        foreach ($params as $i => $value) {
            $body = str_replace('{{' . ($i + 1) . '}}', $value, $body);
        }

        return $body;
    }
}
