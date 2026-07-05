<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_mock_generation_creates_a_completed_generation_and_result(): void
    {
        $user = User::factory()->create(['credits_balance' => 20]);
        $project = $this->affiliateProject($user);

        $response = $this->postJson("/api/v1/projects/{$project->id}/generate-campaign");

        $response
            ->assertCreated()
            ->assertJsonPath('data.job_type', 'generate_affiliate_campaign')
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.credits_charged', 1)
            ->assertJsonCount(5, 'data.result_payload.hooks')
            ->assertJsonCount(10, 'data.result_payload.hashtags')
            ->assertJsonStructure([
                'data' => [
                    'result_payload' => [
                        'hooks',
                        'script' => ['title', 'duration_seconds', 'scenes'],
                        'caption',
                        'hashtags',
                        'cta',
                        'notes',
                    ],
                ],
                'meta' => ['credits_balance'],
            ]);

        $generationId = $response->json('data.id');

        $this->assertDatabaseHas('generations', [
            'id' => $generationId,
            'user_id' => $user->id,
            'project_id' => $project->id,
            'job_type' => 'generate_affiliate_campaign',
            'provider' => 'mock',
            'status' => 'completed',
            'credits_charged' => 1,
        ]);

        $generation = Generation::query()->findOrFail($generationId);

        $this->assertNotNull($generation->started_at);
        $this->assertNotNull($generation->finished_at);
        $this->assertIsString($generation->result_payload['caption']);
        $this->assertIsString($generation->result_payload['cta']);
    }

    public function test_successful_generation_charges_one_credit_and_records_positive_usage(): void
    {
        $user = User::factory()->create(['credits_balance' => 3]);
        $project = $this->affiliateProject($user);

        $response = $this->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertCreated()
            ->assertJsonPath('meta.credits_balance', 2);

        $generationId = $response->json('data.id');
        $transaction = CreditTransaction::query()->where('generation_id', $generationId)->firstOrFail();

        $this->assertSame(CreditTransactionType::Usage, $transaction->type);
        $this->assertSame(1, $transaction->amount);
        $this->assertSame(2, $transaction->balance_after);
        $this->assertSame('Mock affiliate campaign generation', $transaction->reason);
        $this->assertSame(2, $user->refresh()->credits_balance);
    }

    public function test_insufficient_credits_returns_a_clear_error_without_negative_balance(): void
    {
        $user = User::factory()->create(['credits_balance' => 0]);
        $project = $this->affiliateProject($user);

        $this->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertPaymentRequired()
            ->assertJsonPath('code', 'insufficient_credits')
            ->assertJsonPath('available_credits', 0)
            ->assertJsonPath('required_credits', 1)
            ->assertJsonPath(
                'message',
                'You do not have enough platform credits to generate this campaign.',
            );

        $this->assertSame(0, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_non_affiliate_projects_cannot_generate_campaign_content(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()
            ->for($user)
            ->create(['type' => 'video_enhancer']);

        $this->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_project_type')
            ->assertJsonPath(
                'message',
                'Only affiliate campaign projects can use campaign generation.',
            );

        $this->assertDatabaseCount('generations', 0);
        $this->assertSame(20, $user->refresh()->credits_balance);
    }

    public function test_mock_generation_supports_the_three_campaign_languages(): void
    {
        $user = User::factory()->create(['credits_balance' => 3]);

        foreach ([
            'english' => 'Before you scroll',
            'indonesian' => 'Sebelum scroll',
            'spanish' => 'Antes de seguir',
        ] as $language => $expectedHook) {
            $project = $this->affiliateProject($user, $language);

            $response = $this->postJson("/api/v1/projects/{$project->id}/generate-campaign")
                ->assertCreated();

            $hook = $response->json('data.result_payload.hooks.0');

            $this->assertIsString($hook);
            $this->assertStringContainsString($expectedHook, $hook);
        }

        $this->assertSame(0, $user->refresh()->credits_balance);
    }

    public function test_user_cannot_generate_another_users_campaign(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = $this->affiliateProject($owner);

        $this->actingAs($otherUser)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertNotFound();

        $this->assertDatabaseCount('generations', 0);
        $this->assertSame(20, $owner->refresh()->credits_balance);
        $this->assertSame(20, $otherUser->refresh()->credits_balance);
    }

    private function affiliateProject(User $user, string $language = 'english'): Project
    {
        return Project::factory()
            ->for($user)
            ->create([
                'title' => 'Portable smoothie blender',
                'type' => 'affiliate_campaign',
                'status' => 'draft',
                'input_payload' => [
                    'product_name' => 'Portable smoothie blender',
                    'product_description' => 'A rechargeable blender for quick smoothies.',
                    'target_audience' => 'Busy students who want healthy breakfasts.',
                    'language' => $language,
                    'tone' => 'problem_solution',
                ],
                'settings' => [
                    'platform' => 'tiktok',
                    'format' => 'vertical_9_16',
                ],
            ]);
    }
}
