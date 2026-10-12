<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderNote;
use App\Services\OrderWorkflow;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Acciones rápidas desde el detalle de la orden, pensadas para el técnico en el celular.
 */
class OrderWorkController extends Controller
{
    public function __construct(private OrderWorkflow $workflow)
    {
    }

    /**
     * Siguiente paso del flujo (ej. "Iniciar diagnóstico") con una nota opcional.
     */
    public function status(Request $request, Order $order)
    {
        $this->authorizeOrder($order);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->workflow->nextActions($order)))],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['status.in' => 'Ese cambio de estado no está disponible para esta orden.']);

        if ($error = $this->workflow->qualityCheckError($order, $validated['status'])) {
            return back()->with('error', $error);
        }

        $this->workflow->transition($order, $validated['status'], Auth::id(), $validated['note'] ?? null);

        return back()->with('success', 'Orden ' . $order->order_number . ': ' . strtolower(Order::statuses()[$order->status]) . '.');
    }

    /**
     * El técnico se asigna una orden sin técnico.
     */
    public function take(Order $order)
    {
        abort_unless(Auth::user()->isTechnician(), 403, 'Solo un técnico puede tomar una orden.');

        abort_unless(BranchContext::allows($order->branch_id), 403, 'La orden es de otra sucursal.');

        if ($order->user_id) {
            return back()->with('error', 'Esta orden ya tiene técnico asignado.');
        }

        $order->update(['user_id' => Auth::id()]);

        return redirect()->route('orders.show', $order)->with('success', 'Tomaste la orden ' . $order->order_number . '.');
    }

    /**
     * Diagnóstico, trabajo realizado y costos de servicio sin abrir el formulario completo.
     */
    public function work(Request $request, Order $order)
    {
        $this->authorizeOrder($order);

        if (in_array($order->status, ['delivered', 'closed_no_repair'])) {
            return back()->with('error', 'La orden está cerrada.');
        }

        $validated = $request->validate([
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'work_done' => ['nullable', 'string', 'max:5000'],
            'diagnosis_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'labor_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $order->fill([
            'diagnosis' => $validated['diagnosis'] ?? null,
            'work_done' => $validated['work_done'] ?? null,
            'diagnosis_cost' => $validated['diagnosis_cost'] ?? 0,
            'labor_cost' => $validated['labor_cost'] ?? 0,
        ]);
        $order->recalculateTotals();

        return back()->with('success', 'Trabajo técnico guardado.');
    }

    public function addNote(Request $request, Order $order)
    {
        $this->authorizeOrder($order);
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $order->workNotes()->create(['user_id' => Auth::id(), 'body' => $validated['body']]);

        return back()->with('success', 'Nota agregada a la bitácora.');
    }

    public function deleteNote(Order $order, OrderNote $note)
    {
        abort_unless($note->order_id === $order->id, 404);
        $user = Auth::user();
        abort_unless($note->user_id === $user->id || $user->isAdmin() || $user->isSuperAdmin(), 403, 'Solo el autor o un administrador puede borrar la nota.');

        $note->delete();

        return back()->with('success', 'Nota eliminada.');
    }

    private function authorizeOrder(Order $order): void
    {
        abort_unless($order->isVisibleTo(Auth::user()), 403, 'Esta orden está asignada a otro técnico.');
    }
}
