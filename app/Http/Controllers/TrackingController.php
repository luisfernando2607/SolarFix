<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderReview;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AssignmentNotification;
use App\Services\OrderWorkflow;
use App\Services\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Página pública de seguimiento: el cliente ve el estado de su orden con el enlace privado.
 * No muestra PIN, notas internas, bitácora, técnico ni datos personales.
 * Desde ella puede aprobar o rechazar el presupuesto y calificar el servicio al recibir el equipo.
 */
class TrackingController extends Controller
{
    public function show(string $token)
    {
        $order = $this->order($token);
        $order->load(['brand', 'deviceModel', 'warranty', 'statusHistory', 'review']);

        return response()->view('tracking.show', [
            'order' => $order,
            'company' => \App\Support\Company::info($order->branch_id),
            'statuses' => Order::statuses(),
            'canDecide' => $this->canDecide($order),
            'budget' => $this->canDecide($order) ? WhatsAppMessage::budgetConcepts($order) : [],
            'canReview' => $this->canReview($order),
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * El cliente descarga su presupuesto mientras espera su respuesta.
     */
    public function budget(string $token)
    {
        $order = $this->order($token);
        abort_unless($this->canDecide($order), 404);

        return OrderController::budgetPdf($order)->stream('presupuesto-' . $order->order_number . '.pdf');
    }

    /**
     * El cliente aprueba o rechaza el presupuesto: la orden avanza como si lo hiciera el taller.
     */
    public function decide(Request $request, string $token)
    {
        $order = $this->order($token);
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $decided = DB::transaction(function () use ($order, $validated) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            // Un segundo clic (o el taller que ya registró la respuesta) no vuelve a cambiar la orden.
            if (! $this->canDecide($order)) {
                return null;
            }

            $approve = $validated['decision'] === 'approve';
            $note = $approve ? 'Presupuesto aprobado por el cliente en línea'
                : 'Presupuesto rechazado por el cliente en línea' . (filled($validated['reason'] ?? null) ? ': ' . $validated['reason'] : '');
            app(OrderWorkflow::class)->transition($order, $approve ? 'repairing' : 'closed_no_repair', null, $note);

            return [$order, $approve, $note];
        });

        if (! $decided) {
            return redirect()->route('tracking.show', $token)->with('info', 'Esta orden ya no está esperando su respuesta.');
        }

        [$order, $approve, $note] = $decided;
        $this->notifyStaff($order, $approve ? 'Presupuesto aprobado' : 'Presupuesto rechazado', "Orden {$order->order_number}: {$note}");

        return redirect()->route('tracking.show', $token)->with('success', $approve
            ? '¡Gracias! Aprobó la reparación; ya la iniciamos.'
            : 'Registramos que no desea la reparación. Puede pasar a retirar su equipo.');
    }

    /**
     * Calificación del servicio (una vez, después de la entrega).
     */
    public function review(Request $request, string $token)
    {
        $order = $this->order($token);
        if (! $this->canReview($order)) {
            return redirect()->route('tracking.show', $token);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], [], ['rating' => 'calificación', 'comment' => 'comentario']);

        $review = OrderReview::firstOrCreate(['order_id' => $order->id], [
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'created_at' => now(),
        ]);

        // Una mala experiencia se atiende rápido: avisa a la administración.
        if ($review->wasRecentlyCreated && $review->rating <= 3) {
            $admins = User::whereIn('role', ['super_admin', 'admin'])->where('is_active', true)->pluck('id');
            foreach ($admins as $id) {
                AssignmentNotification::notify($id, "Calificación de {$review->rating}/5",
                    "Orden {$order->order_number}" . ($review->comment ? ': ' . $review->comment : ''), route('orders.show', $order));
            }
        }

        return redirect()->route('tracking.show', $token)->with('success', '¡Gracias por su opinión!');
    }

    private function order(string $token): Order
    {
        return Order::where('tracking_token', $token)->firstOrFail();
    }

    private function canDecide(Order $order): bool
    {
        return $order->status === 'waiting_approval' && Setting::get('online_approval', '1') === '1';
    }

    private function canReview(Order $order): bool
    {
        return $order->status === 'delivered' && Setting::get('customer_survey', '1') === '1'
            && ! $order->review()->exists();
    }

    /**
     * Avisa al técnico de la orden; si no tiene, a recepción de la sucursal.
     */
    private function notifyStaff(Order $order, string $title, string $body): void
    {
        $ids = $order->user_id
            ? [$order->user_id]
            : User::where('role', 'receptionist')->where('is_active', true)->where('branch_id', $order->branch_id)->pluck('id')->all();

        foreach ($ids as $id) {
            AssignmentNotification::notify($id, $title, $body, route('orders.show', $order));
        }
    }
}
