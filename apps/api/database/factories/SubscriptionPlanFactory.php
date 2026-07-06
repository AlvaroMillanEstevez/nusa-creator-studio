<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'starter',
            'name' => 'Starter',
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'price_amount' => 99000,
            'currency' => 'IDR',
            'billing_interval' => 'month',
            'monthly_credits' => 100,
            'max_projects_per_month' => null,
            'max_generations_per_month' => null,
            'is_public' => true,
            'is_featured' => false,
            'is_active' => true,
            'is_internal' => false,
            'sort_order' => 20,
            'cta_label' => 'Choose Starter',
            'features' => ['100 monthly credits'],
            'limits' => null,
            'metadata' => null,
        ];
    }
}
