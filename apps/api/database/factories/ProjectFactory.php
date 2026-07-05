<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'type' => fake()->randomElement(Project::TYPES),
            'status' => fake()->randomElement(Project::STATUSES),
            'input_payload' => null,
            'settings' => null,
        ];
    }
}
