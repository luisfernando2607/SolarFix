<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use App\Models\WhatsappLog;
use App\Notifications\AssignmentNotification;
use Illuminate\Http\Request;

/**
 * Webhook de la WhatsApp Cloud API: Meta avisa aquí cuando un mensaje se entrega, se lee o
 * falla, y cuando un cliente responde. Se configura en Meta con la URL /webhooks/whatsapp.
 */
class WhatsAppWebhookController extends Controller
{
    private const STATUS_ORDER = ['sent' => 1, 'delivered' => 2, 'read' => 3];

    /**
     * Verificación inicial de Meta: devuelve el "challenge" si el token coincide.
     */
    public function verify(Request $request)
    {
        $token = config('services.whatsapp.verify_token');

        abort_unless($request->query('hub_mode') === 'subscribe' && filled($token)
            && hash_equals((string) $token, (string) $request->query('hub_verify_token')), 403);

        return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request)
    {
        // Solo Meta conoce el secreto de la app: la firma prueba que el aviso viene de Meta.
        // Sin WHATSAPP_APP_SECRET no se acepta nada (cualquiera podría inventar estados o respuestas).
        $secret = (string) config('services.whatsapp.app_secret');
        abort_if($secret === '', 403);
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(hash_equals($expected, (string) $request->header('X-Hub-Signature-256')), 403);

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                foreach ($value['statuses'] ?? [] as $status) {
                    $this->updateStatus($status);
                }
                foreach ($value['messages'] ?? [] as $message) {
                    $this->storeReply($message, $value['contacts'][0]['profile']['name'] ?? null);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    private function updateStatus(array $status): void
    {
        $log = WhatsappLog::where('wamid', $status['id'] ?? '')->first();
        $new = $status['status'] ?? null;
        if (! $log || ! $new) {
            return;
        }

        // Los avisos pueden llegar desordenados: nunca se retrocede (leído no vuelve a entregado).
        if ($new === 'failed') {
            $log->update(['status' => 'failed', 'status_at' => now(),
                'error' => mb_substr($status['errors'][0]['error_data']['details'] ?? $status['errors'][0]['title'] ?? 'Error de WhatsApp', 0, 500)]);
        } elseif ((self::STATUS_ORDER[$new] ?? 0) > (self::STATUS_ORDER[$log->status] ?? 0)) {
            $log->update(['status' => $new, 'status_at' => now()]);
        }
    }

    private function storeReply(array $message, ?string $profileName): void
    {
        $from = preg_replace('/\D/', '', (string) ($message['from'] ?? ''));
        if ($from === '' || WhatsappLog::where('wamid', $message['id'] ?? '')->exists()) {
            return;
        }

        $client = $this->findClient($from);
        $order = $client
            ? (Order::where('client_id', $client->id)->whereNotIn('status', ['delivered', 'closed_no_repair'])->latest('id')->first()
                ?? Order::where('client_id', $client->id)->latest('id')->first())
            : null;

        $body = match ($message['type'] ?? 'text') {
            'text' => $message['text']['body'] ?? '',
            'button' => $message['button']['text'] ?? '',
            'interactive' => $message['interactive']['button_reply']['title'] ?? $message['interactive']['list_reply']['title'] ?? '',
            'image' => '[Imagen]' . (isset($message['image']['caption']) ? ' ' . $message['image']['caption'] : ''),
            'audio' => '[Nota de voz]',
            'document' => '[Documento]',
            'location' => '[Ubicación]',
            default => '[' . ($message['type'] ?? 'Mensaje') . ']',
        };

        WhatsappLog::create([
            'order_id' => $order?->id,
            'client_id' => $client?->id,
            'trigger_event' => 'reply',
            'channel' => 'api',
            'direction' => 'in',
            'wamid' => $message['id'] ?? null,
            'phone_to' => $from,
            'message_body' => mb_substr(($client ? '' : ($profileName ? "{$profileName}: " : '')) . $body, 0, 4000),
            'sent_at' => isset($message['timestamp']) ? \Illuminate\Support\Carbon::createFromTimestamp((int) $message['timestamp']) : now(),
        ]);

        // Aviso al técnico de la orden y a recepción de la sucursal (o a la administración).
        $title = 'WhatsApp de ' . ($client?->name ?? $profileName ?? '+' . $from);
        $text = mb_strimwidth($body, 0, 120, '…') . ($order ? " · orden {$order->order_number}" : '');
        $url = $order ? route('orders.show', $order) : ($client ? route('clients.show', $client) : route('dashboard'));
        $staff = User::where('is_active', true)->where(function ($q) use ($order) {
            $q->where('role', 'receptionist')->when($order?->branch_id, fn ($q, $branch) => $q->where('branch_id', $branch));
            if ($order?->user_id) {
                $q->orWhere('id', $order->user_id);
            }
        })->pluck('id');
        if ($staff->isEmpty()) {
            $staff = User::where('is_active', true)->whereIn('role', ['super_admin', 'admin'])->pluck('id');
        }
        foreach ($staff as $id) {
            AssignmentNotification::notify($id, $title, $text, $url);
        }
    }

    /**
     * Cliente por teléfono: WhatsApp manda 593991234567; en la base puede estar como
     * 0991234567, 099 123 4567, +593 99 123 4567…
     */
    private function findClient(string $from): ?Client
    {
        $local = str_starts_with($from, '593') ? '0' . substr($from, 3) : $from;
        $exact = Client::whereIn('phone', [$from, '+' . $from, $local])->first();
        if ($exact) {
            return $exact;
        }

        // Sin espacios, guiones ni "+": termina en los últimos 9 dígitos (celular de Ecuador).
        return Client::whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '.', '') LIKE ?", ['%' . substr($from, -9)])
            ->get()
            ->first(fn ($c) => \App\Services\WhatsAppMessage::normalizePhone($c->phone) === $from);
    }
}
