<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use App\Models\WhatsappLog;
use App\Services\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['week' => ['nullable', 'date'], 'user_id' => ['nullable', 'integer']]);

        $user = Auth::user();
        $weekStart = ($request->filled('week') ? Carbon::parse($request->week) : today())->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $appointments = Appointment::with(['client', 'user', 'order'])
            ->visibleTo($user)
            ->when(! $user->isTechnician() && $request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->whereBetween('scheduled_at', [$weekStart, $weekEnd])
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy(fn ($a) => $a->scheduled_at->toDateString());

        return view('appointments.index', [
            'weekStart' => $weekStart,
            'days' => collect(range(0, 6))->map(fn ($i) => $weekStart->copy()->addDays($i)),
            'appointments' => $appointments,
            'technicians' => $this->technicians(),
            'reminders' => $user->can('manage-appointments') ? $this->pendingReminders() : collect(),
        ]);
    }

    public function create(Request $request)
    {
        $appointment = new Appointment([
            'type' => 'home_visit',
            'duration_min' => 60,
            'scheduled_at' => now()->addDay()->setTime(9, 0),
        ]);

        // Segunda visita desde una orden: cliente, dirección y técnico de la orden.
        if ($request->filled('order_id')) {
            $order = Order::with('client')->findOrFail($request->order_id);
            $appointment->fill([
                'order_id' => $order->id,
                'client_id' => $order->client_id,
                'user_id' => $order->user?->isTechnician() ? $order->user_id : null,
                'type' => 'followup',
                'title' => 'Segunda visita · orden ' . $order->order_number,
                'address' => $order->client?->address,
            ]);
        }

        return view('appointments.form', ['appointment' => $appointment, 'technicians' => $this->technicians()]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $appointment = Appointment::create($validated + ['status' => 'pending']);

        return redirect()->route('appointments.show', $appointment)
            ->with('success', 'Cita agendada. Envía la confirmación por WhatsApp al cliente.');
    }

    public function show(Appointment $appointment)
    {
        $this->authorizeView($appointment);
        $appointment->load(['client', 'user', 'order']);

        return view('appointments.show', ['appointment' => $appointment]);
    }

    public function edit(Appointment $appointment)
    {
        return view('appointments.form', ['appointment' => $appointment->load('client'), 'technicians' => $this->technicians($appointment)]);
    }

    public function update(Request $request, Appointment $appointment)
    {
        $validated = $this->validated($request, $appointment);

        // Si cambia la fecha, el recordatorio debe enviarse de nuevo.
        if (! $appointment->scheduled_at->equalTo(Carbon::parse($validated['scheduled_at']))) {
            $validated['reminder_sent'] = false;
        }

        $appointment->update($validated);

        return redirect()->route('appointments.show', $appointment)->with('success', 'Cita actualizada.');
    }

    /**
     * Confirmar, completar o cancelar. El técnico asignado también puede hacerlo.
     */
    public function status(Request $request, Appointment $appointment)
    {
        $this->authorizeView($appointment);
        $validated = $request->validate(['status' => ['required', Rule::in(array_keys(Appointment::statuses()))]]);

        $appointment->update(['status' => $validated['status']]);

        return back()->with('success', 'Cita ' . strtolower(Appointment::statuses()[$validated['status']]) . '.');
    }

    public function whatsapp(Appointment $appointment, string $type)
    {
        abort_unless(array_key_exists($type, WhatsAppMessage::APPOINTMENT_TYPES), 404);
        $this->authorizeView($appointment);

        $phone = WhatsAppMessage::normalizePhone($appointment->client?->phone);
        if (! $phone) {
            return back()->with('error', 'El cliente no tiene un número de teléfono válido para WhatsApp.');
        }

        $message = WhatsAppMessage::buildAppointment($type, $appointment);

        WhatsappLog::create([
            'order_id' => $appointment->order_id,
            'client_id' => $appointment->client_id,
            'trigger_event' => $type,
            'phone_to' => $phone,
            'message_body' => $message,
            'sent_by' => Auth::id(),
            'sent_at' => now(),
        ]);

        if ($type === 'appointment_reminder') {
            $appointment->update(['reminder_sent' => true]);
        } elseif ($appointment->status === 'pending') {
            $appointment->update(['status' => 'confirmed']);
        }

        return redirect()->away(WhatsAppMessage::url($phone, $message));
    }

    private function validated(Request $request, ?Appointment $appointment = null): array
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'user_id' => ['nullable', Rule::in($this->technicians($appointment)->pluck('id'))],
            'order_id' => ['nullable', 'exists:orders,id'],
            'type' => ['required', Rule::in(array_keys(Appointment::types()))],
            'title' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date'],
            'duration_min' => ['required', 'integer', 'min:15', 'max:480'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['scheduled_at' => 'fecha y hora']);

        if (! empty($validated['user_id'])) {
            $clash = Appointment::overlapping((int) $validated['user_id'], Carbon::parse($validated['scheduled_at']), (int) $validated['duration_min'], $appointment?->id)->first();
            if ($clash) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'scheduled_at' => 'El técnico ya tiene una cita de ' . $clash->scheduled_at->format('H:i') . ' a ' . $clash->endsAt()->format('H:i') . ' (' . $clash->title . ').',
                ]);
            }
        }

        return $validated;
    }

    private function technicians(?Appointment $appointment = null)
    {
        return User::where(function ($q) use ($appointment) {
            $q->where('role', 'technician')->where('is_active', true);
            if ($appointment?->user_id) {
                $q->orWhere('id', $appointment->user_id);
            }
        })->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Citas de mañana sin recordatorio enviado.
     */
    private function pendingReminders()
    {
        return Appointment::with(['client', 'user'])
            ->whereDate('scheduled_at', today()->addDay())
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('reminder_sent', false)
            ->orderBy('scheduled_at')
            ->get();
    }

    private function authorizeView(Appointment $appointment): void
    {
        $user = Auth::user();
        abort_if($user->isTechnician() && $appointment->user_id !== $user->id, 403, 'Esta cita está asignada a otro técnico.');
    }
}
