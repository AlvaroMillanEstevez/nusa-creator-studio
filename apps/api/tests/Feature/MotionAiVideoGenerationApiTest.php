<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Jobs\GenerateMotionAiVideoJob;
use App\Models\CreatorTool;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MotionAiVideoGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CreatorTool::query()->where('key', 'motion_ai_video')->update([
            'status' => 'available',
            'implementation_stage' => 'mock',
            'credit_cost' => 3,
            'credit_cost_label' => '3 credits',
            'badge_label' => 'Mock beta',
        ]);
    }

    public function test_motion_ai_video_tool_appears_in_public_tools_when_available(): void
    {
        $response = $this->getJson('/api/v1/tools')->assertOk();
        $tool = collect($response->json('data'))->firstWhere('key', 'motion_ai_video');

        $this->assertIsArray($tool);
        $this->assertSame('available', $tool['status']);
        $this->assertSame('mock', $tool['implementation_stage']);
        $this->assertSame(3, $tool['credit_cost']);
        $this->assertSame('Mock beta', $tool['badge_label']);
        $this->assertStringContainsString('motion-video concepts', $tool['short_description']);
    }

    public function test_guest_cannot_generate_motion_ai_video(): void
    {
        $project = $this->workspaceProject(User::factory()->create());

        $this->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload())
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_generate_with_product_image_only(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        $image = $this->imageAsset($user, $project, 'product_image', 'product.jpg');

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'product_image_asset_id' => $image->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.job_type', 'generate_motion_ai_video')
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonPath('data.status', Generation::STATUS_QUEUED)
            ->assertJsonPath('data.credits_charged', 3)
            ->assertJsonPath('data.input_payload.product_image_asset_id', $image->id)
            ->assertJsonPath('data.input_payload.platform', 'tiktok')
            ->assertJsonPath('meta.credits_balance', 7)
            ->json('data.id');

        Queue::assertPushed(
            GenerateMotionAiVideoJob::class,
            fn (GenerateMotionAiVideoJob $job): bool => $job->generationId === $generationId,
        );

        app()->call([new GenerateMotionAiVideoJob($generationId), 'handle']);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertArrayHasKey('motion_summary', $generation->output_payload);
        $this->assertArrayHasKey('generation_prompt', $generation->output_payload);
        $this->assertArrayHasKey('scene_plan', $generation->output_payload);
        $this->assertArrayHasKey('provider_readiness', $generation->output_payload);
        $this->assertFalse($generation->output_payload['motion_summary']['render_available']);
        $this->assertFalse($generation->output_payload['provider_readiness']['render_available']);
        $this->assertFalse($generation->output_payload['metadata']['render_available']);
        $this->assertFalse($generation->output_payload['metadata']['real_provider_enabled']);
        $this->assertSame('product_image_only', $generation->output_payload['motion_summary']['source_type']);
        $this->assertSame(0, MediaAsset::query()->where('type', 'video')->count());

        $transaction = CreditTransaction::query()->where('generation_id', $generationId)->firstOrFail();
        $this->assertSame(CreditTransactionType::Usage, $transaction->type);
        $this->assertSame(3, $transaction->amount);
        $this->assertSame(7, $transaction->balance_after);
        $this->assertSame('Mock Motion AI Video plan generation', $transaction->reason);
    }

    public function test_authenticated_user_can_generate_with_product_and_scenario_image(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        $productImage = $this->imageAsset($user, $project, 'product_image', 'product.jpg');
        $scenarioImage = $this->imageAsset($user, $project, 'background_reference', 'countertop.jpg');

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'product_image_asset_id' => $productImage->id,
                'scenario_image_asset_id' => $scenarioImage->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.input_payload.scenario_image_asset_id', $scenarioImage->id)
            ->json('data.id');

        app()->call([new GenerateMotionAiVideoJob($generationId), 'handle']);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame('product_image_with_scenario', $generation->output_payload['motion_summary']['source_type']);
        $this->assertTrue($generation->output_payload['motion_settings']['scenario_used']);
    }

    public function test_accepts_optional_campaign_and_voiceover_generation_sources(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        $image = $this->imageAsset($user, $project, 'product_image', 'product.jpg');
        $campaign = Generation::factory()->for($user)->for($project)->create([
            'job_type' => 'generate_affiliate_campaign',
            'status' => Generation::STATUS_COMPLETED,
            'output_payload' => [
                'campaign_angle' => 'Show how the bottle fits fast creator routines.',
                'voiceover_ready_script' => 'Show the bottle in a morning routine.',
                'cta' => 'Check the product details',
            ],
        ]);
        $voiceover = Generation::factory()->for($user)->for($project)->create([
            'job_type' => 'generate_ai_voiceover',
            'status' => Generation::STATUS_COMPLETED,
            'output_payload' => [
                'voiceover_title' => 'Bottle voiceover',
                'narration_script' => 'Keep hydration visible in your daily setup.',
            ],
        ]);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'product_image_asset_id' => $image->id,
                'campaign_generation_id' => $campaign->id,
                'voiceover_generation_id' => $voiceover->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.input_payload.script_source', 'campaign_script')
            ->assertJsonPath('data.input_payload.campaign_generation_id', $campaign->id)
            ->assertJsonPath('data.input_payload.voiceover_generation_id', $voiceover->id)
            ->json('data.id');

        app()->call([new GenerateMotionAiVideoJob($generationId), 'handle']);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame('voiceover_assisted', $generation->output_payload['motion_summary']['source_type']);
        $this->assertSame($campaign->id, $generation->output_payload['metadata']['campaign_generation_id']);
        $this->assertSame($voiceover->id, $generation->output_payload['metadata']['voiceover_generation_id']);
        $this->assertTrue($generation->output_payload['metadata']['voiceover_assisted']);
        $this->assertStringContainsString('hydration visible', $generation->output_payload['script_plan']['voiceover_ready_script']);
    }

    public function test_motion_ai_video_requires_product_image(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'product_image_asset_id' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_image_asset_id');

        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('generations', ['job_type' => 'generate_motion_ai_video']);
    }

    public function test_motion_ai_video_rejects_non_image_assets_and_other_users_assets(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        $otherUser = User::factory()->create();
        $otherProject = $this->workspaceProject($otherUser);
        $video = $this->videoAsset($user, $project);
        $productImage = $this->imageAsset($user, $project, 'product_image', 'product.jpg');
        $otherImage = $this->imageAsset($otherUser, $otherProject, 'product_image', 'other.jpg');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'product_image_asset_id' => $video->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_image_asset_id');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'product_image_asset_id' => $productImage->id,
                'scenario_image_asset_id' => $video->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scenario_image_asset_id');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'product_image_asset_id' => $otherImage->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_image_asset_id');

        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('generations', ['job_type' => 'generate_motion_ai_video']);
    }

    public function test_manual_script_source_requires_manual_script(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        $image = $this->imageAsset($user, $project, 'product_image', 'product.jpg');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'product_image_asset_id' => $image->id,
                'script_source' => 'manual_script',
                'manual_script' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('manual_script');

        Queue::assertNothingPushed();
    }

    public function test_insufficient_credits_block_dispatch_without_charge(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 2]);
        $project = $this->workspaceProject($user);
        $image = $this->imageAsset($user, $project, 'product_image', 'product.jpg');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'product_image_asset_id' => $image->id,
            ]))
            ->assertPaymentRequired()
            ->assertJsonPath('code', 'insufficient_credits')
            ->assertJsonPath('available_credits', 2)
            ->assertJsonPath('required_credits', 3);

        Queue::assertNothingPushed();
        $this->assertSame(2, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('credit_transactions', 0);
        $this->assertDatabaseMissing('generations', ['job_type' => 'generate_motion_ai_video']);
    }

    public function test_generation_history_returns_motion_ai_video_plan_output(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        $image = $this->imageAsset($user, $project, 'product_image', 'product.jpg');
        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'product_image_asset_id' => $image->id,
            ]))
            ->assertCreated()
            ->json('data.id');

        app()->call([new GenerateMotionAiVideoJob($generationId), 'handle']);

        $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/generations")
            ->assertOk()
            ->assertJsonPath('data.0.id', $generationId)
            ->assertJsonPath('data.0.job_type', 'generate_motion_ai_video')
            ->assertJsonPath('data.0.output_payload.provider_readiness.provider', 'mock')
            ->assertJsonPath('data.0.result_payload.motion_summary.render_available', false);
    }

    public function test_legacy_motion_video_route_still_uses_canonical_generator(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        $image = $this->imageAsset($user, $project, 'product_image', 'product.jpg');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-video", $this->validPayload([
                'product_image_asset_id' => $image->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.job_type', 'generate_motion_ai_video');
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'product_image_asset_id' => null,
            'scenario_image_asset_id' => null,
            'campaign_generation_id' => null,
            'voiceover_generation_id' => null,
            'platform' => 'tiktok',
            'target_duration_seconds' => 8,
            'manual_script' => null,
            'visual_style' => 'realistic_product',
            'motion_style' => 'slow_push_in',
            'camera_direction' => 'Slow push-in with stable framing.',
            'scene_description' => 'Clean creator desk setup.',
            'background_prompt' => 'Warm desk scene with neutral background.',
            'negative_prompt' => 'warped product, fake logos, exaggerated claims',
            'aspect_ratio' => 'vertical_9_16',
            'include_voiceover_plan' => true,
            'include_caption_plan' => true,
            'primary_cta' => 'See product details',
        ], $overrides);
    }

    private function workspaceProject(User $user): Project
    {
        return Project::factory()->for($user)->create([
            'title' => 'Hydration bottle workspace',
            'type' => 'workspace',
            'status' => 'draft',
            'input_payload' => [
                'product_name' => 'Hydration bottle',
                'product_description' => 'A simple bottle for daily creator desk routines.',
                'target_audience' => 'TikTok affiliate creators',
                'language' => 'english',
                'tone' => 'natural',
                'cta_preference' => 'See product details',
            ],
            'settings' => ['platform' => 'tiktok', 'format' => 'vertical_9_16'],
        ]);
    }

    private function imageAsset(User $user, Project $project, string $usageRole, string $name): MediaAsset
    {
        return MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'image',
            'usage_role' => $usageRole,
            'original_name' => $name,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 500_000,
        ]);
    }

    private function videoAsset(User $user, Project $project): MediaAsset
    {
        return MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'video',
            'usage_role' => 'source_video',
            'original_name' => 'source.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1_500_000,
        ]);
    }
}
