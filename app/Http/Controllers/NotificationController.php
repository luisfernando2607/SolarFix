<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        return view('notifications.index', [
            'notifications' => Auth::user()->notifications()->paginate(20),
        ]);
    }

    /**
     * Marca la notificación como leída y lleva a la orden o cita.
     */
    public function open(string $id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? route('dashboard');

        // Solo enlaces internos.
        $internal = parse_url($url, PHP_URL_HOST) === parse_url(url('/'), PHP_URL_HOST);

        return redirect()->to($internal ? $url : route('dashboard'));
    }

    public function markAllRead()
    {
        Auth::user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Notificaciones marcadas como leídas.');
    }
}
