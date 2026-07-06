<?php

namespace Database\Factories;

use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => null,
            'job_type' => 'script',
            'provider' => null,
            'status' => Generation::STATUS_QUEUED,
            'cost_usd' => 0,
            'credits_charged' => 0,
            'input_payload' => null,
            'output_payload' => null,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }
}
