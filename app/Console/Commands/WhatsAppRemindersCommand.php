<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Setting;
use App\Models\WhatsappLog;
use App\Services\WhatsAppCloud;
use App\Services\WhatsAppMessage;
use App\Services\WhatsAppTemplates;
use Illuminate\Console\Command;

/**
 * Recordatorios automáticos por la WhatsApp Cloud API (cada tarde): citas de mañana y equipos
 * listos que llevan días sin retirarse. Solo corre con el envío automático activado.
 */
class WhatsAppRemindersCommand extends Command
{
    protected $signature = 'solarfix:whatsapp-reminders';

    protected $description = 'Envía por WhatsApp los recordatorios de citas de mañana y de equipos sin retirar';

    public function handle(): int
    {
        if (! WhatsAppCloud::automatic()) {
            $this->line('Envío automático de WhatsApp desactivado (Configuración > Envío de WhatsApp).');

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        // Citas de mañana sin recordatorio.
        $appointments = Appointment::with('client')
            ->whereBetween('scheduled_at', [today()->addDay(), today()->addDay()->endOfDay()])
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('reminder_sent', false)
            ->get();
        foreach ($appointments as $appointment) {
            if (! $phone = WhatsAppMessage::normalizePhone($appointment->client?->phone)) {
                continue;
            }
            $log = WhatsAppCloud::sendTemplate('appointment_reminder', $phone, WhatsAppTemplates::forAppointment('appointment_reminder', $appointment),
                ['order_id' => $appointment->order_id, 'client_id' => $appointment->client_id]);
            if ($log->status === 'failed') {
                $failed++;
            } else {
                $appointment->update(['reminder_sent' => true]);
                $sent++;
            }
        }

        // Equipos listos hace N días o más, sin recordatorio en los últimos N días.
        $days = max(1, (int) Setting::get('pickup_reminder_days', 7));
        $orders = Order::with('client')->where('status', 'ready')
            ->whereNotIn('id', OrderStatusHistory::select('order_id')->where('to_status', 'ready')->where('changed_at', '>=', now()->subDays($days)))
            ->where('updated_at', '<', now()->subDays($days))
            ->whereNotIn('id', WhatsappLog::select('order_id')->whereNotNull('order_id')->where('trigger_event', 'pickup_reminder')
                ->where('sent_at', '>=', now()->subDays($days))->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'failed')))
            ->get();
        foreach ($orders as $order) {
            if (! $phone = WhatsAppMessage::normalizePhone($order->client?->phone)) {
                continue;
            }
            $log = WhatsAppCloud::sendTemplate('pickup_reminder', $phone, WhatsAppTemplates::forOrder('pickup_reminder', $order),
                ['order_id' => $order->id, 'client_id' => $order->client_id]);
            $log->status === 'failed' ? $failed++ : $sent++;
        }

        $this->info("Recordatorios enviados: {$sent}" . ($failed ? " · con error: {$failed}" : ''));

        return self::SUCCESS;
    }
}
