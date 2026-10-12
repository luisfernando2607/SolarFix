<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Lucía Paredes', 'phone' => '099 123 4567', 'type' => 'home_visit',
            'date' => today()->addDays(2)->toDateString(), 'time' => '10:30',
            'address' => 'Av. Siempre Viva 123', 'details' => 'Aire acondicionado no enfría',
        ];
    }

    public function test_client_requests_appointment_online(): void
    {
        $desk = User::factory()->role('receptionist')->create();

        $this->get(route('booking.create'))->assertOk()->assertSee('Enviar solicitud');
        $this->post(route('booking.store'), $this->payload())->assertRedirect(route('booking.create'))->assertSessionHas('success');

        $appointment = Appointment::first();
        $this->assertTrue($appointment->requested_online);
        $this->assertSame('pending', $appointment->status);
        $this->assertNull($appointment->user_id);
        $this->assertSame('0991234567', $appointment->client->phone);
        $this->assertStringContainsString('no enfría', $appointment->notes);
        $this->assertSame(1, $desk->notifications()->count());
        $this->actingAs($desk)->get(route('appointments.show', $appointment))->assertSee('Solicitada en línea');
    }

    public function test_existing_client_is_reused_by_phone(): void
    {
        $client = Client::create(['name' => 'Lucía P.', 'phone' => '0991234567', 'client_type' => 'individual']);

        $this->post(route('booking.store'), $this->payload());

        $this->assertSame(1, Client::count());
        $this->assertSame($client->id, Appointment::first()->client_id);
    }

    public function test_bots_and_past_dates_are_rejected(): void
    {
        $this->post(route('booking.store'), $this->payload(['website' => 'http://spam']))->assertSessionHas('success');
        $this->post(route('booking.store'), $this->payload(['date' => today()->subDay()->toDateString()]))->assertSessionHasErrors('date');
        $this->post(route('booking.store'), $this->payload(['phone' => '<script>']))->assertSessionHasErrors('phone');

        $this->assertSame(0, Appointment::count());
        $this->assertSame(0, Client::count());
    }

    public function test_booking_can_be_disabled(): void
    {
        Setting::put('online_booking', '0', 'portal');

        $this->get(route('booking.create'))->assertNotFound();
        $this->post(route('booking.store'), $this->payload())->assertNotFound();
    }
}
