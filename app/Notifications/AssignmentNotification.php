<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Aviso interno (campana) cuando se asigna una orden, cita o reclamo a un técnico.
 */
class AssignmentNotification extends Notification
{
    public function __construct(
        private string $title,
        private string $body,
        private string $url,
    ) {
    }

    /**
     * Notifica al usuario asignado, salvo que se haya asignado a sí mismo.
     */
    public static function notify($userId, string $title, string $body, string $url): void
    {
        if (! $userId || (int) $userId === (int) Auth::id()) {
            return;
        }

        User::find((int) $userId)?->notify(new self($title, $body, $url));
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
