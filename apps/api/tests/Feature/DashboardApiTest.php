<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_current_user_balance_and_scoped_stats(): void
    {
        $user = User::factory()->create(['credits_balance' => 13]);
        $otherUser = User::factory()->create(['credits_balance' => 99]);

        $affiliateDraft = Project::factory()->for($user)->create([
            'type' => 'affiliate_campaign',
            'status' => 'draft',
        ]);
        Project::factory()->for($user)->create([
            'type' => 'affiliate_campaign',
            'status' => 'completed',
        ]);
        Project::factory()->for($user)->create([
            'type' => 'video_enhancer',
            'status' => 'draft',
        ]);
        Project::factory()->for($user)->create([
            'type' => 'product_to_video',
            'status' => 'completed',
        ]);
        $otherProject = Project::factory()->for($otherUser)->create();

        $this->generation($user, $affiliateDraft, 'completed', now()->subMinutes(3));
        $this->generation($user, $affiliateDraft, 'failed', now()->subMinutes(2));
        $this->generation($user, $affiliateDraft, 'completed', now()->subMinute());
        $this->generation($otherUser, $otherProject, 'completed', now());

        CreditTransaction::factory()->for($user)->create([
            'type' => CreditTransactionType::Usage,
            'amount' => 1,
        ]);
        CreditTransaction::factory()->for($user)->create([
            'type' => CreditTransactionType::Usage,
            'amount' => 2,
        ]);
        CreditTransaction::factory()->for($user)->create([
            'type' => CreditTransactionType::AdminGrant,
            'amount' => 50,
        ]);
        CreditTransaction::factory()->for($otherUser)->create([
            'type' => CreditTransactionType::Usage,
            'amount' => 9,
        ]);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.credits_balance', 13)
            ->assertJsonPath('stats.total_projects', 4)
            ->assertJsonPath('stats.total_generations', 3)
            ->assertJsonPath('stats.completed_generations', 2)
            ->assertJsonPath('stats.failed_generations', 1)
            ->assertJsonPath('stats.draft_projects', 2)
            ->assertJsonPath('stats.total_credits_spent', 3)
            ->assertJsonPath('stats.affiliate_campaign_projects', 2)
            ->assertJsonPath('stats.video_enhancer_projects', 1)
            ->assertJsonPath('stats.product_to_video_projects', 1);
    }

    public function test_recent_projects_are_limited_and_ordered_newest_first_with_generation_summary(): void
    {
        $user = User::factory()->create();
        $projects = collect();

        foreach (range(1, 6) as $index) {
            $createdAt = now()->subMinutes(7 - $index);
            $projects->push(Project::factory()->for($user)->create([
                'title' => "Project {$index}",
                'type' => 'affiliate_campaign',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]));
        }

        $newest = $projects->last();
        $this->generation($user, $newest, 'failed', now()->subMinute());
        $latestGeneration = $this->generation($user, $newest, 'completed', now());

        $response = $this->getJson('/api/v1/dashboard');

        $response
            ->assertOk()
            ->assertJsonCount(5, 'recent_projects')
            ->assertJsonPath('recent_projects.0.id', $newest->id)
            ->assertJsonPath('recent_projects.0.generations_count', 2)
            ->assertJsonPath('recent_projects.0.latest_generation_status', 'completed')
            ->assertJsonPath(
                'recent_projects.0.latest_generation_created_at',
                $latestGeneration->created_at->toISOString(),
            )
            ->assertJsonPath('recent_projects.4.id', $projects->get(1)->id);

        $this->assertNotContains(
            $projects->first()->id,
            collect($response->json('recent_projects'))->pluck('id')->all(),
        );
    }

    public function test_recent_generations_are_newest_first_and_do_not_expose_payloads(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'title' => 'Portable light campaign',
            'type' => 'product_to_video',
        ]);
        $generations = collect();

        foreach (range(1, 6) as $index) {
            $generations->push($this->generation(
                $user,
                $project,
                'completed',
                now()->subMinutes(7 - $index),
                ['private_request' => "request-{$index}"],
                ['private_result' => "result-{$index}"],
            ));
        }

        $response = $this->getJson('/api/v1/dashboard')->assertOk();

        $response
            ->assertJsonCount(5, 'recent_generations')
            ->assertJsonPath('recent_generations.0.id', $generations->last()->id)
            ->assertJsonPath('recent_generations.0.project_title', 'Portable light campaign')
            ->assertJsonPath('recent_generations.0.project_type', 'product_to_video')
            ->assertJsonPath('recent_generations.4.id', $generations->get(1)->id);

        foreach ($response->json('recent_generations') as $generation) {
            $this->assertArrayNotHasKey('request_payload', $generation);
            $this->assertArrayNotHasKey('result_payload', $generation);
            $this->assertArrayNotHasKey('error_message', $generation);
        }
    }

    public function test_dashboard_respects_authenticated_and_development_user_ownership(): void
    {
        $developmentUser = User::factory()->create(['name' => 'Development Owner']);
        $otherUser = User::factory()->create(['name' => 'Authenticated Owner']);
        Project::factory()->for($developmentUser)->create(['title' => 'Development project']);
        Project::factory()->for($otherUser)->create(['title' => 'Authenticated project']);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('user.id', $developmentUser->id)
            ->assertJsonPath('stats.total_projects', 1)
            ->assertJsonPath('recent_projects.0.title', 'Development project')
            ->assertJsonMissing(['title' => 'Authenticated project']);

        $this->actingAs($otherUser)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('user.id', $otherUser->id)
            ->assertJsonPath('stats.total_projects', 1)
            ->assertJsonPath('recent_projects.0.title', 'Authenticated project')
            ->assertJsonMissing(['title' => 'Development project']);
    }

    /**
     * @param  array<string, mixed>|null  $requestPayload
     * @param  array<string, mixed>|null  $resultPayload
     */
    private function generation(
        User $user,
        Project $project,
        string $status,
        \DateTimeInterface $createdAt,
        ?array $requestPayload = null,
        ?array $resultPayload = null,
    ): Generation {
        return Generation::factory()
            ->for($user)
            ->for($project)
            ->create([
                'job_type' => 'generate_affiliate_campaign',
                'provider' => 'mock',
                'status' => $status,
                'credits_charged' => 1,
                'request_payload' => $requestPayload,
                'result_payload' => $resultPayload,
                'finished_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
    }
}
