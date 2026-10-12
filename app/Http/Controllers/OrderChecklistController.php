<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Checklist;
use App\Models\Order;
use App\Models\OrderChecklistResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderChecklistController extends Controller
{
    public function edit(Order $order, string $stage)
    {
        $checklist = $this->checklistFor($order, $stage);

        return view('checklists.fill', [
            'order' => $order->load(['client', 'brand', 'deviceModel']),
            'stage' => $stage,
            'checklist' => $checklist,
            'items' => $checklist->activeItems()->get(),
            'results' => $order->checklistResults()->get()->keyBy('checklist_item_id'),
            'locked' => $this->isLocked($order),
        ]);
    }

    public function update(Request $request, Order $order, string $stage)
    {
        $checklist = $this->checklistFor($order, $stage);

        if ($this->isLocked($order)) {
            return redirect()->route('orders.show', $order)->with('error', 'La orden está cerrada; el checklist ya no se puede modificar.');
        }

        $items = $checklist->activeItems()->get();
        $rules = [];
        foreach ($items as $item) {
            $rules["results.{$item->id}"] = [$item->is_required ? 'required' : 'nullable', Rule::in(array_keys(Checklist::results()))];
            $rules["notes.{$item->id}"] = ['nullable', 'string', 'max:255'];
        }
        $validated = $request->validate($rules, [
            'results.*.required' => 'Marca este ítem.',
        ]);

        DB::transaction(function () use ($order, $items, $validated, $checklist) {
            foreach ($items as $item) {
                OrderChecklistResult::updateOrCreate(
                    ['order_id' => $order->id, 'checklist_item_id' => $item->id],
                    [
                        'result' => $validated['results'][$item->id] ?? 'pending',
                        'notes' => $validated['notes'][$item->id] ?? null,
                    ],
                );
            }

            $damaged = collect($validated['results'] ?? [])->filter(fn ($r) => $r === 'damaged')->count();
            AuditLog::record(
                'checklist',
                $order,
                description: $checklist->name . ' · orden ' . $order->order_number . ($damaged ? " · {$damaged} con daño" : ''),
                orderId: $order->id,
            );
        });

        return redirect()->route('orders.show', $order)->with('success', Checklist::stages()[$stage] . ': checklist guardado.');
    }

    private function checklistFor(Order $order, string $stage): Checklist
    {
        abort_unless($order->isVisibleTo(Auth::user()), 403, 'Esta orden está asignada a otro técnico.');

        $checklist = Checklist::for($order->device_type, $stage);
        abort_unless($checklist, 404, 'No hay checklist para este tipo de equipo y etapa.');

        return $checklist;
    }

    private function isLocked(Order $order): bool
    {
        return in_array($order->status, ['delivered', 'closed_no_repair']);
    }
}
