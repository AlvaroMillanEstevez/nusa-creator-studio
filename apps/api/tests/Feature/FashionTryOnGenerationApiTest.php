<?php

namespace Tests\Feature;

use App\Contracts\FashionTryOnProvider;
use App\Enums\CreditTransactionType;
use App\Jobs\GenerateFashionTryOnJob;
use App\Models\CreatorTool;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\GenerationJobProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class FashionTryOnGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_fashion_try_on_project_can_be_created(): void
    {
        $user = User::factory()->create();
        $payload = $this->fashionPayload();

        $this->actingAs($user)
            ->postJson('/api/v1/projects', [
                'title' => $payload['title'],
                'type' => 'fashion_try_on',
                'status' => 'draft',
                'input_payload' => $payload,
                'settings' => ['platform' => 'tiktok', 'format' => 'vertical_9_16'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'fashion_try_on')
            ->assertJsonPath('data.input_payload.garment_type', 'jacket')
            ->assertJsonPath('data.input_payload.output_style', 'realistic');
    }

    public function test_fashion_try_on_tool_appears_in_public_tools_when_available(): void
    {
        $response = $this->getJson('/api/v1/tools')->assertOk();
        $tool = collect($response->json('data'))->firstWhere('key', 'fashion_try_on');

        $this->assertIsArray($tool);
        $this->assertSame('available', $tool['status']);
        $this->assertSame('/fashion-try-on/new', $tool['route']);
        $this->assertSame(2, $tool['credit_cost']);
        $this->assertSame('New mock', $tool['badge_label']);
    }

    public function test_generation_endpoint_requires_authentication(): void
    {
        $project = $this->fashionProject(User::factory()->create());

        $this->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on")
            ->assertUnauthorized();
    }

    public function test_user_cannot_generate_another_users_project(): void
    {
        $owner = User::factory()->create();
        $project = $this->fashionProject($owner);
        $this->attachRequiredAssets($project, $owner);

        $this->actingAs(User::factory()->create())
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on")
            ->assertNotFound();

        $this->assertDatabaseCount('generations', 0);
    }

    public function test_wrong_project_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['type' => 'affiliate_campaign']);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_project_type');

        $this->assertDatabaseCount('generations', 0);
    }

    public function test_unavailable_tool_blocks_generation(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->fashionProject($user);
        $this->attachRequiredAssets($project, $user);
        CreatorTool::query()->where('key', 'fashion_try_on')->update(['status' => 'maintenance']);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'tool_unavailable');

        Queue::assertNothingPushed();
        $this->assertSame(5, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_dynamic_credit_cost_is_charged(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 8]);
        $project = $this->fashionProject($user);
        $this->attachRequiredAssets($project, $user);
        CreatorTool::query()->where('key', 'fashion_try_on')->update(['credit_cost' => 4]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on")
            ->assertCreated()
            ->assertJsonPath('data.job_type', 'generate_fashion_try_on')
            ->assertJsonPath('data.credits_charged', 4)
            ->assertJsonPath('meta.credits_balance', 4);

        $transaction = CreditTransaction::query()
            ->where('generation_id', $response->json('data.id'))
            ->firstOrFail();

        $this->assertSame(CreditTransactionType::Usage, $transaction->type);
        $this->assertSame(4, $transaction->amount);
        $this->assertSame(4, $transaction->balance_after);
        $this->assertSame('Mock Fashion Try-On generation', $transaction->reason);
    }

    public function test_insufficient_credits_block_dispatch_without_charge(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 1]);
        $project = $this->fashionProject($user);
        $this->attachRequiredAssets($project, $user);
        CreatorTool::query()->where('key', 'fashion_try_on')->update(['credit_cost' => 2]);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on")
            ->assertPaymentRequired()
            ->assertJsonPath('code', 'insufficient_credits')
            ->assertJsonPath('available_credits', 1)
            ->assertJsonPath('required_credits', 2);

        Queue::assertNothingPushed();
        $this->assertSame(1, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_unlimited_user_is_charged_zero(): void
    {
        Queue::fake();
        $user = User::factory()->internalCreator()->create(['credits_balance' => 0]);
        $project = $this->fashionProject($user);
        $this->attachRequiredAssets($project, $user);
        CreatorTool::query()->where('key', 'fashion_try_on')->update(['credit_cost' => 4]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on")
            ->assertCreated()
            ->assertJsonPath('data.credits_charged', 0)
            ->assertJsonPath('meta.credits_balance', 0);

        $transaction = CreditTransaction::query()
            ->where('generation_id', $response->json('data.id'))
            ->firstOrFail();

        $this->assertSame(0, $transaction->amount);
        $this->assertSame(0, $transaction->balance_after);
        $this->assertTrue($transaction->metadata['unlimited_access']);
    }

    public function test_generation_is_blocked_when_required_assets_are_missing_or_not_images(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->fashionProject($user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'missing_reference_assets')
            ->assertJsonPath('message', 'Please upload a model reference image and a garment reference image before generating a try-on.');

        MediaAsset::factory()->for($user)->for($project)->create(['usage_role' => 'model_reference', 'type' => 'image']);

        $this->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'missing_reference_assets');

        MediaAsset::factory()->for($user)->for($project)->create([
            'usage_role' => 'garment_reference',
            'type' => 'document',
            'mime_type' => 'application/pdf',
        ]);

        $this->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'missing_reference_assets');

        Queue::assertNothingPushed();
        $this->assertSame(5, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_queued_job_completes_and_stores_output_payload(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->fashionProject($user, 'spanish');
        $this->attachRequiredAssets($project, $user);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on")
            ->assertCreated()
            ->assertJsonPath('data.status', Generation::STATUS_QUEUED)
            ->assertJsonPath('data.output_payload', null)
            ->json('data.id');

        Queue::assertPushed(
            GenerateFashionTryOnJob::class,
            fn (GenerateFashionTryOnJob $job): bool => $job->generationId === $generationId,
        );

        $job = new GenerateFashionTryOnJob($generationId);
        app()->call([$job, 'handle']);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertArrayHasKey('try_on_title', $generation->output_payload);
        $this->assertArrayHasKey('generated_image', $generation->output_payload);
        $this->assertSame('placeholder', $generation->output_payload['generated_image']['status']);
        $this->assertStringContainsString('probador virtual', $generation->output_payload['concept_summary']);
    }

    public function test_generation_history_and_detail_return_fashion_try_on_output(): void
    {
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->fashionProject($user);
        $this->attachRequiredAssets($project, $user);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on")
            ->assertCreated()
            ->json('data.id');

        $this->getJson("/api/v1/projects/{$project->id}/generations")
            ->assertOk()
            ->assertJsonPath('data.0.id', $generationId)
            ->assertJsonPath('data.0.job_type', 'generate_fashion_try_on')
            ->assertJsonPath('data.0.output_payload.generated_image.provider', 'mock');

        $this->getJson("/api/v1/generations/{$generationId}")
            ->assertOk()
            ->assertJsonPath('data.id', $generationId)
            ->assertJsonPath('data.output_payload.generated_image.message', 'Real try-on image generation will be connected later.')
            ->assertJsonPath('data.result_payload.generated_image.provider', 'mock');
    }

    public function test_failed_job_uses_safe_error_and_refunds_charge_once(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 3]);
        $project = $this->fashionProject($user);
        $this->attachRequiredAssets($project, $user);
        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on")
            ->assertCreated()
            ->json('data.id');

        $this->app->bind(FashionTryOnProvider::class, fn () => new class implements FashionTryOnProvider
        {
            public function generate(array $input): array
            {
                throw new RuntimeException('SQLSTATE leaked path /var/www/provider.php:99');
            }
        });

        $job = new GenerateFashionTryOnJob($generationId);
        $exception = null;

        try {
            $job->handle(app(FashionTryOnProvider::class), app(GenerationJobProcessor::class));
        } catch (RuntimeException $caught) {
            $exception = $caught;
            $job->failed($caught);
        }

        $this->assertNotNull($exception);
        $job->failed($exception);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame('Generation failed due to an internal processing error.', $generation->error_message);
        $this->assertStringNotContainsString('SQLSTATE', $generation->error_message);
        $this->assertSame(3, $user->refresh()->credits_balance);
        $this->assertSame(1, CreditTransaction::query()
            ->where('generation_id', $generationId)
            ->where('type', CreditTransactionType::Refund->value)
            ->count());
    }

    /** @return array<string, mixed> */
    private function fashionPayload(string $language = 'english'): array
    {
        return [
            'title' => 'Streetwear jacket try-on',
            'product_name' => 'Everyday creator jacket',
            'product_description' => 'A lightweight jacket for everyday content shoots.',
            'garment_type' => 'jacket',
            'target_audience' => 'Fashion affiliate creators',
            'style_goal' => 'product_showcase',
            'output_style' => 'realistic',
            'model_notes' => 'Use a relaxed standing pose.',
            'garment_notes' => 'Keep the zipper and collar visible.',
            'background_preference' => 'clean studio wall',
            'language' => $language,
            'platform' => 'tiktok',
            'notes' => 'Keep it safe and generic.',
        ];
    }

    private function fashionProject(User $user, string $language = 'english'): Project
    {
        return Project::factory()->for($user)->create([
            'title' => 'Streetwear jacket try-on',
            'type' => 'fashion_try_on',
            'status' => 'draft',
            'input_payload' => $this->fashionPayload($language),
            'settings' => ['platform' => 'tiktok', 'format' => 'vertical_9_16'],
        ]);
    }

    private function attachRequiredAssets(Project $project, User $user): void
    {
        MediaAsset::factory()->for($user)->for($project)->create([
            'usage_role' => 'model_reference',
            'type' => 'image',
            'original_name' => 'model-reference.jpg',
            'mime_type' => 'image/jpeg',
        ]);
        MediaAsset::factory()->for($user)->for($project)->create([
            'usage_role' => 'garment_reference',
            'type' => 'image',
            'original_name' => 'garment-reference.jpg',
            'mime_type' => 'image/jpeg',
        ]);
    }
}
