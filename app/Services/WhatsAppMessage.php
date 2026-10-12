<?php

namespace App\Services;

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
        $order->loadMissing(['client', 'brand', 'deviceModel']);

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
        foreach ([
            'Diagnóstico' => $order->diagnosis_cost,
            'Mano de obra' => $order->labor_cost,
            'Repuestos' => $order->parts_cost,
            'Recargo' => $order->surcharge_amount,
        ] as $label => $amount) {
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

    private static function money($amount): string
    {
        return '$' . number_format((float) $amount, 2);
    }
}
