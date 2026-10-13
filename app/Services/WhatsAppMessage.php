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
        'pickup_reminder' => 'Recordatorio de retiro',
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

    /**
     * Plantillas por defecto. Se pueden reemplazar en Configuración; las variables van entre llaves.
     * Una línea que solo contiene una variable vacía se omite (ej. {diagnostico} sin diagnóstico).
     */
    public const TEMPLATES = [
        'budget' => "Hola {cliente}, le saludamos de *{negocio}*.\nSu equipo {equipo} (orden *{orden}*) ya fue diagnosticado.\n\n{diagnostico}\n\n*Presupuesto:*\n{detalle_presupuesto}\n*Total: {total}*\n{abonos}\n\nPuede aprobar o rechazar la reparación aquí: {seguimiento}\nTambién puede responder a este mensaje. ¡Gracias!",
        'ready' => "Hola {cliente}, le saludamos de *{negocio}*.\nSu equipo {equipo} (orden *{orden}*) está *listo para retirar*.\n{saldo_pendiente}\n\n{direccion_negocio}\n\nEstado de su orden: {seguimiento}",
        'delivery' => "Hola {cliente}, gracias por confiar en *{negocio}*.\nLe entregamos su equipo {equipo} (orden *{orden}*).\n\n{trabajo_realizado}\n\n{garantia}\n\nConserve su número de orden ({orden}) para cualquier consulta.\n¿Cómo le atendimos? Califíquenos aquí: {seguimiento}",
        'pickup_reminder' => "Hola {cliente}, le saludamos de *{negocio}*.\nLe recordamos que su equipo {equipo} (orden *{orden}*) está listo para retirar desde hace {dias} días.\n{saldo_pendiente}\n\n{direccion_negocio}\n\nEstado de su orden: {seguimiento}",
        'appointment_confirmation' => "Hola {cliente}, le saludamos de *{negocio}*.\nSu cita de {servicio} quedó agendada para el *{fecha_hora}*.\n{direccion}\n{tecnico}\n\nSi necesita reprogramar, responda a este mensaje. ¡Gracias!",
        'appointment_reminder' => "Hola {cliente}, le recordamos de *{negocio}* su cita de {servicio} para *mañana {fecha_hora}*.\n{direccion}\n{tecnico}\n\nSi necesita reprogramar, responda a este mensaje. ¡Gracias!",
        'maintenance_due' => "Hola {cliente}, le saludamos de *{negocio}*.\nSegún su plan de mantenimiento, {cuando} el mantenimiento de: *{equipo}*.\n\n¿Desea que agendemos una visita? Responda a este mensaje con el día y la hora que le convienen.",
    ];

    public const TEMPLATE_LABELS = [
        'budget' => 'Presupuesto',
        'ready' => 'Equipo listo',
        'delivery' => 'Entrega y garantía',
        'pickup_reminder' => 'Recordatorio de retiro',
        'appointment_confirmation' => 'Confirmación de cita',
        'appointment_reminder' => 'Recordatorio de cita',
        'maintenance_due' => 'Mantenimiento preventivo',
    ];

    /**
     * Variables disponibles por plantilla (para mostrarlas en Configuración).
     */
    public const VARIABLES = [
        'budget' => ['cliente', 'negocio', 'telefono_negocio', 'equipo', 'orden', 'diagnostico', 'detalle_presupuesto', 'total', 'abonos', 'saldo', 'seguimiento'],
        'ready' => ['cliente', 'negocio', 'telefono_negocio', 'equipo', 'orden', 'total', 'saldo', 'saldo_pendiente', 'direccion_negocio', 'seguimiento'],
        'delivery' => ['cliente', 'negocio', 'telefono_negocio', 'equipo', 'orden', 'trabajo_realizado', 'garantia', 'total', 'seguimiento'],
        'pickup_reminder' => ['cliente', 'negocio', 'telefono_negocio', 'equipo', 'orden', 'dias', 'total', 'saldo', 'saldo_pendiente', 'direccion_negocio', 'seguimiento'],
        'appointment_confirmation' => ['cliente', 'negocio', 'telefono_negocio', 'servicio', 'fecha_hora', 'direccion', 'tecnico'],
        'appointment_reminder' => ['cliente', 'negocio', 'telefono_negocio', 'servicio', 'fecha_hora', 'direccion', 'tecnico'],
        'maintenance_due' => ['cliente', 'negocio', 'telefono_negocio', 'equipo', 'cuando', 'fecha'],
    ];

    public static function template(string $type): string
    {
        $custom = trim((string) Setting::get('wa_template_' . $type, ''));

        return $custom !== '' ? $custom : self::TEMPLATES[$type];
    }

    public static function build(string $type, Order $order): string
    {
        $order->loadMissing(['client', 'brand', 'deviceModel', 'parts.sparePart']);

        $vars = self::common($order->client?->name, $order->branch_id) + [
            'equipo' => trim(implode(' ', array_filter([
                Order::deviceTypes()[$order->device_type] ?? null,
                $order->brand?->name ?? $order->brand_text,
                $order->deviceModel?->name ?? $order->model_text,
            ]))),
            'orden' => $order->order_number,
            'seguimiento' => $order->tracking_token ? $order->trackingUrl() : '',
            'total' => money($order->total_amount),
            'saldo' => money($order->balance_due),
        ];

        $vars += match ($type) {
            'budget' => [
                'diagnostico' => $order->diagnosis ? '*Diagnóstico:* ' . $order->diagnosis : '',
                'detalle_presupuesto' => self::budgetLines($order),
                'abonos' => (float) $order->amount_paid > 0
                    ? 'Abonado: ' . money($order->amount_paid) . "\n" . 'Saldo: ' . money($order->balance_due)
                    : '',
            ],
            'pickup_reminder' => [
                'dias' => (string) self::daysReady($order),
                'saldo_pendiente' => (float) $order->balance_due > 0 ? 'Saldo pendiente: ' . money($order->balance_due) : '',
                'direccion_negocio' => ($address = \App\Support\Company::info($order->branch_id)['address']) ? "Le esperamos en: {$address}" : '',
            ],
            'ready' => [
                'saldo_pendiente' => (float) $order->balance_due > 0 ? 'Saldo pendiente: ' . money($order->balance_due) : '',
                'direccion_negocio' => ($address = \App\Support\Company::info($order->branch_id)['address']) ? "Le esperamos en: {$address}" : '',
            ],
            'delivery' => [
                'trabajo_realizado' => $order->work_done ? '*Trabajo realizado:* ' . $order->work_done : '',
                'garantia' => $order->warranty_days > 0
                    ? "*Garantía:* {$order->warranty_days} días, hasta el "
                        . ($order->delivery_date ?? now())->copy()->addDays($order->warranty_days)->format('d/m/Y') . '.'
                    : '',
            ],
        };

        return self::render(self::template($type), $vars);
    }

    public const APPOINTMENT_TYPES = [
        'appointment_confirmation' => 'Confirmación de cita',
        'appointment_reminder' => 'Recordatorio de cita',
    ];

    public static function buildAppointment(string $type, Appointment $appointment): string
    {
        $appointment->loadMissing(['client', 'user']);

        return self::render(self::template($type), self::common($appointment->client?->name, $appointment->branch_id) + [
            'servicio' => strtolower(Appointment::types()[$appointment->type] ?? 'visita'),
            'fecha_hora' => $appointment->scheduled_at->translatedFormat('l d \d\e F') . ' a las ' . $appointment->scheduled_at->format('H:i'),
            'direccion' => $appointment->address ? "Dirección: {$appointment->address}" : '',
            'tecnico' => $appointment->user ? "Técnico: {$appointment->user->name}" : '',
        ]);
    }

    public static function buildMaintenanceDue(MaintenancePlan $plan): string
    {
        return self::render(self::template('maintenance_due'), self::common($plan->client?->name, $plan->branch_id) + [
            'equipo' => $plan->title,
            'cuando' => $plan->next_due_date->lt(today())
                ? 'ya le corresponde'
                : 'le corresponde el ' . $plan->next_due_date->translatedFormat('d \d\e F'),
            'fecha' => $plan->next_due_date->format('d/m/Y'),
        ]);
    }

    /**
     * Reemplaza las variables; omite las líneas que solo tenían una variable vacía.
     */
    public static function render(string $template, array $vars): string
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $template) as $line) {
            if (preg_match('/^\s*\{([a-z_]+)\}\s*$/', $line, $m) && array_key_exists($m[1], $vars) && $vars[$m[1]] === '') {
                continue;
            }
            $lines[] = $line;
        }

        $replacements = [];
        foreach ($vars as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
        }

        $text = strtr(implode("\n", $lines), $replacements);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    private static function common(?string $client, ?int $branchId = null): array
    {
        $company = \App\Support\Company::info($branchId);

        return [
            'cliente' => $client ?? 'cliente',
            'negocio' => $company['name'],
            'telefono_negocio' => (string) $company['phone'],
        ];
    }

    /**
     * Días desde que la orden quedó lista (según el historial; si no hay, la última modificación).
     */
    public static function daysReady(Order $order): int
    {
        $since = \App\Models\OrderStatusHistory::where('order_id', $order->id)->where('to_status', 'ready')->max('changed_at');

        return (int) ($since ? \Illuminate\Support\Carbon::parse($since) : $order->updated_at)->diffInDays(now());
    }

    private static function budgetLines(Order $order): string
    {
        return implode("\n", array_map(fn ($c) => "- {$c[0]}: " . money($c[1]), self::budgetConcepts($order)));
    }

    /**
     * Conceptos del presupuesto con monto (para WhatsApp y la página del cliente).
     */
    public static function budgetConcepts(Order $order): array
    {
        $order->loadMissing('parts.sparePart');
        $concepts = [['Diagnóstico', $order->diagnosis_cost], ['Mano de obra', $order->labor_cost]];
        if ($order->parts->isNotEmpty()) {
            foreach ($order->parts as $part) {
                $concepts[] = [($part->sparePart?->name ?? 'Repuesto') . ' x' . $part->quantity, $part->subtotal];
            }
        } else {
            $concepts[] = ['Repuestos', $order->parts_cost];
        }
        $concepts[] = ['Recargo', $order->surcharge_amount];

        return array_values(array_filter($concepts, fn ($c) => (float) $c[1] > 0));
    }
}
