<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ClientCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function authenticate(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        return $user;
    }

    public function test_user_can_list_clients(): void
    {
        $this->authenticate();
        Client::factory()->count(2)->create();

        $response = $this->getJson('/api/clients');

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data', 2)
                ->etc());
    }

    public function test_user_can_create_client(): void
    {
        $this->authenticate();

        $payload = [
            'name' => 'Clinica Alpha',
            'email' => 'alpha@example.com',
            'phone' => '11999999999',
            'birth_date' => '1990-05-10',
            'credit_balance' => 12,
            'credit_consumed' => 4,
            'credit_expires_at' => now()->addMonth()->toDateString(),
        ];

        $response = $this->postJson('/api/clients', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'Clinica Alpha',
                'email' => 'alpha@example.com',
                'phone' => '11999999999',
            ]);

        $this->assertDatabaseHas('clients', [
            'email' => 'alpha@example.com',
            'name' => 'Clinica Alpha',
            'credit_consumed' => 4,
        ]);
    }

    public function test_user_can_view_client(): void
    {
        $this->authenticate();
        $client = Client::factory()->create([
            'credit_balance' => 8,
            'credit_consumed' => 2,
            'credit_expires_at' => now()->addDay(),
        ]);

        $response = $this->getJson("/api/clients/{$client->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $client->id,
                'email' => $client->email,
            ]);
    }

    public function test_user_can_update_client(): void
    {
        $this->authenticate();
        $client = Client::factory()->create();

        $response = $this->putJson("/api/clients/{$client->id}", [
            'name' => 'Clinica Beta',
            'email' => 'beta@example.com',
            'credit_consumed' => 10,
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Clinica Beta',
                'email' => 'beta@example.com',
                'credit_consumed' => 10,
            ]);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'email' => 'beta@example.com',
            'credit_consumed' => 10,
        ]);
    }

    public function test_user_can_delete_client(): void
    {
        $this->authenticate();
        $client = Client::factory()->create();

        $response = $this->deleteJson("/api/clients/{$client->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('clients', [
            'id' => $client->id,
        ]);
    }

    public function test_it_validates_unique_email_on_create(): void
    {
        $this->authenticate();
        Client::factory()->create(['email' => 'duplicado@example.com']);

        $response = $this->postJson('/api/clients', [
            'name' => 'Outra Clínica',
            'email' => 'duplicado@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
