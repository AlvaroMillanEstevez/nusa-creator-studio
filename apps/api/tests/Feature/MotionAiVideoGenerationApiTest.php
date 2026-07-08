<?php

namespace Tests\Feature;

use App\Contracts\MotionAiVideoProvider;
use App\Enums\CreditTransactionType;
use App\Jobs\GenerateMotionAiVideoJob;
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

class MotionAiVideoGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_motion_ai_video_project_can_be_created(): void
    {
        $user = User::factory()->create();
        $payload = $this->motionPayload();

        $this->actingAs($user)
            ->postJson('/api/v1/projects', [
                'title' => $payload['title'],
                'type' => 'motion_ai_video',
                'status' => 'draft',
                'input_payload' => $payload,
                'settings' => ['platform' => 'tiktok', 'format' => 'vertical_9_16'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'motion_ai_video')
            ->assertJsonPath('data.input_payload.video_goal', 'product_showcase')
            ->assertJsonPath('data.input_payload.motion_style', 'smooth_camera');
    }

    public function test_motion_ai_video_tool_appears_in_public_tools_when_available(): void
    {
        $response = $this->getJson('/api/v1/tools')->assertOk();
        $tool = collect($response->json('data'))->firstWhere('key', 'motion_ai_video');

        $this->assertIsArray($tool);
        $this->assertSame('available', $tool['status']);
        $this->assertSame('/motion-video/new', $tool['route']);
        $this->assertSame(3, $tool['credit_cost']);
        $this->assertSame('New mock', $tool['badge_label']);
    }

    public function test_generation_endpoint_requires_authentication(): void
    {
        $project = $this->motionProject(User::factory()->create());

        $this->postJson("/api/v1/projects/{$project->id}/generate-motion-video")
            ->assertUnauthorized();
    }

    public function test_user_cannot_generate_another_users_project(): void
    {
        $owner = User::factory()->create();
        $project = $this->motionProject($owner);
        $this->attachVisualAsset($project, $owner, 'product_image', 'image', 'product.jpg', 'image/jpeg');

        $this->actingAs(User::factory()->create())
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-video")
            ->assertNotFound();

        $this->assertDatabaseCount('generations', 0);
    }

    public function test_wrong_project_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['type' => 'affiliate_campaign']);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-video")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_project_type');

        $this->assertDatabaseCount('generations', 0);
    }

    public function test_unavailable_tool_blocks_generation(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->motionProject($user);
        $this->attachVisualAsset($project, $user, 'product_image', 'image', 'product.jpg', 'image/jpeg');
        CreatorTool::query()->where('key', 'motion_ai_video')->update(['status' => 'maintenance']);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-video")
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
        $project = $this->motionProject($user);
        $this->attachVisualAsset($project, $user, 'product_image', 'image', 'product.jpg', 'image/jpeg');
        CreatorTool::query()->where('key', 'motion_ai_video')->update(['credit_cost' => 4]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-video")
            ->assertCreated()
            ->assertJsonPath('data.job_type', 'generate_motion_ai_video')
            ->assertJsonPath('data.credits_charged', 4)
            ->assertJsonPath('meta.credits_balance', 4);

        $transaction = CreditTransaction::query()
            ->where('generation_id', $response->json('data.id'))
            ->firstOrFail();

        $this->assertSame(CreditTransactionType::Usage, $transaction->type);
        $this->assertSame(4, $transaction->amount);
        $this->assertSame(4, $transaction->balance_after);
        $this->assertSame('Mock Motion AI Video generation', $transaction->reason);
    }

    public function test_insufficient_credits_block_dispatch_without_charge(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 2]);
        $project = $this->motionProject($user);
        $this->attachVisualAsset($project, $user, 'product_image', 'image', 'product.jpg', 'image/jpeg');
        CreatorTool::query()->where('key', 'motion_ai_video')->update(['credit_cost' => 3]);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-video")
            ->assertPaymentRequired()
            ->assertJsonPath('code', 'insufficient_credits')
            ->assertJsonPath('available_credits', 2)
            ->assertJsonPath('required_credits', 3);

        Queue::assertNothingPushed();
        $this->assertSame(2, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_unlimited_user_is_charged_zero(): void
    {
        Queue::fake();
        $user = User::factory()->internalCreator()->create(['credits_balance' => 0]);
        $project = $this->motionProject($user);
        $this->attachVisualAsset($project, $user, 'product_image', 'image', 'product.jpg', 'image/jpeg');
        CreatorTool::query()->where('key', 'motion_ai_video')->update(['credit_cost' => 4]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-video")
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

    public function test_generation_is_blocked_when_no_visual_reference_asset_exists(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->motionProject($user);

        MediaAsset::factory()->for($user)->for($project)->create([
            'usage_role' => 'audio_track',
            'type' => 'audio',
            'mime_type' => 'audio/mpeg',
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-video")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'missing_visual_reference_asset')
            ->assertJsonPath('message', 'Please upload at least one product image, source video, background reference or brand reference before generating a motion video.');

        Queue::assertNothingPushed();
        $this->assertSame(5, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_generation_is_allowed_with_product_image_source_video_or_background_reference(): void
    {
        Queue::fake();

        foreach ([
            ['product_image', 'image', 'product.jpg', 'image/jpeg'],
            ['source_video', 'video', 'clip.mp4', 'video/mp4'],
            ['background_reference', 'image', 'background.png', 'image/png'],
        ] as [$role, $type, $name, $mime]) {
            $user = User::factory()->create(['credits_balance' => 5]);
            $project = $this->motionProject($user);
            $this->attachVisualAsset($project, $user, $role, $type, $name, $mime);

            $this->actingAs($user)
                ->postJson("/api/v1/projects/{$project->id}/generate-motion-video")
                ->assertCreated()
                ->assertJsonPath('data.job_type', 'generate_motion_ai_video')
                ->assertJsonPath('data.input_payload.assets.visual_references.0.usage_role', $role);
        }

        Queue::assertPushed(GenerateMotionAiVideoJob::class, 3);
        $this->assertDatabaseCount('generations', 3);
    }

    public function test_queued_job_completes_and_stores_output_payload(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->motionProject($user, 'spanish');
        $this->attachVisualAsset($project, $user, 'product_image', 'image', 'producto.jpg', 'image/jpeg');

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-video")
            ->assertCreated()
            ->assertJsonPath('data.status', Generation::STATUS_QUEUED)
            ->assertJsonPath('data.output_payload', null)
            ->json('data.id');

        Queue::assertPushed(
            GenerateMotionAiVideoJob::class,
            fn (GenerateMotionAiVideoJob $job): bool => $job->generationId === $generationId,
        );

        $job = new GenerateMotionAiVideoJob($generationId);
        app()->call([$job, 'handle']);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertArrayHasKey('video_title', $generation->output_payload);
        $this->assertArrayHasKey('generated_video', $generation->output_payload);
        $this->assertArrayHasKey('scene_plan', $generation->output_payload);
        $this->assertSame('placeholder', $generation->output_payload['generated_video']['status']);
        $this->assertStringContainsString('vídeo motion', $generation->output_payload['concept_summary']);
    }

    public function test_generation_history_and_detail_return_motion_ai_video_output(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->motionProject($user);
        $this->attachVisualAsset($project, $user, 'product_image', 'image', 'product.jpg', 'image/jpeg');

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-video")
            ->assertCreated()
            ->json('data.id');

        app()->call([new GenerateMotionAiVideoJob($generationId), 'handle']);

        $this->getJson("/api/v1/projects/{$project->id}/generations")
            ->assertOk()
            ->assertJsonPath('data.0.id', $generationId)
            ->assertJsonPath('data.0.job_type', 'generate_motion_ai_video')
            ->assertJsonPath('data.0.output_payload.generated_video.provider', 'mock');

        $this->getJson("/api/v1/generations/{$generationId}")
            ->assertOk()
            ->assertJsonPath('data.id', $generationId)
            ->assertJsonPath('data.output_payload.generated_video.message', 'Real AI video generation will be connected later.')
            ->assertJsonPath('data.result_payload.generated_video.provider', 'mock');
    }

    public function test_failed_job_uses_safe_error_and_refunds_charge_once(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->motionProject($user);
        $this->attachVisualAsset($project, $user, 'product_image', 'image', 'product.jpg', 'image/jpeg');
        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-video")
            ->assertCreated()
            ->json('data.id');

        $this->app->bind(MotionAiVideoProvider::class, fn () => new class implements MotionAiVideoProvider
        {
            public function generate(array $input): array
            {
                throw new RuntimeException('Provider stack trace /var/www/video.php:123');
            }
        });

        $job = new GenerateMotionAiVideoJob($generationId);
        $exception = null;

        try {
            $job->handle(app(MotionAiVideoProvider::class), app(GenerationJobProcessor::class));
        } catch (RuntimeException $caught) {
            $exception = $caught;
            $job->failed($caught);
        }

        $this->assertNotNull($exception);
        $job->failed($exception);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame('Generation failed due to an internal processing error.', $generation->error_message);
        $this->assertStringNotContainsString('/var/www/video.php', $generation->error_message);
        $this->assertSame(5, $user->refresh()->credits_balance);
        $this->assertSame(1, CreditTransaction::query()
            ->where('generation_id', $generationId)
            ->where('type', CreditTransactionType::Refund->value)
            ->count());
    }

    /** @return array<string, mixed> */
    private function motionPayload(string $language = 'english'): array
    {
        return [
            'title' => 'Portable blender motion video',
            'product_name' => 'Portable smoothie blender',
            'product_description' => 'A compact blender for quick creator product demos.',
            'video_goal' => 'product_showcase',
            'motion_style' => 'smooth_camera',
            'visual_style' => 'ecommerce_clean',
            'duration_seconds' => 15,
            'aspect_ratio' => 'vertical_9_16',
            'target_audience' => 'TikTok affiliate creators',
            'platform' => 'tiktok',
            'language' => $language,
            'brand_notes' => 'Keep it clean and premium.',
            'scene_notes' => 'Open with the product and show safe details.',
            'audio_notes' => 'Use upbeat pacing later.',
            'notes' => 'No unrealistic claims.',
        ];
    }

    private function motionProject(User $user, string $language = 'english'): Project
    {
        return Project::factory()->for($user)->create([
            'title' => 'Portable blender motion video',
            'type' => 'motion_ai_video',
            'status' => 'draft',
            'input_payload' => $this->motionPayload($language),
            'settings' => ['platform' => 'tiktok', 'format' => 'vertical_9_16'],
        ]);
    }

    private function attachVisualAsset(
        Project $project,
        User $user,
        string $usageRole,
        string $type,
        string $name,
        string $mimeType,
    ): void {
        MediaAsset::factory()->for($user)->for($project)->create([
            'usage_role' => $usageRole,
            'type' => $type,
            'original_name' => $name,
            'mime_type' => $mimeType,
        ]);
    }
}
