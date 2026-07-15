<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Jobs\GenerateAutoVideoAdJob;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutoVideoAdGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_generate_auto_video_ad(): void
    {
        $project = $this->workspaceProject(User::factory()->create());

        $this->postJson("/api/v1/projects/{$project->id}/generate-auto-video-ad", $this->validPayload())
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_generate_image_based_auto_video_ad_plan(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 20]);
        $project = $this->workspaceProject($user);
        $image = $this->imageAsset($user, $project);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-ad", $this->validPayload([
                'product_image_asset_id' => $image->id,
                'source_video_asset_id' => null,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.job_type', 'generate_auto_video_ad')
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonPath('data.status', Generation::STATUS_QUEUED)
            ->assertJsonPath('data.credits_charged', 12)
            ->assertJsonPath('data.input_payload.visual_source_type', 'image')
            ->assertJsonPath('data.input_payload.product_image_asset_id', $image->id)
            ->assertJsonPath('meta.credits_balance', 8)
            ->json('data.id');

        Queue::assertPushed(
            GenerateAutoVideoAdJob::class,
            fn (GenerateAutoVideoAdJob $job): bool => $job->generationId === $generationId,
        );

        $this->assertSame(0, MediaAsset::query()->where('type', 'video')->count());

        app()->call([new GenerateAutoVideoAdJob($generationId), 'handle']);
        $generation = Generation::query()->findOrFail($generationId);

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame('image', $generation->output_payload['ad_summary']['visual_source_type']);
        $this->assertFalse($generation->output_payload['metadata']['render_available']);
        $this->assertFalse($generation->output_payload['metadata']['shotstack_render_pending']);
        $this->assertArrayHasKey('ad_summary', $generation->output_payload);
        $this->assertArrayHasKey('storyboard', $generation->output_payload);
        $this->assertArrayHasKey('overlay_plan', $generation->output_payload);
        $this->assertArrayHasKey('render_plan', $generation->output_payload);
        $this->assertSame(0, MediaAsset::query()->where('type', 'video')->count());

        $transaction = CreditTransaction::query()->where('generation_id', $generationId)->firstOrFail();
        $this->assertSame(CreditTransactionType::Usage, $transaction->type);
        $this->assertSame(12, $transaction->amount);
        $this->assertSame(8, $transaction->balance_after);
    }

    public function test_authenticated_user_can_generate_video_based_auto_video_ad_plan(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 20]);
        $project = $this->workspaceProject($user);
        $video = $this->videoAsset($user, $project);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-ad", $this->validPayload([
                'product_image_asset_id' => null,
                'source_video_asset_id' => $video->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.input_payload.visual_source_type', 'video')
            ->assertJsonPath('data.input_payload.source_video_asset_id', $video->id)
            ->json('data.id');

        app()->call([new GenerateAutoVideoAdJob($generationId), 'handle']);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame('video', $generation->output_payload['ad_summary']['visual_source_type']);
        $this->assertSame('product_video', $generation->output_payload['storyboard'][0]['asset_role']);
    }

    public function test_authenticated_user_can_generate_hybrid_auto_video_ad_plan(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 20]);
        $project = $this->workspaceProject($user);
        $image = $this->imageAsset($user, $project);
        $video = $this->videoAsset($user, $project);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-ad", $this->validPayload([
                'product_image_asset_id' => $image->id,
                'source_video_asset_id' => $video->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.input_payload.visual_source_type', 'hybrid')
            ->json('data.id');

        app()->call([new GenerateAutoVideoAdJob($generationId), 'handle']);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame('hybrid', $generation->output_payload['metadata']['visual_source_type']);
        $this->assertContains('Product image for end card', $generation->output_payload['render_plan']['recommended_assets']);
        $this->assertContains('Source video for demo clips', $generation->output_payload['render_plan']['recommended_assets']);
    }

    public function test_auto_video_ad_requires_at_least_one_visual_asset(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 20]);
        $project = $this->workspaceProject($user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-ad", $this->validPayload([
                'product_image_asset_id' => null,
                'source_video_asset_id' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_image_asset_id', 'source_video_asset_id']);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_auto_video_ad_rejects_wrong_asset_types_and_other_users_assets(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 20]);
        $project = $this->workspaceProject($user);
        $otherUser = User::factory()->create();
        $otherProject = $this->workspaceProject($otherUser);
        $video = $this->videoAsset($user, $project);
        $image = $this->imageAsset($user, $project);
        $otherImage = $this->imageAsset($otherUser, $otherProject);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-ad", $this->validPayload([
                'product_image_asset_id' => $video->id,
                'source_video_asset_id' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_image_asset_id');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-ad", $this->validPayload([
                'product_image_asset_id' => null,
                'source_video_asset_id' => $image->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_video_asset_id');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-ad", $this->validPayload([
                'product_image_asset_id' => $otherImage->id,
                'source_video_asset_id' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_image_asset_id');

        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('generations', [
            'job_type' => 'generate_auto_video_ad',
        ]);
    }

    public function test_auto_video_ad_accepts_campaign_and_voiceover_sources(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 20]);
        $project = $this->workspaceProject($user);
        $image = $this->imageAsset($user, $project);
        $campaign = Generation::factory()->for($user)->for($project)->create([
            'job_type' => 'generate_affiliate_campaign',
            'status' => Generation::STATUS_COMPLETED,
            'output_payload' => [
                'campaign_title' => 'Desk light campaign',
                'campaign_angle' => 'Show how the light improves creator desk setups.',
            ],
        ]);
        $voiceover = Generation::factory()->for($user)->for($project)->create([
            'job_type' => 'generate_ai_voiceover',
            'status' => Generation::STATUS_COMPLETED,
            'output_payload' => [
                'voiceover_title' => 'Creator desk light voiceover',
                'narration_script' => 'Show the light in a real creator setup.',
            ],
        ]);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-ad", $this->validPayload([
                'product_image_asset_id' => $image->id,
                'campaign_generation_id' => $campaign->id,
                'voiceover_generation_id' => $voiceover->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.input_payload.campaign_generation_id', $campaign->id)
            ->assertJsonPath('data.input_payload.voiceover_generation_id', $voiceover->id)
            ->assertJsonPath('data.input_payload.voiceover_ready', true)
            ->json('data.id');

        app()->call([new GenerateAutoVideoAdJob($generationId), 'handle']);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame($campaign->id, $generation->output_payload['metadata']['campaign_generation_id']);
        $this->assertSame($voiceover->id, $generation->output_payload['metadata']['voiceover_generation_id']);
        $this->assertTrue($generation->output_payload['metadata']['voiceover_ready']);
        $this->assertStringContainsString('creator desk setups', $generation->output_payload['ad_summary']['creative_angle']);
    }

    public function test_auto_video_ad_rejects_insufficient_credits(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 1]);
        $project = $this->workspaceProject($user);
        $image = $this->imageAsset($user, $project);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-ad", $this->validPayload([
                'product_image_asset_id' => $image->id,
            ]))
            ->assertPaymentRequired()
            ->assertJsonPath('code', 'insufficient_credits')
            ->assertJsonPath('available_credits', 1)
            ->assertJsonPath('required_credits', 12);

        Queue::assertNothingPushed();
        $this->assertSame(1, $user->refresh()->credits_balance);
        $this->assertDatabaseMissing('generations', [
            'job_type' => 'generate_auto_video_ad',
        ]);
    }

    public function test_auto_video_ad_generation_history_still_returns_output_payload(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 20]);
        $project = $this->workspaceProject($user);
        $image = $this->imageAsset($user, $project);
        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-ad", $this->validPayload([
                'product_image_asset_id' => $image->id,
            ]))
            ->assertCreated()
            ->json('data.id');

        app()->call([new GenerateAutoVideoAdJob($generationId), 'handle']);

        $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/generations")
            ->assertOk()
            ->assertJsonPath('data.0.job_type', 'generate_auto_video_ad')
            ->assertJsonPath('data.0.output_payload.metadata.render_available', false)
            ->assertJsonPath('data.0.result_payload.metadata.render_available', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'ad_format' => 'vertical_9_16',
            'platform' => 'tiktok_ads',
            'ad_goal' => 'sales',
            'product_image_asset_id' => null,
            'source_video_asset_id' => null,
            'target_duration_seconds' => 15,
            'primary_cta' => 'See product details',
            'offer_text' => null,
            'landing_page_context' => 'Product detail page with factual benefits.',
            'audience_override' => null,
            'tone' => 'direct_response',
            'visual_style' => 'clean_product',
            'hook_style' => 'benefit_first',
            'include_voiceover_script' => true,
            'include_caption_variants' => true,
        ], $overrides);
    }

    private function workspaceProject(User $user): Project
    {
        return Project::factory()->for($user)->create([
            'title' => 'Creator desk light',
            'type' => 'workspace',
            'status' => 'draft',
            'input_payload' => [
                'product_name' => 'Creator desk light',
                'product_description' => 'A compact light for creator desks.',
                'target_audience' => 'TikTok affiliate creators',
                'language' => 'english',
                'tone' => 'natural',
            ],
            'settings' => ['platform' => 'tiktok', 'format' => 'vertical_9_16'],
        ]);
    }

    private function imageAsset(User $user, Project $project): MediaAsset
    {
        return MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'image',
            'usage_role' => 'product_image',
            'original_name' => 'creator-desk-light.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 500_000,
        ]);
    }

    private function videoAsset(User $user, Project $project): MediaAsset
    {
        return MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'video',
            'usage_role' => 'campaign_source_video',
            'original_name' => 'creator-desk-light-demo.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1_500_000,
        ]);
    }
}
