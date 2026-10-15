<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\WhatsappLog;
use App\Services\WhatsAppMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WhatsAppController extends Controller
{
    /**
     * Envía ahora mismo el aviso por correo al cliente (además de los automáticos).
     */
    public function email(Order $order, string $type)
    {
        abort_unless(array_key_exists($type, \App\Mail\OrderUpdateMail::SUBJECTS), 404);
        abort_unless($order->isVisibleTo(Auth::user()), 403, 'Esta orden está asignada a otro técnico.');

        if (! \App\Services\ClientNotifier::send($order, $type, now: true)) {
            return back()->with('error', 'El cliente no tiene un correo válido.');
        }

        $log = \App\Models\EmailLog::where('order_id', $order->id)->latest('id')->first();

        return $log?->status === 'sent'
            ? back()->with('success', 'Correo enviado a ' . $log->to . '.')
            : back()->with('error', 'No se pudo enviar el correo: ' . ($log?->error ?? 'error desconocido') . '. Revisa la configuración de correo del servidor.');
    }

    /**
     * Registra el mensaje y abre WhatsApp con el texto listo para enviar.
     */
    public function send(Order $order, string $type)
    {
        abort_unless(array_key_exists($type, WhatsAppMessage::TYPES), 404);
        abort_unless($order->isVisibleTo(Auth::user()), 403, 'Esta orden está asignada a otro técnico.');

        $phone = WhatsAppMessage::normalizePhone($order->client?->phone);
        if (! $phone) {
            return back()->with('error', 'El cliente no tiene un número de teléfono válido para WhatsApp.');
        }

        // Con la Cloud API: se envía desde aquí y se vuelve a la orden con el resultado.
        if (\App\Services\WhatsAppCloud::enabled()) {
            $log = \App\Services\WhatsAppCloud::sendTemplate($type, $phone, \App\Services\WhatsAppTemplates::forOrder($type, $order),
                ['order_id' => $order->id, 'client_id' => $order->client_id]);
            if ($log->status === 'failed') {
                return back()->with('error', 'WhatsApp no se envió: ' . $log->error);
            }
            $this->afterBudgetSent($order, $type);

            return back()->with('success', 'Mensaje de WhatsApp enviado a ' . $phone . '.');
        }

        $message = WhatsAppMessage::build($type, $order);

        DB::transaction(function () use ($order, $type, $phone, $message) {
            WhatsappLog::create([
                'order_id' => $order->id,
                'client_id' => $order->client_id,
                'trigger_event' => $type,
                'phone_to' => $phone,
                'message_body' => $message,
                'sent_by' => Auth::id(),
                'sent_at' => now(),
            ]);
            $this->afterBudgetSent($order, $type);
        });

        return redirect()->away(WhatsAppMessage::url($phone, $message));
    }

    /**
     * Al enviar el presupuesto la orden queda esperando la aprobación del cliente.
     */
    private function afterBudgetSent(Order $order, string $type): void
    {
        if ($type !== 'budget' || ! in_array($order->status, ['received', 'diagnosing'])) {
            return;
        }

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $order->status,
            'to_status' => 'waiting_approval',
            'changed_by' => Auth::id(),
            'notes' => 'Presupuesto enviado por WhatsApp',
        ]);
        $order->update(['status' => 'waiting_approval']);
        // El aviso por WhatsApp ya salió; aquí solo el correo (si está activo).
        \App\Services\ClientNotifier::statusChanged($order, 'waiting_approval', skipWhatsApp: true);
    }
}
