<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChecklistController extends Controller
{
    public function index()
    {
        $checklists = Checklist::withCount(['items as active_items_count' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->keyBy(fn ($c) => $c->device_type . '|' . $c->stage);

        return view('checklists.index', [
            'checklists' => $checklists,
            'deviceTypes' => Order::deviceTypes(),
            'stages' => Checklist::stages(),
        ]);
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'device_type' => ['required', Rule::in(array_keys(Order::deviceTypes()))],
            'stage' => ['required', Rule::in(array_keys(Checklist::stages()))],
        ]);

        if ($existing = Checklist::where($validated)->first()) {
            return redirect()->route('checklists.edit', $existing);
        }

        $checklist = new Checklist($validated + [
            'name' => Checklist::stages()[$validated['stage']] . ' ' . strtolower(Order::deviceTypes()[$validated['device_type']]),
            'is_active' => true,
        ]);

        return view('checklists.edit', ['checklist' => $checklist, 'items' => collect()]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateChecklist($request);
        $request->validate([
            'device_type' => ['required', Rule::in(array_keys(Order::deviceTypes()))],
            'stage' => ['required', Rule::in(array_keys(Checklist::stages())),
                Rule::unique('checklists')->where('device_type', $request->device_type)],
        ]);

        $checklist = DB::transaction(function () use ($request, $validated) {
            $checklist = Checklist::create([
                'device_type' => $request->device_type,
                'stage' => $request->stage,
                'name' => $validated['name'],
                'is_active' => $request->boolean('is_active'),
            ]);
            $this->syncItems($checklist, $validated['items'] ?? []);

            return $checklist;
        });

        return redirect()->route('checklists.index')->with('success', 'Checklist "' . $checklist->name . '" creado.');
    }

    public function edit(Checklist $checklist)
    {
        return view('checklists.edit', ['checklist' => $checklist, 'items' => $checklist->activeItems()->get()]);
    }

    public function update(Request $request, Checklist $checklist)
    {
        $validated = $this->validateChecklist($request);

        DB::transaction(function () use ($request, $checklist, $validated) {
            $checklist->update(['name' => $validated['name'], 'is_active' => $request->boolean('is_active')]);
            $this->syncItems($checklist, $validated['items'] ?? []);
        });

        return redirect()->route('checklists.index')->with('success', 'Checklist "' . $checklist->name . '" actualizado.');
    }

    private function validateChecklist(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['boolean'],
            'items' => ['nullable', 'array', 'max:60'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.label' => ['nullable', 'string', 'max:150'],
            'items.*.is_required' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Guarda los ítems en el orden recibido. Los quitados se desactivan (no se borran)
     * para conservar los resultados de órdenes anteriores.
     */
    private function syncItems(Checklist $checklist, array $items): void
    {
        $keptIds = [];
        $order = 0;

        foreach ($items as $row) {
            $label = trim($row['label'] ?? '');
            if ($label === '') {
                continue;
            }

            $attributes = [
                'label' => $label,
                'sort_order' => ++$order,
                'is_required' => (bool) ($row['is_required'] ?? false),
                'is_active' => true,
            ];

            $item = ! empty($row['id']) ? $checklist->items()->whereKey($row['id'])->first() : null;
            $item ? $item->update($attributes) : $item = $checklist->items()->create($attributes);
            $keptIds[] = $item->id;
        }

        ChecklistItem::where('checklist_id', $checklist->id)->whereNotIn('id', $keptIds)->update(['is_active' => false]);
    }
}
