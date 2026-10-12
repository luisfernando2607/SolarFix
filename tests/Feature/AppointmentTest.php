<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use App\Models\WhatsappLog;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private function receptionist(): User
    {
        return User::factory()->role('receptionist')->create();
    }

    private function client(): Client
    {
        return Client::create(['name' => 'Gabriela Torres', 'phone' => '0991234567', 'address' => 'Av. Amazonas N24', 'client_type' => 'individual']);
    }

    private function appointment(array $attributes = []): Appointment
    {
        return Appointment::create($attributes + [
            'client_id' => $this->client()->id,
            'type' => 'maintenance',
            'title' => 'Mantenimiento split',
            'address' => 'Av. Amazonas N24',
            'scheduled_at' => today()->addDay()->setTime(10, 0),
            'duration_min' => 60,
            'status' => 'pending',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'client_id' => $this->client()->id,
            'type' => 'installation',
            'title' => 'Instalación split 12000 BTU',
            'address' => 'Calle 10 y Av. 6',
            'scheduled_at' => today()->addDays(2)->setTime(9, 0)->format('Y-m-d\TH:i'),
            'duration_min' => 120,
        ];
    }

    public function test_receptionist_schedules_appointment(): void
    {
        $tech = User::factory()->role('technician')->create();

        $this->actingAs($this->receptionist())->post(route('appointments.store'), $this->payload(['user_id' => $tech->id]))
            ->assertSessionHasNoErrors();

        $appointment = Appointment::first();
        $this->assertSame('pending', $appointment->status);
        $this->assertSame($tech->id, $appointment->user_id);
        $this->assertSame('09:00', $appointment->scheduled_at->format('H:i'));
    }

    public function test_overlapping_appointment_for_same_technician_is_rejected(): void
    {
        $tech = User::factory()->role('technician')->create();
        $this->appointment(['user_id' => $tech->id, 'scheduled_at' => today()->addDays(2)->setTime(10, 0)]);

        // 09:00 + 120 min se cruza con la de las 10:00.
        $this->actingAs($this->receptionist())->post(route('appointments.store'), $this->payload(['user_id' => $tech->id]))
            ->assertSessionHasErrors('scheduled_at');

        // A las 11:00 ya no se cruza (la anterior termina 11:00).
        $this->actingAs($this->receptionist())->post(route('appointments.store'), $this->payload([
            'user_id' => $tech->id, 'scheduled_at' => today()->addDays(2)->setTime(11, 0)->format('Y-m-d\TH:i'),
        ]))->assertSessionHasNoErrors();
    }

    public function test_cancelled_appointments_do_not_block_schedule(): void
    {
        $tech = User::factory()->role('technician')->create();
        $this->appointment(['user_id' => $tech->id, 'scheduled_at' => today()->addDays(2)->setTime(9, 0), 'status' => 'cancelled']);

        $this->actingAs($this->receptionist())->post(route('appointments.store'), $this->payload(['user_id' => $tech->id]))
            ->assertSessionHasNoErrors();
    }

    public function test_rescheduling_resets_reminder(): void
    {
        $appointment = $this->appointment(['reminder_sent' => true]);

        $this->actingAs($this->receptionist())->put(route('appointments.update', $appointment), $this->payload(['client_id' => $appointment->client_id]));

        $this->assertFalse($appointment->fresh()->reminder_sent);
    }

    public function test_confirmation_whatsapp_confirms_and_logs(): void
    {
        $appointment = $this->appointment();

        $response = $this->actingAs($this->receptionist())->post(route('appointments.whatsapp', [$appointment, 'appointment_confirmation']));

        $this->assertStringStartsWith('https://wa.me/593991234567', $response->headers->get('Location'));
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $log = WhatsappLog::first();
        $this->assertStringContainsString('Av. Amazonas N24', $log->message_body);
        $this->assertStringContainsString('10:00', $log->message_body);
    }

    public function test_reminder_marks_sent_and_leaves_reminder_list(): void
    {
        $appointment = $this->appointment();
        $receptionist = $this->receptionist();

        $this->actingAs($receptionist)->get(route('appointments.index'))
            ->assertViewHas('reminders', fn ($r) => $r->pluck('id')->all() === [$appointment->id]);

        $this->actingAs($receptionist)->post(route('appointments.whatsapp', [$appointment, 'appointment_reminder']));

        $this->assertTrue($appointment->fresh()->reminder_sent);
        $this->assertStringContainsString('mañana', WhatsappLog::first()->message_body);
        $this->actingAs($receptionist)->get(route('appointments.index'))->assertViewHas('reminders', fn ($r) => $r->isEmpty());
    }

    public function test_technician_sees_only_own_appointments_and_cannot_schedule(): void
    {
        $tech = User::factory()->role('technician')->create();
        $mine = $this->appointment(['user_id' => $tech->id, 'title' => 'Mi visita']);
        $other = $this->appointment(['user_id' => User::factory()->role('technician')->create()->id, 'title' => 'Visita ajena', 'scheduled_at' => today()->addDay()->setTime(15, 0)]);

        $this->actingAs($tech)->get(route('appointments.index', ['week' => today()->addDay()->toDateString()]))
            ->assertOk()->assertSee('Mi visita')->assertDontSee('Visita ajena');
        $this->actingAs($tech)->get(route('appointments.show', $other))->assertForbidden();
        $this->actingAs($tech)->patch(route('appointments.status', $mine), ['status' => 'completed'])->assertRedirect();
        $this->assertSame('completed', $mine->fresh()->status);
        $this->actingAs($tech)->get(route('appointments.create'))->assertForbidden();
    }

    public function test_order_created_from_appointment_is_linked_and_assigned(): void
    {
        $tech = User::factory()->role('technician')->create();
        $appointment = $this->appointment(['user_id' => $tech->id]);
        $receptionist = $this->receptionist();

        $this->actingAs($receptionist)->get(route('orders.create', ['appointment_id' => $appointment->id]))
            ->assertOk()->assertSee('Orden desde la cita')->assertSee('Gabriela Torres');

        $this->actingAs($receptionist)->post(route('orders.store'), [
            'appointment_id' => $appointment->id,
            'client_id' => $appointment->client_id,
            'device_type' => 'aire_split',
            'declared_fault' => 'No enfría',
            'unlock_type' => 'none',
            'entry_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $order = Order::latest('id')->first();
        $this->assertSame($order->id, $appointment->fresh()->order_id);
        $this->assertSame($tech->id, $order->user_id);
    }

    public function test_appointment_with_order_cannot_be_linked_again(): void
    {
        $order = Order::create([
            'client_id' => $this->client()->id, 'order_number' => 'T-1', 'device_type' => 'aire_split',
            'declared_fault' => 'x', 'unlock_type' => 'none', 'status' => 'received', 'entry_date' => now()->toDateString(),
        ]);
        $appointment = $this->appointment(['order_id' => $order->id]);

        $this->actingAs($this->receptionist())->get(route('orders.create', ['appointment_id' => $appointment->id]))->assertStatus(422);
    }

    public function test_second_visit_from_order_prefills_and_appears_in_order(): void
    {
        $tech = User::factory()->role('technician')->create();
        $client = $this->client();
        $order = Order::create([
            'client_id' => $client->id, 'user_id' => $tech->id, 'order_number' => 'T-2', 'device_type' => 'aire_split',
            'declared_fault' => 'x', 'unlock_type' => 'none', 'status' => 'repairing', 'entry_date' => now()->toDateString(),
        ]);
        $receptionist = $this->receptionist();

        $this->actingAs($receptionist)->get(route('appointments.create', ['order_id' => $order->id]))
            ->assertOk()->assertSee('Segunda visita · orden T-2')->assertSee('Av. Amazonas N24');

        $this->actingAs($receptionist)->post(route('appointments.store'), $this->payload([
            'client_id' => $client->id, 'order_id' => $order->id, 'user_id' => $tech->id, 'type' => 'followup', 'title' => 'Segunda visita',
        ]));

        $this->actingAs($receptionist)->get(route('orders.show', $order))->assertOk()->assertSee('Seguimiento / segunda visita');
    }

    public function test_dashboard_shows_today_and_unassigned(): void
    {
        $this->appointment(['scheduled_at' => now()->addHour(), 'title' => 'Visita de hoy']);

        $this->actingAs($this->receptionist())->get(route('dashboard'))
            ->assertSee('Visita de hoy')
            ->assertSee('citas próximas sin técnico');
    }

    public function test_message_builder(): void
    {
        $appointment = $this->appointment(['scheduled_at' => \Illuminate\Support\Carbon::parse('2026-10-13 10:30')]);

        $message = WhatsAppMessage::buildAppointment('appointment_confirmation', $appointment);

        $this->assertStringContainsString('martes 13 de octubre a las 10:30', $message);
    }

    public function test_screens_render(): void
    {
        $appointment = $this->appointment();
        $receptionist = $this->receptionist();

        $this->actingAs($receptionist)->get(route('appointments.index'))->assertOk();
        $this->actingAs($receptionist)->get(route('appointments.create'))->assertOk();
        $this->actingAs($receptionist)->get(route('appointments.show', $appointment))->assertOk()->assertSee('Crear orden de servicio');
        $this->actingAs($receptionist)->get(route('appointments.edit', $appointment))->assertOk();
    }
}
