<?php

namespace Tests\Feature;

use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectGenerationHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_generations_are_listed_newest_first_with_results(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['type' => 'affiliate_campaign']);
        $otherProject = Project::factory()->for($user)->create(['type' => 'affiliate_campaign']);

        $oldest = $this->generation($user, $project, 'Old hook', now()->subMinutes(10));
        $newest = $this->generation($user, $project, 'New hook', now());
        $this->generation($user, $otherProject, 'Other project hook', now()->addMinute());

        $response = $this->getJson("/api/v1/projects/{$project->id}/generations");

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.0.result_payload.hooks.0', 'New hook')
            ->assertJsonPath('data.1.id', $oldest->id)
            ->assertJsonMissing(['result_payload' => ['hooks' => ['Other project hook']]])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'project_id',
                        'job_type',
                        'provider',
                        'status',
                        'cost_usd',
                        'credits_charged',
                        'request_payload',
                        'result_payload',
                        'started_at',
                        'finished_at',
                        'created_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_user_cannot_list_another_users_project_generations(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($owner)->create(['type' => 'affiliate_campaign']);

        $this->generation($owner, $project, 'Private hook', now());

        $this->actingAs($otherUser)
            ->getJson("/api/v1/projects/{$project->id}/generations")
            ->assertNotFound();
    }

    public function test_listing_generation_history_does_not_charge_credits(): void
    {
        $user = User::factory()->create(['credits_balance' => 7]);
        $project = Project::factory()->for($user)->create(['type' => 'affiliate_campaign']);
        $generation = $this->generation($user, $project, 'Saved hook', now());
        $transactionCount = CreditTransaction::query()->count();

        $this->getJson("/api/v1/projects/{$project->id}/generations")
            ->assertOk()
            ->assertJsonPath('data.0.id', $generation->id);

        $this->assertSame(7, $user->refresh()->credits_balance);
        $this->assertSame($transactionCount, CreditTransaction::query()->count());
    }

    private function generation(
        User $user,
        Project $project,
        string $hook,
        \DateTimeInterface $createdAt,
    ): Generation {
        return Generation::factory()
            ->for($user)
            ->for($project)
            ->create([
                'job_type' => 'generate_affiliate_campaign',
                'provider' => 'mock',
                'status' => 'completed',
                'credits_charged' => 1,
                'request_payload' => $project->input_payload,
                'result_payload' => [
                    'hooks' => [$hook],
                    'script' => [
                        'title' => 'Saved script',
                        'duration_seconds' => 30,
                        'scenes' => [],
                    ],
                    'caption' => 'Saved caption',
                    'hashtags' => ['#Saved'],
                    'cta' => 'See details.',
                    'notes' => ['Use real footage.'],
                ],
                'started_at' => $createdAt,
                'finished_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
    }
}
