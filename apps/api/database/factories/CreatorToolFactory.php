<?php

namespace Database\Factories;

use App\Models\CreatorTool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreatorTool>
 */
class CreatorToolFactory extends Factory
{
    protected $model = CreatorTool::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'title' => fake()->words(3, true),
            'short_description' => fake()->sentence(),
            'long_description' => fake()->paragraph(),
            'category' => 'campaign_creation',
            'status' => 'coming_soon',
            'route' => null,
            'credit_cost' => 1,
            'credit_cost_label' => '1 credit',
            'badge_label' => 'Coming soon',
            'is_recommended' => false,
            'is_visible' => true,
            'sort_order' => 100,
            'metadata' => null,
        ];
    }
}
