<?php

namespace Tests\Feature;

use App\Contracts\AffiliateCampaignProvider;
use App\Enums\CreditTransactionType;
use App\Jobs\GenerateAffiliateCampaignJob;
use App\Jobs\GenerateProductVideoScriptJob;
use App\Jobs\GenerateVideoEnhancerJob;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\GenerationJobProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class AsyncGenerationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_endpoint_queues_and_charges_once_before_dispatch(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->project($user, 'affiliate_campaign');

        $response = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertCreated()
            ->assertJsonPath('data.status', Generation::STATUS_QUEUED)
            ->assertJsonPath('data.job_type', 'generate_affiliate_campaign')
            ->assertJsonPath('data.credits_charged', 1)
            ->assertJsonPath('data.output_payload', null)
            ->assertJsonPath('meta.credits_balance', 4);

        $generationId = $response->json('data.id');

        Queue::assertPushed(
            GenerateAffiliateCampaignJob::class,
            fn (GenerateAffiliateCampaignJob $job): bool => $job->generationId === $generationId,
        );
        $this->assertDatabaseCount('generations', 1);
        $this->assertSame(1, CreditTransaction::query()
            ->where('generation_id', $generationId)
            ->where('type', CreditTransactionType::Usage->value)
            ->count());
    }

    public function test_all_existing_generation_jobs_complete_and_store_output(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10]);

        $cases = [
            [
                'type' => 'affiliate_campaign',
                'endpoint' => 'generate-campaign',
                'job' => GenerateAffiliateCampaignJob::class,
                'output_key' => 'hooks',
            ],
            [
                'type' => 'video_enhancer',
                'endpoint' => 'generate-video-enhancer',
                'job' => GenerateVideoEnhancerJob::class,
                'output_key' => 'improved_caption',
            ],
            [
                'type' => 'product_to_video',
                'endpoint' => 'generate-product-video-script',
                'job' => GenerateProductVideoScriptJob::class,
                'output_key' => 'storyboard',
            ],
        ];

        foreach ($cases as $case) {
            $project = $this->project($user, $case['type']);
            $generationId = $this->actingAs($user)
                ->postJson("/api/v1/projects/{$project->id}/{$case['endpoint']}")
                ->assertCreated()
                ->json('data.id');

            $job = new $case['job']($generationId);
            app()->call([$job, 'handle']);

            $generation = Generation::query()->findOrFail($generationId);
            $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
            $this->assertNotNull($generation->started_at);
            $this->assertNotNull($generation->finished_at);
            $this->assertArrayHasKey($case['output_key'], $generation->output_payload);
        }
    }

    public function test_generation_detail_is_owner_scoped_and_returns_output(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = $this->project($owner, 'affiliate_campaign');
        $generation = Generation::factory()->for($owner)->for($project)->create([
            'job_type' => 'generate_affiliate_campaign',
            'status' => Generation::STATUS_COMPLETED,
            'output_payload' => ['hooks' => ['A useful hook']],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->actingAs($owner)
            ->getJson("/api/v1/generations/{$generation->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $generation->id)
            ->assertJsonPath('data.output_payload.hooks.0', 'A useful hook')
            ->assertJsonPath('data.result_payload.hooks.0', 'A useful hook');

        $this->actingAs($other)
            ->getJson("/api/v1/generations/{$generation->id}")
            ->assertNotFound();
    }

    public function test_history_includes_async_lifecycle_statuses_without_charging(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user, 'affiliate_campaign');

        foreach ([
            Generation::STATUS_QUEUED,
            Generation::STATUS_PROCESSING,
            Generation::STATUS_COMPLETED,
            Generation::STATUS_FAILED,
        ] as $status) {
            Generation::factory()->for($user)->for($project)->create([
                'status' => $status,
            ]);
        }

        $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/generations")
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonFragment(['status' => Generation::STATUS_QUEUED])
            ->assertJsonFragment(['status' => Generation::STATUS_PROCESSING])
            ->assertJsonFragment(['status' => Generation::STATUS_COMPLETED])
            ->assertJsonFragment(['status' => Generation::STATUS_FAILED]);

        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_failed_job_uses_safe_error_and_refunds_the_charge_once(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 2]);
        $project = $this->project($user, 'affiliate_campaign');
        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertCreated()
            ->json('data.id');

        $this->app->bind(AffiliateCampaignProvider::class, fn () => new class implements AffiliateCampaignProvider
        {
            public function generate(array $input): array
            {
                throw new RuntimeException('SQLSTATE secret in /var/www/provider.php:42');
            }
        });

        $job = new GenerateAffiliateCampaignJob($generationId);
        $exception = null;

        try {
            $job->handle(
                app(AffiliateCampaignProvider::class),
                app(GenerationJobProcessor::class),
            );
        } catch (RuntimeException $caught) {
            $exception = $caught;
            $job->failed($caught);
        }

        $this->assertNotNull($exception);
        $job->failed($exception);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame(
            'Generation failed due to an internal processing error.',
            $generation->error_message,
        );
        $this->assertStringNotContainsString('SQLSTATE', $generation->error_message);
        $this->assertStringNotContainsString('/var/www', $generation->error_message);

        $detailResponse = $this->actingAs($user)
            ->getJson("/api/v1/generations/{$generationId}")
            ->assertOk()
            ->assertJsonPath(
                'data.error_message',
                'Generation failed due to an internal processing error.',
            );
        $this->assertStringNotContainsString('SQLSTATE', $detailResponse->getContent());
        $this->assertStringNotContainsString('/var/www', $detailResponse->getContent());
        $this->assertSame(2, $user->refresh()->credits_balance);
        $this->assertSame(1, CreditTransaction::query()
            ->where('generation_id', $generationId)
            ->where('type', CreditTransactionType::Refund->value)
            ->count());
    }

    public function test_processing_the_same_job_twice_does_not_charge_or_generate_twice(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 3]);
        $project = $this->project($user, 'affiliate_campaign');
        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertCreated()
            ->json('data.id');

        $job = new GenerateAffiliateCampaignJob($generationId);
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);

        $this->assertSame(2, $user->refresh()->credits_balance);
        $this->assertSame(1, CreditTransaction::query()
            ->where('generation_id', $generationId)
            ->where('type', CreditTransactionType::Usage->value)
            ->count());
    }

    private function project(User $user, string $type): Project
    {
        return Project::factory()->for($user)->create([
            'type' => $type,
            'status' => 'draft',
            'input_payload' => [
                'product_name' => 'Portable creator light',
                'product_description' => 'A compact light for creator desks.',
                'target_audience' => 'Mobile-first creators',
                'language' => 'english',
                'tone' => 'natural',
                'video_title' => 'Creator light demo',
                'video_context' => 'A desk setup demonstration.',
                'spoken_summary' => 'A creator explains the light.',
                'style' => 'natural',
                'key_benefits' => 'Compact, adjustable and simple.',
                'video_goal' => 'product_demo',
                'video_style' => 'natural',
                'duration_seconds' => 30,
            ],
        ]);
    }
}
