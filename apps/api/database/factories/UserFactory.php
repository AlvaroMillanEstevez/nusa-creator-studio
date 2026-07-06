<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'credits_balance' => 20,
            'role' => 'free_user',
            'plan_key' => 'free',
            'is_unlimited' => false,
            'monthly_credit_allowance' => 20,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    public function internalCreator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'internal_creator',
            'plan_key' => 'internal_unlimited',
            'is_unlimited' => true,
        ]);
    }

    public function subscriber(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'subscriber',
            'plan_key' => 'starter',
        ]);
    }

    public function freeUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'free_user',
            'plan_key' => 'free',
            'is_unlimited' => false,
            'monthly_credit_allowance' => 20,
        ]);
    }

    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_unlimited' => true,
        ]);
    }
}
