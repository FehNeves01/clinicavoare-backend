<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $balance = $this->faker->randomFloat(2, 0, 60);

        return [
            'name' => $this->faker->company(),
            'email' => $this->faker->unique()->companyEmail(),
            'phone' => $this->faker->numerify('119########'),
            'birth_date' => $this->faker->date(),
            'credit_balance' => $balance,
            'credit_consumed' => $this->faker->randomFloat(2, 0, $balance),
            'credit_expires_at' => now()->endOfMonth(),
        ];
    }
}

