<?php

namespace Database\Factories;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditTransaction>
 */
class CreditTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'generation_id' => null,
            'type' => fake()->randomElement(CreditTransactionType::cases()),
            'amount' => fake()->numberBetween(0, 100),
            'balance_after' => fake()->numberBetween(0, 10_000),
            'reason' => null,
            'metadata' => null,
        ];
    }
}
