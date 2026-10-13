<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\WhatsAppMessage;
use App\Support\Company;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso al cliente sobre su orden (presupuesto, equipo listo, entrega o recordatorio de retiro).
 * El texto es el mismo de WhatsApp (respeta las plantillas de Configuración).
 */
class OrderUpdateMail extends Mailable
{
    public const SUBJECTS = [
        'budget' => 'Presupuesto de su orden :orden',
        'ready' => 'Su equipo está listo · orden :orden',
        'delivery' => 'Gracias por su confianza · orden :orden',
        'pickup_reminder' => 'Recordatorio: su equipo le espera · orden :orden',
    ];

    public function __construct(public Order $order, public string $type)
    {
    }

    public function envelope(): Envelope
    {
        $company = Company::info($this->order->branch_id);

        return new Envelope(
            subject: str_replace(':orden', $this->order->order_number, self::SUBJECTS[$this->type]) . ' · ' . $company['name'],
            replyTo: $company['email'] ? [$company['email']] : [],
        );
    }

    public function content(): Content
    {
        // *negrita* de WhatsApp → texto normal; los enlaces se muestran como botón aparte.
        $text = WhatsAppMessage::build($this->type, $this->order);
        $url = $this->order->tracking_token ? $this->order->trackingUrl() : null;
        if ($url) {
            $text = trim(preg_replace('/^.*' . preg_quote($url, '/') . '.*$/m', '', $text));
        }

        return new Content(view: 'emails.order-update', with: [
            'company' => Company::info($this->order->branch_id),
            'lines' => preg_split("/\n/", str_replace('*', '', $text)),
            'url' => $url,
            'action' => match ($this->type) {
                'budget' => 'Ver y aprobar el presupuesto',
                'delivery' => 'Calificar el servicio',
                default => 'Ver el estado de mi orden',
            },
        ]);
    }
}
