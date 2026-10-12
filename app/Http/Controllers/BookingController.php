<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AssignmentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Página pública "Agendar": el cliente pide una visita o un turno en el taller.
 * Queda como cita pendiente sin técnico; recepción la confirma y la asigna.
 */
class BookingController extends Controller
{
    public function create()
    {
        abort_unless($this->enabled(), 404);

        return response()->view('booking.create', [
            'company' => ['name' => Setting::get('company_name', 'SolarFix'), 'phone' => Setting::get('company_phone')],
            'types' => Appointment::types(),
            'branches' => Branch::activeList(),
        ])->header('X-Robots-Tag', 'noindex');
    }

    public function store(Request $request)
    {
        abort_unless($this->enabled(), 404);

        // Campo trampa: los robots lo llenan, las personas no lo ven.
        if (filled($request->input('website'))) {
            return redirect()->route('booking.create')->with('success', 'Recibimos su solicitud.');
        }

        $branches = Branch::activeList();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'min:7', 'max:30', 'regex:/^[0-9+\s()-]+$/'],
            'type' => ['required', Rule::in(array_keys(Appointment::types()))],
            'date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:' . today()->addDays(90)->toDateString()],
            'time' => ['required', 'date_format:H:i'],
            'address' => ['nullable', 'string', 'max:255'],
            'details' => ['required', 'string', 'max:1000'],
            'branch_id' => [$branches->count() > 1 ? 'required' : 'nullable', Rule::in($branches->pluck('id'))],
        ], [], [
            'name' => 'nombre', 'phone' => 'teléfono', 'type' => 'servicio', 'date' => 'fecha', 'time' => 'hora',
            'details' => 'detalle', 'branch_id' => 'sucursal',
        ]);

        $scheduledAt = Carbon::parse($validated['date'] . ' ' . $validated['time']);
        if ($scheduledAt->lte(now())) {
            return back()->withInput()->withErrors(['time' => 'Elija una hora posterior a la actual.']);
        }

        // Cliente existente por teléfono (con o sin espacios); si no, se crea.
        $digits = preg_replace('/\D/', '', $validated['phone']);
        $client = Client::where('phone', $validated['phone'])->orWhere('phone', $digits)->first()
            ?? Client::create(['name' => $validated['name'], 'phone' => $digits ?: $validated['phone'], 'address' => $validated['address'] ?? null, 'client_type' => 'individual']);

        $appointment = Appointment::create([
            'branch_id' => $validated['branch_id'] ?? Branch::mainId(),
            'client_id' => $client->id,
            'type' => $validated['type'],
            'title' => 'Solicitud en línea · ' . Appointment::types()[$validated['type']],
            'address' => $validated['address'] ?? $client->address,
            'scheduled_at' => $scheduledAt,
            'duration_min' => 60,
            'status' => 'pending',
            'requested_online' => true,
            'notes' => "Solicitado por {$validated['name']} ({$validated['phone']}):\n{$validated['details']}",
        ]);

        $staff = User::where('is_active', true)->where('role', 'receptionist')->where('branch_id', $appointment->branch_id)->pluck('id');
        if ($staff->isEmpty()) {
            $staff = User::where('is_active', true)->whereIn('role', ['super_admin', 'admin'])->pluck('id');
        }
        foreach ($staff as $id) {
            AssignmentNotification::notify($id, 'Nueva solicitud de cita', "{$validated['name']} · " . $scheduledAt->format('d/m/Y H:i'), route('appointments.show', $appointment));
        }

        return redirect()->route('booking.create')->with('success',
            'Recibimos su solicitud para el ' . $scheduledAt->format('d/m/Y \a \l\a\s H:i') . '. Le contactaremos para confirmarla.');
    }

    private function enabled(): bool
    {
        return Setting::get('online_booking', '1') === '1';
    }
}
