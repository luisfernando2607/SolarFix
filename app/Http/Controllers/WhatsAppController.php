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

            // Al enviar el presupuesto la orden queda esperando la aprobación del cliente.
            if ($type === 'budget' && in_array($order->status, ['received', 'diagnosing'])) {
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'from_status' => $order->status,
                    'to_status' => 'waiting_approval',
                    'changed_by' => Auth::id(),
                    'notes' => 'Presupuesto enviado por WhatsApp',
                ]);
                $order->update(['status' => 'waiting_approval']);
            }
        });

        return redirect()->away(WhatsAppMessage::url($phone, $message));
    }
}
