<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function authenticate(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        return $user;
    }

    protected function createRoom(array $overrides = []): Room
    {
        $defaults = [
            'number' => $overrides['number'] ?? 'ROOM-' . uniqid(),
            'name' => 'Sala Principal',
            'description' => 'Sala para reuniões',
            'capacity' => 10,
            'is_active' => true,
        ];

        return Room::create(array_merge($defaults, $overrides));
    }

    protected function createClientWithCredit(float $credit = 10): Client
    {
        $client = Client::factory()->create([
            'credit_balance' => 0,
            'credit_consumed' => 0,
        ]);

        $client->addCredit($credit);
        $client->refresh();

        return $client;
    }

    public function test_user_can_create_booking(): void
    {
        $this->authenticate();
        $client = $this->createClientWithCredit(8);
        $room = $this->createRoom();

        $payload = [
            'client_id' => $client->id,
            'room_id' => $room->id,
            'booking_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'hours_booked' => 2,
            'notes' => 'Primeiro agendamento',
        ];

        $response = $this->postJson('/api/bookings', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'client_id' => $client->id,
                'room_id' => $room->id,
                'hours_booked' => 2,
                'notes' => 'Primeiro agendamento',
            ]);

        $this->assertDatabaseHas('bookings', [
            'client_id' => $client->id,
            'room_id' => $room->id,
            'hours_booked' => 2,
        ]);

        $this->assertSame(6.0, $client->fresh()->credit_balance);
    }

    public function test_user_can_list_bookings_with_filters(): void
    {
        $this->authenticate();
        $client = $this->createClientWithCredit(12);
        $roomA = $this->createRoom(['number' => '201', 'name' => 'Sala Norte']);
        $roomB = $this->createRoom(['number' => '202', 'name' => 'Sala Sul']);

        $this->postJson('/api/bookings', [
            'client_id' => $client->id,
            'room_id' => $roomA->id,
            'booking_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'hours_booked' => 2,
        ])->assertCreated();

        $this->postJson('/api/bookings', [
            'client_id' => $client->id,
            'room_id' => $roomB->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '16:00',
            'hours_booked' => 2,
            'status' => 'confirmed',
        ])->assertCreated();

        $response = $this->postJson('/api/bookings/list', [
            'client_id' => $client->id,
            'status' => 'confirmed',
        ]);

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['room_id' => $roomB->id, 'status' => 'confirmed']);
    }

    public function test_user_can_list_bookings_by_room(): void
    {
        $this->authenticate();
        $client = $this->createClientWithCredit(8);
        $room = $this->createRoom(['number' => '303', 'name' => 'Sala Central']);

        $this->postJson('/api/bookings', [
            'client_id' => $client->id,
            'room_id' => $room->id,
            'booking_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:30',
            'hours_booked' => 1.5,
        ])->assertCreated();

        $response = $this->postJson('/api/bookings/by-room', [
            'room_id' => $room->id,
        ]);

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['room_id' => $room->id]);
    }

    public function test_user_can_update_booking_and_adjust_credit(): void
    {
        $this->authenticate();
        $client = $this->createClientWithCredit(6);
        $room = $this->createRoom(['number' => '401', 'name' => 'Sala Leste']);

        $createResponse = $this->postJson('/api/bookings', [
            'client_id' => $client->id,
            'room_id' => $room->id,
            'booking_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'hours_booked' => 1,
        ])->assertCreated();

        $bookingId = $createResponse->json('id');

        $updateResponse = $this->postJson("/api/bookings/{$bookingId}/update", [
            'end_time' => '11:00',
            'hours_booked' => 2,
        ]);

        $updateResponse->assertOk()
            ->assertJsonFragment([
                'id' => $bookingId,
                'hours_booked' => 2,
            ]);

        $this->assertSame(4.0, $client->fresh()->credit_balance);
    }

    public function test_update_booking_fails_when_insufficient_credit(): void
    {
        $this->authenticate();
        $client = $this->createClientWithCredit(2);
        $room = $this->createRoom(['number' => '402', 'name' => 'Sala Oeste']);

        $bookingId = $this->postJson('/api/bookings', [
            'client_id' => $client->id,
            'room_id' => $room->id,
            'booking_date' => now()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '14:00',
            'hours_booked' => 1,
        ])->json('id');

        $response = $this->postJson("/api/bookings/{$bookingId}/update", [
            'hours_booked' => 5,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['hours_booked']);
    }

    public function test_user_can_cancel_booking_and_restore_credit(): void
    {
        $this->authenticate();
        $client = $this->createClientWithCredit(5);
        $room = $this->createRoom(['number' => '501', 'name' => 'Sala Oeste']);

        $createResponse = $this->postJson('/api/bookings', [
            'client_id' => $client->id,
            'room_id' => $room->id,
            'booking_date' => now()->toDateString(),
            'start_time' => '15:00',
            'end_time' => '17:00',
            'hours_booked' => 2,
        ])->assertCreated();

        $bookingId = $createResponse->json('id');

        $cancelResponse = $this->postJson("/api/bookings/{$bookingId}/cancel");

        $cancelResponse->assertOk()
            ->assertJsonFragment(['message' => 'Agendamento cancelado com sucesso.']);

        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'status' => 'cancelled',
        ]);

        $this->assertSame(5.0, $client->fresh()->credit_balance);
    }
}

