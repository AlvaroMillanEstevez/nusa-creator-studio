<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Jobs\GenerateAutoVideoAdJob;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\AutoVideoAdProviderManager;
use App\Services\Generation\ShotstackAutoVideoAdTimelineBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Throwable;

class ShotstackAutoVideoAdProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('media.disk', 'media-test');
        config()->set('auto_video_ad.provider', 'mock');
        config()->set('auto_video_ad.real_enabled', false);
        config()->set('auto_video_ad.real_internal_only', true);
        config()->set('auto_video_editor.shotstack.api_key', null);
        config()->set('auto_video_editor.shotstack.base_url', 'https://api.shotstack.io');
        config()->set('auto_video_editor.shotstack.environment', 'stage');
        config()->set('auto_video_editor.shotstack.render_poll_interval_seconds', 0);
        config()->set('auto_video_editor.shotstack.render_max_poll_attempts', 1);
        config()->set('auto_video_editor.shotstack.render_timeout_seconds', 1);
        config()->set('auto_video_editor.provider_assets.public_base_url', 'https://assets.nusa.test');
        config()->set('auto_video_editor.provider_assets.ttl_minutes', 30);

        Cache::flush();
        Storage::fake('media-test');
    }

    public function test_provider_manager_returns_mock_by_default(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertSame('mock', app(AutoVideoAdProviderManager::class)->selectedProviderForUser($user));
    }

    public function test_provider_manager_returns_shotstack_for_admin_when_enabled(): void
    {
        $this->enableShotstack();
        $user = User::factory()->admin()->create();

        $this->assertSame('shotstack', app(AutoVideoAdProviderManager::class)->selectedProviderForUser($user));
    }

    public function test_provider_manager_returns_mock_for_normal_user_while_internal_only(): void
    {
        $this->enableShotstack();
        $user = User::factory()->create();

        $this->assertSame('mock', app(AutoVideoAdProviderManager::class)->selectedProviderForUser($user));
    }

    public function test_shotstack_render_creates_private_auto_video_ad_asset(): void
    {
        Queue::fake();
        $this->enableShotstack();
        $this->fakeShotstackSuccess();
        $user = User::factory()->admin()->create(['credits_balance' => 20, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $image = $this->imageAsset($user, $project);
        $video = $this->videoAsset($user, $project);
        $voiceoverAsset = $this->audioAsset($user, $project);
        $voiceover = $this->voiceoverGeneration($user, $project, $voiceoverAsset);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-ad", $this->validPayload([
                'product_image_asset_id' => $image->id,
                'source_video_asset_id' => $video->id,
                'voiceover_generation_id' => $voiceover->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.provider', 'shotstack')
            ->assertJsonPath('data.credits_charged', 12)
            ->assertJsonPath('meta.credits_balance', 8)
            ->json('data.id');

        app()->call([new GenerateAutoVideoAdJob($generationId), 'handle']);

        $generation = Generation::query()->findOrFail($generationId);
        $asset = MediaAsset::query()->where('usage_role', 'edited_video_result')->firstOrFail();

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame('shotstack', $generation->provider);
        $this->assertSame($asset->id, $generation->output_payload['final_video_asset_id']);
        $this->assertSame("/api/v1/media-assets/{$asset->id}/stream", $generation->output_payload['final_video_stream_url']);
        $this->assertSame("/api/v1/media-assets/{$asset->id}/download", $generation->output_payload['final_video_download_url']);
        $this->assertSame($asset->id, $generation->output_payload['rendered_video_asset']['id']);
        $this->assertTrue($generation->output_payload['metadata']['render_available']);
        $this->assertFalse($generation->output_payload['metadata']['shotstack_render_pending']);
        $this->assertSame('done', $generation->output_payload['metadata']['provider_render_status']);
        $this->assertSame('shotstack', $generation->output_payload['provider_metadata']['provider']);
        $this->assertSame('auto_video_ads', $generation->output_payload['provider_metadata']['workflow']);
        $this->assertSame('render_ad_123', $generation->output_payload['provider_metadata']['render_id']);
        $this->assertNotEmpty($generation->output_payload['provider_metadata']['source_clip_ranges_used']);
        $this->assertSame('auto-video-ad-'.$generationId.'.mp4', $asset->original_name);
        $this->assertSame('auto-video-ads', $asset->metadata['workflow']);
        $this->assertSame('auto_video_ads', $asset->metadata['workflow_key']);
        $this->assertSame('shotstack', $asset->metadata['provider']);
        $this->assertSame('render_ad_123', $asset->metadata['render_id']);
        $this->assertSame(8, $user->refresh()->credits_balance);
        $this->assertSame(12, CreditTransaction::query()
            ->where('generation_id', $generationId)
            ->where('type', CreditTransactionType::Usage->value)
            ->firstOrFail()
            ->amount);
    }

    public function test_shotstack_non_mp4_download_fails_safely_and_refunds(): void
    {
        Queue::fake();
        $this->enableShotstack();
        $this->fakeShotstackSuccess('<!doctype html><html>provider error</html>');
        $user = User::factory()->admin()->create(['credits_balance' => 20, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $image = $this->imageAsset($user, $project);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-ad", $this->validPayload([
                'product_image_asset_id' => $image->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.provider', 'shotstack')
            ->json('data.id');

        $this->runAutoVideoAdJobExpectingFailure($generationId);

        $generation = Generation::query()->findOrFail($generationId);

        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame('shotstack_invalid_response', $generation->output_payload['provider_metadata']['failure_reason']);
        $this->assertSame('download_validate', $generation->output_payload['provider_metadata']['failure_stage']);
        $this->assertSame(20, $user->refresh()->credits_balance);
        $this->assertSame(1, CreditTransaction::query()
            ->where('generation_id', $generationId)
            ->where('type', CreditTransactionType::Refund->value)
            ->count());
        $this->assertSame(0, MediaAsset::query()->where('usage_role', 'edited_video_result')->count());
    }

    public function test_auto_video_ad_timeline_builder_supports_image_video_and_hybrid_sources(): void
    {
        $builder = app(ShotstackAutoVideoAdTimelineBuilder::class);
        $plan = [
            'ad_summary' => ['main_cta' => 'Shop now'],
            'storyboard' => [
                ['visual_direction' => 'Open with product.', 'overlay_text' => 'New find', 'narration_line' => 'Here is the product.'],
                ['visual_direction' => 'Show the benefit.', 'overlay_text' => 'Useful detail', 'narration_line' => 'Show one useful detail.'],
                ['visual_direction' => 'Close with CTA.', 'overlay_text' => 'Shop now', 'narration_line' => 'Shop now.'],
            ],
        ];
        $input = [
            'target_duration_seconds' => 15,
            'ad_format' => 'vertical_9_16',
            'source_video_asset' => ['metadata' => ['duration_seconds' => 55]],
            'campaign_generation_summary' => [
                'recommended_cuts' => [
                    ['start_seconds' => 2, 'end_seconds' => 6],
                    ['start_seconds' => 20, 'end_seconds' => 25],
                    ['start_seconds' => 44, 'end_seconds' => 49],
                ],
            ],
        ];

        $image = $builder->build('https://assets.nusa.test/image.jpg', null, null, $plan, $input + ['visual_source_type' => 'image']);
        $video = $builder->build(null, 'https://assets.nusa.test/video.mp4', null, $plan, $input + ['visual_source_type' => 'video']);
        $hybrid = $builder->build('https://assets.nusa.test/image.jpg', 'https://assets.nusa.test/video.mp4', null, $plan, $input + ['visual_source_type' => 'hybrid']);

        $this->assertSame('image', $image['timeline_summary']['visual_source_type']);
        $this->assertSame('video', $video['timeline_summary']['visual_source_type']);
        $this->assertSame('hybrid', $hybrid['timeline_summary']['visual_source_type']);
        $this->assertSame('product_image', $image['timeline'][0]['source']);
        $this->assertSame('source_video', $video['timeline'][0]['source']);
        $this->assertSame('product_image', $hybrid['timeline'][0]['source']);
        $this->assertNotEmpty($video['timeline_summary']['source_clip_ranges_used']);
        $this->assertTrue($video['timeline_summary']['diverse_source_segments_used']);
        $this->assertFalse($video['render_settings']['captions_overlapping']);
    }

    private function enableShotstack(string $apiKey = 'shotstack-test-key'): void
    {
        config()->set('auto_video_ad.provider', 'shotstack');
        config()->set('auto_video_ad.real_enabled', true);
        config()->set('auto_video_ad.real_internal_only', true);
        config()->set('auto_video_editor.shotstack.api_key', $apiKey);
    }

    private function fakeShotstackSuccess(?string $downloadBody = null): void
    {
        Http::fake([
            'https://api.shotstack.io/edit/stage/render' => Http::response([
                'success' => true,
                'message' => 'Created',
                'response' => ['id' => 'render_ad_123'],
            ], 201),
            'https://api.shotstack.io/edit/stage/render/render_ad_123*' => Http::response([
                'success' => true,
                'message' => 'OK',
                'response' => [
                    'id' => 'render_ad_123',
                    'status' => 'done',
                    'url' => 'https://shotstack-output.nusa.test/render_ad_123.mp4',
                ],
            ]),
            'https://shotstack-output.nusa.test/*' => Http::response($downloadBody ?? $this->mp4Bytes(), 200, [
                'Content-Type' => 'video/mp4',
            ]),
        ]);
    }

    private function runAutoVideoAdJobExpectingFailure(int $generationId): void
    {
        $job = new GenerateAutoVideoAdJob($generationId);

        try {
            app()->call([$job, 'handle']);
        } catch (Throwable $exception) {
            $job->failed($exception);

            return;
        }

        $this->fail('Expected Auto Video Ads job to fail.');
    }

    private function mp4Bytes(): string
    {
        return "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";
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
            'metadata' => ['duration_seconds' => 55],
        ]);
    }

    private function audioAsset(User $user, Project $project): MediaAsset
    {
        return MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'audio',
            'usage_role' => 'voiceover_audio',
            'original_name' => 'creator-desk-light-voiceover.mp3',
            'mime_type' => 'audio/mpeg',
            'size_bytes' => 280_000,
            'metadata' => ['duration_seconds' => 18.5],
        ]);
    }

    private function voiceoverGeneration(User $user, Project $project, MediaAsset $asset): Generation
    {
        return Generation::factory()->for($user)->for($project)->create([
            'job_type' => 'generate_ai_voiceover',
            'provider' => 'elevenlabs',
            'status' => Generation::STATUS_COMPLETED,
            'output_payload' => [
                'audio_asset_id' => $asset->id,
                'audio' => [
                    'asset_id' => $asset->id,
                    'duration_seconds' => 18.5,
                ],
                'narration_script' => 'Show the light in a real creator setup.',
            ],
        ]);
    }
}
