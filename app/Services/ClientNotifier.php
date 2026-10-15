<?php

namespace App\Services;

use App\Mail\OrderUpdateMail;
use App\Models\EmailLog;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

use function Illuminate\Support\defer;

/**
 * Avisos por correo al cliente cuando cambia su orden. Se envían después de responder la
 * página (si el servidor de correo tarda o falla, el usuario no espera) y quedan registrados.
 */
class ClientNotifier
{
    /** Estado al que llega la orden → aviso que corresponde. */
    public const BY_STATUS = [
        'waiting_approval' => 'budget',
        'ready' => 'ready',
        'delivered' => 'delivery',
    ];

    public static function enabled(): bool
    {
        return Setting::get('email_notifications', '0') === '1';
    }

    /**
     * Llamado cuando la orden cambia de estado: avisa si ese estado tiene correo.
     */
    public static function statusChanged(Order $order, string $status, bool $skipWhatsApp = false): void
    {
        if (! isset(self::BY_STATUS[$status])) {
            return;
        }
        $type = self::BY_STATUS[$status];

        if (self::enabled()) {
            self::send($order, $type);
        }
        // WhatsApp automático por la Cloud API (después de responder la página).
        if (! $skipWhatsApp && WhatsAppCloud::automatic()) {
            $userId = Auth::id();
            defer(function () use ($order, $type, $userId) {
                $order = $order->fresh('client');
                $phone = WhatsAppMessage::normalizePhone($order?->client?->phone);
                if ($phone) {
                    WhatsAppCloud::sendTemplate($type, $phone, WhatsAppTemplates::forOrder($type, $order),
                        ['order_id' => $order->id, 'client_id' => $order->client_id, 'sent_by' => $userId]);
                }
            });
        }
    }

    /**
     * Envía (después de la respuesta) y registra el resultado. Devuelve false si el cliente no tiene correo.
     */
    public static function send(Order $order, string $type, bool $now = false): bool
    {
        $order->loadMissing('client');
        $email = $order->client?->email;
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $userId = Auth::id();
        $deliver = function () use ($order, $type, $email, $userId) {
            $mail = new OrderUpdateMail($order->fresh(['client']), $type);
            $log = ['order_id' => $order->id, 'client_id' => $order->client_id, 'type' => $type, 'to' => $email,
                'subject' => $mail->envelope()->subject, 'sent_by' => $userId, 'sent_at' => now()];
            try {
                Mail::to($email)->send($mail);
                EmailLog::create($log + ['status' => 'sent']);
            } catch (\Throwable $e) {
                report($e);
                EmailLog::create($log + ['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
            }
        };

        $now ? $deliver() : defer($deliver);

        return true;
    }
}
