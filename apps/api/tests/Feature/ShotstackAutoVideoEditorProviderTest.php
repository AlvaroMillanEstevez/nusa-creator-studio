<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Jobs\GenerateAutoVideoEditJob;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\AutoVideoEditorGenerationProcessor;
use App\Services\Generation\AutoVideoEditorProviderManager;
use App\Services\Media\TemporaryProviderAssetUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;
use Throwable;

class ShotstackAutoVideoEditorProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('media.disk', 'media-test');
        config()->set('auto_video_editor.provider', 'mock');
        config()->set('auto_video_editor.real_enabled', false);
        config()->set('auto_video_editor.real_internal_only', true);
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

        $this->assertSame('mock', app(AutoVideoEditorProviderManager::class)->selectedProviderForUser($user));
    }

    public function test_provider_manager_returns_mock_when_shotstack_disabled(): void
    {
        config()->set('auto_video_editor.provider', 'shotstack');
        config()->set('auto_video_editor.real_enabled', false);

        $user = User::factory()->admin()->create();

        $this->assertSame('mock', app(AutoVideoEditorProviderManager::class)->selectedProviderForUser($user));
    }

    public function test_provider_manager_returns_mock_for_normal_user_while_internal_only(): void
    {
        config()->set('auto_video_editor.provider', 'shotstack');
        config()->set('auto_video_editor.real_enabled', true);
        config()->set('auto_video_editor.real_internal_only', true);

        $user = User::factory()->create();

        $this->assertSame('mock', app(AutoVideoEditorProviderManager::class)->selectedProviderForUser($user));
    }

    public function test_provider_manager_returns_shotstack_for_admin_when_enabled(): void
    {
        config()->set('auto_video_editor.provider', 'shotstack');
        config()->set('auto_video_editor.real_enabled', true);
        config()->set('auto_video_editor.real_internal_only', true);

        $user = User::factory()->admin()->create();

        $this->assertSame('shotstack', app(AutoVideoEditorProviderManager::class)->selectedProviderForUser($user));
    }

    public function test_missing_shotstack_api_key_fails_safely_and_refunds(): void
    {
        Queue::fake();
        $this->enableShotstack(apiKey: '');
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project);
        $analysis = $this->videoAnalysis($user, $project, $sourceVideo);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'source_video_to_campaign_generation_id' => $analysis->id,
                'include_voiceover' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'shotstack')
            ->assertJsonPath('meta.credits_balance', 8)
            ->json('data.id');

        $this->runAutoEditorJobExpectingFailure($generationId);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame('shotstack_config_missing', $generation->output_payload['provider_metadata']['failure_reason']);
        $this->assertSame(10, $user->refresh()->credits_balance);
        $this->assertSame(1, CreditTransaction::query()
            ->where('generation_id', $generationId)
            ->where('type', CreditTransactionType::Refund->value)
            ->count());
        $this->assertSame(0, MediaAsset::query()->where('usage_role', 'edited_video_result')->count());
    }

    public function test_missing_public_asset_base_url_fails_safely_and_refunds(): void
    {
        Queue::fake();
        $this->enableShotstack(apiKey: 'shotstack-test-key', publicBaseUrl: '');
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project);
        $analysis = $this->videoAnalysis($user, $project, $sourceVideo);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'source_video_to_campaign_generation_id' => $analysis->id,
                'include_voiceover' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'shotstack')
            ->json('data.id');

        $this->runAutoEditorJobExpectingFailure($generationId);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame('shotstack_public_asset_base_url_missing', $generation->output_payload['provider_metadata']['failure_reason']);
        $this->assertSame(10, $user->refresh()->credits_balance);
    }

    public function test_temporary_provider_asset_url_streams_only_the_intended_asset(): void
    {
        $user = User::factory()->create();
        $project = $this->workspaceProject($user);
        $asset = $this->videoAsset($user, $project, contents: 'INTENDED_VIDEO_BYTES');
        $this->videoAsset($user, $project, contents: 'OTHER_VIDEO_BYTES');

        $url = app(TemporaryProviderAssetUrlService::class)->create($asset, 'shotstack');
        $path = parse_url($url, PHP_URL_PATH);

        $response = $this->get($path);

        $response->assertOk();
        $this->assertSame('INTENDED_VIDEO_BYTES', $response->streamedContent());
        $this->assertStringNotContainsString($asset->path, $response->streamedContent());
    }

    public function test_temporary_provider_asset_url_expires(): void
    {
        $user = User::factory()->create();
        $project = $this->workspaceProject($user);
        $asset = $this->videoAsset($user, $project);
        $service = app(TemporaryProviderAssetUrlService::class);
        $url = $service->create($asset, 'shotstack');
        $token = basename((string) parse_url($url, PHP_URL_PATH));
        $cacheKey = $this->providerAssetCacheKey($service, $token);
        $payload = Cache::get($cacheKey);
        $payload['expires_at'] = now()->subMinute()->toISOString();
        Cache::put($cacheKey, $payload, now()->addMinute());

        $this->get(parse_url($url, PHP_URL_PATH))->assertNotFound();
    }

    public function test_another_user_cannot_create_shotstack_render_using_someone_elses_asset(): void
    {
        Queue::fake();
        $this->enableShotstack();
        $owner = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $other = User::factory()->create();
        $project = $this->workspaceProject($owner);
        $otherAsset = $this->videoAsset($other, $this->workspaceProject($other));

        $this->actingAs($owner)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'source_video_asset_id' => $otherAsset->id,
                'include_voiceover' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_video_asset_id');

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('generations', 0);
        $this->assertSame(10, $owner->refresh()->credits_balance);
    }

    public function test_shotstack_submit_poll_and_download_success_creates_private_video_asset(): void
    {
        Queue::fake();
        $this->enableShotstack();
        $this->fakeShotstackSuccess();
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project);
        $voiceover = $this->audioAsset($user, $project);
        $analysis = $this->videoAnalysis($user, $project, $sourceVideo);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'source_video_to_campaign_generation_id' => $analysis->id,
                'include_voiceover' => true,
                'voiceover_audio_asset_id' => $voiceover->id,
                'target_duration_seconds' => 15,
            ])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'shotstack')
            ->json('data.id');

        app()->call([new GenerateAutoVideoEditJob($generationId), 'handle']);

        $generation = Generation::query()->findOrFail($generationId);
        $asset = MediaAsset::query()->where('usage_role', 'edited_video_result')->firstOrFail();

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame('shotstack', $generation->provider);
        $this->assertSame($asset->id, $generation->output_payload['final_video_asset_id']);
        $this->assertSame("/api/v1/media-assets/{$asset->id}/stream", $generation->output_payload['final_video_stream_url']);
        $this->assertSame("/api/v1/media-assets/{$asset->id}/download", $generation->output_payload['final_video_download_url']);
        $this->assertSame('voiceover_matched', $generation->output_payload['timeline_summary']['duration_sync_strategy']);
        $this->assertGreaterThanOrEqual(20.5, $generation->output_payload['timeline_summary']['final_duration_seconds']);
        $this->assertSame(15, $generation->output_payload['timeline_summary']['requested_target_duration_seconds']);
        $this->assertGreaterThanOrEqual(20.5, $generation->output_payload['timeline_summary']['effective_target_duration_seconds']);
        $this->assertTrue($generation->output_payload['timeline_summary']['duration_adjusted_for_voiceover']);
        $this->assertFalse($generation->output_payload['timeline_summary']['captions_overlapping']);
        $this->assertNotEmpty($generation->output_payload['timeline_summary']['caption_schedule']);
        $this->assertSame('shadow_text_light_caption', $generation->output_payload['timeline_summary']['caption_visual_strategy']);
        $this->assertSame('Check the product details.', $generation->output_payload['timeline_summary']['cta_used']);
        $this->assertSame(0, $generation->output_payload['timeline_summary']['repeated_clip_count']);
        $this->assertNull($generation->output_payload['timeline_summary']['repeated_clip_reason']);
        $this->assertNotEmpty($generation->output_payload['timeline_summary']['source_clip_ranges_used']);
        $this->assertSame('Check the product details.', $generation->output_payload['creative_brief']['selected_cta']);
        $this->assertSame('shotstack', $generation->output_payload['provider_metadata']['provider']);
        $this->assertSame(15, $generation->output_payload['provider_metadata']['requested_target_duration_seconds']);
        $this->assertGreaterThanOrEqual(20.5, $generation->output_payload['provider_metadata']['effective_target_duration_seconds']);
        $this->assertTrue($generation->output_payload['provider_metadata']['duration_adjusted_for_voiceover']);
        $this->assertFalse($generation->output_payload['provider_metadata']['captions_overlapping']);
        $this->assertNotEmpty($generation->output_payload['provider_metadata']['caption_schedule']);
        $this->assertSame('shadow_text_light_caption', $generation->output_payload['provider_metadata']['caption_visual_strategy']);
        $this->assertSame('voiceover_matched', $generation->output_payload['provider_metadata']['duration_sync_strategy']);
        $this->assertSame('9:16', $generation->output_payload['provider_metadata']['output_aspect_ratio']);
        $this->assertSame(720, $generation->output_payload['provider_metadata']['output_width']);
        $this->assertSame(1280, $generation->output_payload['provider_metadata']['output_height']);
        $this->assertSame('cover_crop_to_vertical', $generation->output_payload['provider_metadata']['source_fit_strategy']);
        $this->assertFalse($generation->output_payload['provider_metadata']['has_letterboxing_expected']);
        $this->assertTrue($generation->output_payload['provider_metadata']['caption_safe_area_applied']);
        $this->assertSame('shotstack', $asset->metadata['provider']);
        $this->assertSame('render_test_123', $asset->metadata['render_id']);
        $this->assertSame($sourceVideo->id, $asset->metadata['source_video_asset_id']);
        $this->assertSame($voiceover->id, $asset->metadata['voiceover_asset_id']);
        $this->assertSame('voiceover_matched', $asset->metadata['timeline_summary']['duration_sync_strategy']);
        $this->assertSame('9:16', $asset->metadata['render_settings']['output_aspect_ratio']);
        $this->assertSame(720, $asset->metadata['render_settings']['output_width']);
        $this->assertSame(1280, $asset->metadata['render_settings']['output_height']);
        $this->assertTrue($asset->metadata['captions_plan']['caption_safe_area_applied']);
        Storage::disk('media-test')->assertExists($asset->path);

        $json = json_encode($generation->output_payload, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($asset->path, $json);
        $this->assertStringNotContainsString('shotstack-test-key', $json);
        $this->assertStringNotContainsString('provider-assets', $json);
    }

    public function test_shotstack_render_failure_marks_generation_failed_and_refunds(): void
    {
        Queue::fake();
        $this->enableShotstack();
        Http::fake([
            'https://api.shotstack.io/edit/stage/render' => Http::response([
                'success' => true,
                'response' => ['id' => 'render_failed_123'],
            ], 201),
            'https://api.shotstack.io/edit/stage/render/render_failed_123*' => Http::response([
                'success' => true,
                'response' => ['id' => 'render_failed_123', 'status' => 'failed'],
            ]),
        ]);
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project);
        $analysis = $this->videoAnalysis($user, $project, $sourceVideo);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'source_video_to_campaign_generation_id' => $analysis->id,
                'include_voiceover' => false,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->runAutoEditorJobExpectingFailure($generationId);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame('shotstack_render_failed', $generation->output_payload['provider_metadata']['failure_reason']);
        $this->assertSame(10, $user->refresh()->credits_balance);
    }

    public function test_shotstack_render_timeout_marks_generation_failed_and_refunds(): void
    {
        Queue::fake();
        $this->enableShotstack();
        Http::fake([
            'https://api.shotstack.io/edit/stage/render' => Http::response([
                'success' => true,
                'response' => ['id' => 'render_timeout_123'],
            ], 201),
            'https://api.shotstack.io/edit/stage/render/render_timeout_123*' => Http::response([
                'success' => true,
                'response' => ['id' => 'render_timeout_123', 'status' => 'rendering'],
            ]),
        ]);
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project);
        $analysis = $this->videoAnalysis($user, $project, $sourceVideo);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'source_video_to_campaign_generation_id' => $analysis->id,
                'include_voiceover' => false,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->runAutoEditorJobExpectingFailure($generationId);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame('shotstack_render_timeout', $generation->output_payload['provider_metadata']['failure_reason']);
        $this->assertSame(10, $user->refresh()->credits_balance);
    }

    public function test_real_provider_mode_requires_existing_voiceover_audio_for_voiceover_option(): void
    {
        Queue::fake();
        $this->enableShotstack();
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project);
        $analysis = $this->videoAnalysis($user, $project, $sourceVideo);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-auto-video-edit", [
                'source_video_to_campaign_generation_id' => $analysis->id,
                'include_voiceover' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('voiceover_generation_id')
            ->assertSee('Generate a voiceover first, then reuse it in Auto Video Editor.');

        Queue::assertNothingPushed();
        $this->assertSame(10, $user->refresh()->credits_balance);
    }

    public function test_owner_can_stream_and_download_generated_video_asset(): void
    {
        $user = User::factory()->create();
        $project = $this->workspaceProject($user);
        $asset = $this->videoAsset($user, $project, role: 'edited_video_result', contents: 'PRIVATE_MP4_BYTES');
        $asset->forceFill(['original_name' => 'auto-video-editor-49.mp4'])->save();

        $stream = $this->actingAs($user)
            ->get("/api/v1/media-assets/{$asset->id}/stream", ['Accept' => 'video/mp4'])
            ->assertOk()
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('Accept-Ranges', 'bytes');
        $this->assertStringContainsString('inline;', $stream->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('filename="auto-video-editor-49.mp4"', $stream->headers->get('Content-Disposition') ?? '');
        $this->assertSame('PRIVATE_MP4_BYTES', $stream->streamedContent());
        $this->assertStringNotContainsString('<html', strtolower($stream->streamedContent()));

        $download = $this->actingAs($user)
            ->get("/api/v1/media-assets/{$asset->id}/download", ['Accept' => 'video/mp4'])
            ->assertOk()
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('Accept-Ranges', 'bytes');
        $this->assertStringContainsString('attachment;', $download->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('filename="auto-video-editor-49.mp4"', $download->headers->get('Content-Disposition') ?? '');
        $this->assertStringEndsWith('.mp4"', $download->headers->get('Content-Disposition') ?? '');
        $this->assertSame('PRIVATE_MP4_BYTES', $download->streamedContent());
        $this->assertStringNotContainsString('<html', strtolower($download->streamedContent()));
    }

    public function test_secure_video_delivery_rejects_guest_other_user_and_missing_storage_object(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = $this->workspaceProject($owner);
        $asset = $this->videoAsset($owner, $project, role: 'edited_video_result');
        $missing = MediaAsset::factory()->for($owner)->for($project)->create([
            'type' => 'video',
            'usage_role' => 'edited_video_result',
            'disk' => 'media-test',
            'path' => 'missing/final.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 100,
        ]);

        $this->getJson("/api/v1/media-assets/{$asset->id}/stream")->assertUnauthorized();
        $this->getJson("/api/v1/media-assets/{$asset->id}/download")->assertUnauthorized();
        $this->actingAs($other)->getJson("/api/v1/media-assets/{$asset->id}/stream")->assertNotFound();
        $this->actingAs($other)->getJson("/api/v1/media-assets/{$asset->id}/download")->assertNotFound();
        $this->actingAs($owner)->getJson("/api/v1/media-assets/{$missing->id}/stream")->assertNotFound();
        $this->actingAs($owner)->getJson("/api/v1/media-assets/{$missing->id}/download")->assertNotFound();
    }

    private function enableShotstack(string $apiKey = 'shotstack-test-key', string $publicBaseUrl = 'https://assets.nusa.test'): void
    {
        config()->set('auto_video_editor.provider', 'shotstack');
        config()->set('auto_video_editor.real_enabled', true);
        config()->set('auto_video_editor.real_internal_only', true);
        config()->set('auto_video_editor.shotstack.api_key', $apiKey);
        config()->set('auto_video_editor.provider_assets.public_base_url', $publicBaseUrl);
    }

    private function fakeShotstackSuccess(): void
    {
        Http::fake([
            'https://api.shotstack.io/edit/stage/render' => Http::response([
                'success' => true,
                'message' => 'Created',
                'response' => ['id' => 'render_test_123'],
            ], 201),
            'https://api.shotstack.io/edit/stage/render/render_test_123*' => Http::response([
                'success' => true,
                'message' => 'OK',
                'response' => [
                    'id' => 'render_test_123',
                    'status' => 'done',
                    'url' => 'https://shotstack-output.nusa.test/render_test_123.mp4',
                ],
            ]),
            'https://shotstack-output.nusa.test/*' => Http::response('FINAL_MP4_BYTES', 200, [
                'Content-Type' => 'video/mp4',
            ]),
        ]);
    }

    private function runAutoEditorJobExpectingFailure(int $generationId): void
    {
        $job = new GenerateAutoVideoEditJob($generationId);
        $exception = null;

        try {
            $job->handle(app(AutoVideoEditorGenerationProcessor::class));
        } catch (Throwable $caught) {
            $exception = $caught;
            $job->failed($caught);
        }

        $this->assertNotNull($exception);
    }

    private function workspaceProject(User $user): Project
    {
        return Project::factory()->for($user)->create([
            'title' => 'Creator product workspace',
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

    private function videoAsset(
        User $user,
        Project $project,
        string $role = 'campaign_source_video',
        string $contents = 'SOURCE_VIDEO_BYTES',
    ): MediaAsset {
        $path = 'tests/videos/'.uniqid('video_', true).'.mp4';
        Storage::disk('media-test')->put($path, $contents);

        return MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'video',
            'usage_role' => $role,
            'disk' => 'media-test',
            'path' => $path,
            'original_name' => 'raw-product-demo.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => strlen($contents),
            'metadata' => [
                'duration_seconds' => 20,
            ],
        ]);
    }

    private function audioAsset(User $user, Project $project): MediaAsset
    {
        $contents = 'VOICEOVER_AUDIO_BYTES';
        $path = 'tests/audio/'.uniqid('voiceover_', true).'.mp3';
        Storage::disk('media-test')->put($path, $contents);

        return MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'audio',
            'usage_role' => 'voiceover_audio',
            'disk' => 'media-test',
            'path' => $path,
            'original_name' => 'voiceover.mp3',
            'mime_type' => 'audio/mpeg',
            'size_bytes' => strlen($contents),
            'metadata' => [
                'duration_seconds' => 20,
            ],
        ]);
    }

    private function videoAnalysis(User $user, Project $project, MediaAsset $sourceVideo): Generation
    {
        return Generation::factory()->for($user)->for($project)->create([
            'job_type' => 'generate_video_to_campaign',
            'provider' => 'gemini',
            'status' => Generation::STATUS_COMPLETED,
            'credits_charged' => 2,
            'input_payload' => ['source_video_asset_id' => $sourceVideo->id],
            'output_payload' => [
                'video_summary' => 'Reusable Gemini video campaign summary.',
                'source_video_asset_id' => $sourceVideo->id,
                'edit_plan' => [
                    'timeline' => [
                        [
                            'timestamp' => '0-3s',
                            'text_overlay' => 'Start with the product',
                            'visual_direction' => 'Open on the clearest product shot.',
                        ],
                        [
                            'timestamp' => '3-7s',
                            'text_overlay' => 'Show the useful detail',
                            'visual_direction' => 'Cut to a simple usage close-up.',
                        ],
                    ],
                ],
                'recommended_cuts' => [
                    ['timestamp' => '0-3s', 'reason' => 'Strong opening shot.'],
                ],
                'cta_options' => ['Check the product details.'],
            ],
        ]);
    }

    private function providerAssetCacheKey(TemporaryProviderAssetUrlService $service, string $token): string
    {
        $method = new ReflectionMethod($service, 'cacheKey');
        $method->setAccessible(true);

        return $method->invoke($service, $token);
    }
}
