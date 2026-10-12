<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\MaintenancePlan;
use App\Models\Order;
use App\Models\Setting;

/**
 * Arma los mensajes de WhatsApp de una orden y el enlace wa.me para enviarlos.
 */
class WhatsAppMessage
{
    public const TYPES = [
        'budget' => 'Presupuesto',
        'ready' => 'Equipo listo',
        'delivery' => 'Entrega y garantía',
    ];

    /**
     * Convierte un teléfono ecuatoriano al formato internacional sin "+" (ej. 0991234567 → 593991234567).
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if (str_starts_with($digits, '593') && strlen($digits) >= 11) {
            return $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '593' . substr($digits, 1);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 9) {
            return '593' . $digits;
        }

        return strlen($digits) >= 10 ? $digits : null;
    }

    public static function url(string $phone, string $message): string
    {
        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }

    public static function build(string $type, Order $order): string
    {
        $order->loadMissing(['client', 'brand', 'deviceModel', 'parts.sparePart']);

        $company = Setting::get('company_name', 'SolarFix');
        $client = $order->client?->name ?? 'cliente';
        $device = trim(implode(' ', array_filter([
            Order::deviceTypes()[$order->device_type] ?? null,
            $order->brand?->name ?? $order->brand_text,
            $order->deviceModel?->name ?? $order->model_text,
        ])));

        $lines = match ($type) {
            'budget' => self::budget($order, $client, $company, $device),
            'ready' => self::ready($order, $client, $company, $device),
            'delivery' => self::delivery($order, $client, $company, $device),
        };

        return implode("\n", $lines);
    }

    private static function budget(Order $order, string $client, string $company, string $device): array
    {
        $lines = [
            "Hola {$client}, le saludamos de *{$company}*.",
            "Su equipo {$device} (orden *{$order->order_number}*) ya fue diagnosticado.",
            '',
        ];

        if ($order->diagnosis) {
            $lines[] = '*Diagnóstico:* ' . $order->diagnosis;
            $lines[] = '';
        }

        $lines[] = '*Presupuesto:*';
        $concepts = [['Diagnóstico', $order->diagnosis_cost], ['Mano de obra', $order->labor_cost]];
        if ($order->parts->isNotEmpty()) {
            foreach ($order->parts as $part) {
                $concepts[] = [($part->sparePart?->name ?? 'Repuesto') . ' x' . $part->quantity, $part->subtotal];
            }
        } else {
            $concepts[] = ['Repuestos', $order->parts_cost];
        }
        $concepts[] = ['Recargo', $order->surcharge_amount];

        foreach ($concepts as [$label, $amount]) {
            if ((float) $amount > 0) {
                $lines[] = "- {$label}: " . self::money($amount);
            }
        }
        $lines[] = '*Total: ' . self::money($order->total_amount) . '*';

        if ((float) $order->amount_paid > 0) {
            $lines[] = 'Abonado: ' . self::money($order->amount_paid);
            $lines[] = 'Saldo: ' . self::money($order->balance_due);
        }

        $lines[] = '';
        $lines[] = 'Por favor confírmenos si aprueba la reparación. ¡Gracias!';

        return $lines;
    }

    private static function ready(Order $order, string $client, string $company, string $device): array
    {
        $lines = [
            "Hola {$client}, le saludamos de *{$company}*.",
            "Su equipo {$device} (orden *{$order->order_number}*) está *listo para retirar*.",
        ];

        if ((float) $order->balance_due > 0) {
            $lines[] = 'Saldo pendiente: ' . self::money($order->balance_due);
        }

        if ($address = Setting::get('company_address')) {
            $lines[] = '';
            $lines[] = "Le esperamos en: {$address}";
        }

        return $lines;
    }

    private static function delivery(Order $order, string $client, string $company, string $device): array
    {
        $lines = [
            "Hola {$client}, gracias por confiar en *{$company}*.",
            "Le entregamos su equipo {$device} (orden *{$order->order_number}*).",
        ];

        if ($order->work_done) {
            $lines[] = '';
            $lines[] = '*Trabajo realizado:* ' . $order->work_done;
        }

        if ($order->warranty_days > 0) {
            $until = ($order->delivery_date ?? now())->copy()->addDays($order->warranty_days);
            $lines[] = '';
            $lines[] = "*Garantía:* {$order->warranty_days} días, hasta el " . $until->format('d/m/Y') . '.';
        }

        $lines[] = '';
        $lines[] = "Conserve su número de orden ({$order->order_number}) para cualquier consulta.";

        return $lines;
    }

    public const APPOINTMENT_TYPES = [
        'appointment_confirmation' => 'Confirmación de cita',
        'appointment_reminder' => 'Recordatorio de cita',
    ];

    public static function buildAppointment(string $type, Appointment $appointment): string
    {
        $appointment->loadMissing(['client', 'user']);

        $company = Setting::get('company_name', 'SolarFix');
        $client = $appointment->client?->name ?? 'cliente';
        $service = strtolower(Appointment::types()[$appointment->type] ?? 'visita');
        $when = $appointment->scheduled_at->translatedFormat('l d \\d\\e F') . ' a las ' . $appointment->scheduled_at->format('H:i');

        $lines = $type === 'appointment_reminder'
            ? ["Hola {$client}, le recordamos de *{$company}* su cita de {$service} para *mañana {$when}*."]
            : ["Hola {$client}, le saludamos de *{$company}*.", "Su cita de {$service} quedó agendada para el *{$when}*."];

        if ($appointment->address) {
            $lines[] = "Dirección: {$appointment->address}";
        }
        if ($appointment->user) {
            $lines[] = "Técnico: {$appointment->user->name}";
        }

        $lines[] = '';
        $lines[] = 'Si necesita reprogramar, responda a este mensaje. ¡Gracias!';

        return implode("\n", $lines);
    }

    public static function buildMaintenanceDue(MaintenancePlan $plan): string
    {
        $company = Setting::get('company_name', 'SolarFix');
        $client = $plan->client?->name ?? 'cliente';
        $when = $plan->next_due_date->lt(today())
            ? 'ya le corresponde'
            : 'le corresponde el ' . $plan->next_due_date->translatedFormat('d \\d\\e F');

        return implode("\n", [
            "Hola {$client}, le saludamos de *{$company}*.",
            "Según su plan de mantenimiento, {$when} el mantenimiento de: *{$plan->title}*.",
            '',
            '¿Desea que agendemos una visita? Responda a este mensaje con el día y la hora que le convienen.',
        ]);
    }

    private static function money($amount): string
    {
        return money($amount);
    }
}
