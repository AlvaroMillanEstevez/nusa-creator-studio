<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Exceptions\MotionAiVideo\MotionAiVideoProviderException;
use App\Jobs\GenerateMotionAiVideoJob;
use App\Models\CreatorTool;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\GoogleVeoMotionAiVideoClient;
use App\Services\Generation\GoogleVeoMotionAiVideoPromptBuilder;
use App\Services\Generation\GoogleVeoSdkBridgeRunner;
use App\Services\Generation\GoogleVeoSdkProbeRunner;
use App\Services\Generation\MotionAiVideoProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class MotionAiVideoGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('media.disk', 'motion-video-test');
        config()->set('motion_ai_video.provider', 'mock');
        config()->set('motion_ai_video.real_enabled', false);
        config()->set('motion_ai_video.real_internal_only', true);
        config()->set('motion_ai_video.google_veo.api_key', null);
        config()->set('motion_ai_video.google_veo.model', 'veo-test');
        config()->set('motion_ai_video.google_veo.request_mode', 'text_only');
        config()->set('motion_ai_video.google_veo.image_references_enabled', false);
        config()->set('motion_ai_video.google_veo.image_to_video_enabled', false);
        config()->set('motion_ai_video.google_veo.allow_experimental_image_input', false);
        config()->set('motion_ai_video.google_veo.max_reference_images', 3);
        config()->set('motion_ai_video.google_veo.default_duration_seconds', 8);
        config()->set('motion_ai_video.google_veo.default_aspect_ratio', '9:16');
        config()->set('motion_ai_video.google_veo.connect_timeout_seconds', 60);
        config()->set('motion_ai_video.google_veo.timeout_seconds', 300);
        config()->set('motion_ai_video.google_veo.request_timeout_seconds', 180);
        config()->set('motion_ai_video.google_veo.retry_times', 2);
        config()->set('motion_ai_video.google_veo.retry_sleep_ms', 1500);
        config()->set('motion_ai_video.google_veo.force_ipv4', false);
        config()->set('motion_ai_video.google_veo.force_http1', false);
        config()->set('motion_ai_video.google_veo.poll_interval_seconds', 0);
        config()->set('motion_ai_video.google_veo.max_poll_attempts', 1);
        config()->set('motion_ai_video.google_veo.sdk_process_timeout_seconds', 900);
        config()->set('motion_ai_video.google_veo.sdk_poll_interval_seconds', 10);
        config()->set('motion_ai_video.google_veo.estimated_cost_usd_per_second', '0.02');

        Storage::fake('motion-video-test');

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

    public function test_text_prompt_mode_accepts_no_product_image(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'source_mode' => 'text_prompt',
                'product_image_asset_id' => null,
                'creative_notes' => 'Create a clean social commerce product showcase from the brief only.',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.input_payload.source_mode', 'text_prompt')
            ->assertJsonPath('data.input_payload.product_image_asset_id', null)
            ->json('data.id');

        app()->call([new GenerateMotionAiVideoJob($generationId), 'handle']);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame('text_prompt', $generation->output_payload['metadata']['source_mode']);
        $this->assertNull($generation->output_payload['asset_plan']['product_image_asset_id']);
    }

    public function test_campaign_assisted_mode_accepts_campaign_without_product_image(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        $campaign = Generation::factory()->for($user)->for($project)->create([
            'job_type' => 'generate_affiliate_campaign',
            'status' => Generation::STATUS_COMPLETED,
            'output_payload' => [
                'campaign_angle' => 'Show the bottle as a desk routine upgrade.',
                'hooks' => ['Your desk routine needs this hydration cue.'],
                'cta' => 'See product details',
            ],
        ]);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'source_mode' => 'campaign_assisted',
                'product_image_asset_id' => null,
                'campaign_generation_id' => $campaign->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.input_payload.source_mode', 'campaign_assisted')
            ->assertJsonPath('data.input_payload.campaign_generation_id', $campaign->id)
            ->assertJsonPath('data.input_payload.product_image_asset_id', null)
            ->json('data.id');

        app()->call([new GenerateMotionAiVideoJob($generationId), 'handle']);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame('campaign_assisted', $generation->output_payload['metadata']['source_mode']);
        $this->assertSame($campaign->id, $generation->output_payload['metadata']['campaign_generation_id']);
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

    public function test_motion_ai_video_provider_manager_returns_mock_by_default(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertSame('mock', app(MotionAiVideoProviderManager::class)->selectedProviderForUser($user));
    }

    public function test_motion_ai_video_provider_manager_returns_google_veo_for_admin_when_enabled(): void
    {
        $this->enableGoogleVeo();
        $user = User::factory()->admin()->create();

        $this->assertSame('google_veo', app(MotionAiVideoProviderManager::class)->selectedProviderForUser($user));
    }

    public function test_motion_ai_video_real_disabled_falls_back_to_mock(): void
    {
        config()->set('motion_ai_video.provider', 'google_veo');
        config()->set('motion_ai_video.real_enabled', false);
        config()->set('motion_ai_video.google_veo.api_key', 'veo-test-key');
        $user = User::factory()->admin()->create();

        $this->assertSame('mock', app(MotionAiVideoProviderManager::class)->selectedProviderForUser($user));
    }

    public function test_motion_ai_video_provider_manager_respects_internal_only_eligibility(): void
    {
        $this->enableGoogleVeo();
        $normalUser = User::factory()->create();
        $internalUser = User::factory()->create(['role' => 'internal_creator']);

        $this->assertSame('mock', app(MotionAiVideoProviderManager::class)->selectedProviderForUser($normalUser));
        $this->assertSame('google_veo', app(MotionAiVideoProviderManager::class)->selectedProviderForUser($internalUser));
    }

    public function test_project_actions_expose_safe_motion_ai_capabilities(): void
    {
        $this->enableGoogleVeo();
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/actions")
            ->assertOk();

        $action = collect($response->json('data'))->firstWhere('key', 'start_motion_video');

        $this->assertIsArray($action);
        $this->assertSame('google_veo', data_get($action, 'provider_capabilities.provider_mode'));
        $this->assertSame('text_only', data_get($action, 'provider_capabilities.request_mode'));
        $this->assertFalse(data_get($action, 'provider_capabilities.product_image_required'));
        $this->assertSame('context_only', data_get($action, 'provider_capabilities.product_image_supported'));
        $this->assertTrue(data_get($action, 'provider_capabilities.campaign_supported'));
        $this->assertTrue(data_get($action, 'provider_capabilities.text_prompt_supported'));
        $this->assertTrue(data_get($action, 'provider_capabilities.image_context_supported'));
        $this->assertTrue(data_get($action, 'provider_capabilities.image_to_video_supported'));
        $this->assertFalse(data_get($action, 'provider_capabilities.image_to_video_enabled'));
        $this->assertTrue(data_get($action, 'provider_capabilities.reference_images_supported'));
        $this->assertFalse(data_get($action, 'provider_capabilities.reference_images_enabled'));
        $this->assertSame(3, data_get($action, 'provider_capabilities.max_reference_images'));
        $this->assertFalse(data_get($action, 'provider_capabilities.image_reference_experimental'));
        $this->assertSame('Google Veo', data_get($action, 'provider_capabilities.provider_label'));
    }

    public function test_project_actions_enable_image_first_frame_from_feature_flag_without_reference_images(): void
    {
        $this->enableGoogleVeo();
        config()->set('motion_ai_video.google_veo.image_to_video_enabled', true);
        config()->set('motion_ai_video.google_veo.request_mode', 'text_only');
        config()->set('motion_ai_video.google_veo.image_references_enabled', true);
        config()->set('motion_ai_video.google_veo.allow_experimental_image_input', true);
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/projects/{$project->id}/actions")
            ->assertOk();

        $action = collect($response->json('data'))->firstWhere('key', 'start_motion_video');

        $this->assertSame('text_only', data_get($action, 'provider_capabilities.request_mode'));
        $this->assertSame('first_frame', data_get($action, 'provider_capabilities.product_image_supported'));
        $this->assertTrue(data_get($action, 'provider_capabilities.image_to_video_enabled'));
        $this->assertFalse(data_get($action, 'provider_capabilities.reference_images_enabled'));
        $this->assertFalse(data_get($action, 'provider_capabilities.image_reference_experimental'));
    }

    public function test_google_veo_client_uses_configured_network_options(): void
    {
        $this->enableGoogleVeo();
        config()->set('motion_ai_video.google_veo.connect_timeout_seconds', 61);
        config()->set('motion_ai_video.google_veo.timeout_seconds', 301);
        config()->set('motion_ai_video.google_veo.retry_times', 3);
        config()->set('motion_ai_video.google_veo.retry_sleep_ms', 750);

        $client = app(GoogleVeoMotionAiVideoClient::class);
        $options = $client->networkOptionsForDiagnostics();
        $summary = $client->safeNetworkSummary('submit');

        $this->assertSame(61, $options['connect_timeout']);
        $this->assertSame(301, $options['timeout']);
        $this->assertSame(3, $options['retry_times']);
        $this->assertSame(750, $options['retry_sleep_ms']);
        $this->assertSame('generativelanguage.googleapis.com', $summary['endpoint_host']);
        $this->assertSame('/v1beta/models/veo-test:predictLongRunning', $summary['endpoint_path']);
        $this->assertSame(61, $summary['connect_timeout_seconds']);
        $this->assertSame(301, $summary['timeout_seconds']);
        $this->assertFalse($summary['force_ipv4']);
        $this->assertFalse($summary['force_http1']);
    }

    public function test_google_veo_timeout_falls_back_to_legacy_request_timeout(): void
    {
        $this->enableGoogleVeo();
        config()->set('motion_ai_video.google_veo.timeout_seconds', null);
        config()->set('motion_ai_video.google_veo.request_timeout_seconds', 222);

        $this->assertSame(222, app(GoogleVeoMotionAiVideoClient::class)->networkOptionsForDiagnostics()['timeout']);
    }

    public function test_google_veo_force_ipv4_and_http1_curl_options_are_opt_in(): void
    {
        $this->enableGoogleVeo();

        $disabledOptions = app(GoogleVeoMotionAiVideoClient::class)->networkOptionsForDiagnostics();
        $this->assertSame([], $disabledOptions['curl']);

        config()->set('motion_ai_video.google_veo.force_ipv4', true);
        config()->set('motion_ai_video.google_veo.force_http1', true);

        $enabledOptions = app(GoogleVeoMotionAiVideoClient::class)->networkOptionsForDiagnostics();
        $this->assertIsArray($enabledOptions['curl']);

        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            $this->assertArrayHasKey(constant('CURLOPT_IPRESOLVE'), $enabledOptions['curl']);
            $this->assertSame(constant('CURL_IPRESOLVE_V4'), $enabledOptions['curl'][constant('CURLOPT_IPRESOLVE')]);
        }

        if (defined('CURLOPT_HTTP_VERSION') && defined('CURL_HTTP_VERSION_1_1')) {
            $this->assertArrayHasKey(constant('CURLOPT_HTTP_VERSION'), $enabledOptions['curl']);
            $this->assertSame(constant('CURL_HTTP_VERSION_1_1'), $enabledOptions['curl'][constant('CURLOPT_HTTP_VERSION')]);
        }
    }

    public function test_google_veo_curl_options_do_not_crash_when_curl_constants_are_missing(): void
    {
        $this->enableGoogleVeo();
        config()->set('motion_ai_video.google_veo.force_ipv4', true);
        config()->set('motion_ai_video.google_veo.force_http1', true);

        $client = new class extends GoogleVeoMotionAiVideoClient
        {
            protected function curlConstant(string $name): ?int
            {
                return null;
            }
        };

        $options = $client->networkOptionsForDiagnostics();

        $this->assertSame([], $options['curl']);
    }

    public function test_google_veo_connection_exception_is_wrapped_and_logged_safely(): void
    {
        $this->enableGoogleVeo('veo-secret-key');
        config()->set('motion_ai_video.google_veo.connect_timeout_seconds', 60);
        config()->set('motion_ai_video.google_veo.timeout_seconds', 300);
        config()->set('motion_ai_video.google_veo.retry_times', 1);
        config()->set('motion_ai_video.google_veo.retry_sleep_ms', 0);
        config()->set('motion_ai_video.google_veo.force_ipv4', true);
        config()->set('motion_ai_video.google_veo.force_http1', true);

        Log::spy();
        Http::fake(function (Request $request): never {
            throw new ConnectionException('cURL error 28: SSL connection timeout for https://generativelanguage.googleapis.com/v1beta/models/veo-test:predictLongRunning?key=veo-secret-key '.str_repeat('A', 220));
        });

        try {
            app(GoogleVeoMotionAiVideoClient::class)->submit([
                'instances' => [[
                    'prompt' => 'Create a clean product showcase.',
                ]],
                'parameters' => [
                    'aspectRatio' => '9:16',
                    'durationSeconds' => 8,
                ],
            ], [
                'generation_id' => 123,
                'request_mode' => 'text_only',
                'provider_request_shape' => 'text_only',
            ]);

            $this->fail('Expected the Google Veo client to wrap the connection exception.');
        } catch (MotionAiVideoProviderException $exception) {
            $this->assertSame('google_veo_submit_failed', $exception->reason);
            $this->assertInstanceOf(ConnectionException::class, $exception->getPrevious());
            $this->assertSame('submit', $exception->context['stage']);
            $this->assertSame('google_veo', $exception->context['provider']);
            $this->assertSame('veo-test', $exception->context['model']);
            $this->assertSame('generativelanguage.googleapis.com', $exception->context['endpoint_host']);
            $this->assertSame('/v1beta/models/veo-test:predictLongRunning', $exception->context['endpoint_path']);
            $this->assertSame(60, $exception->context['connect_timeout_seconds']);
            $this->assertSame(300, $exception->context['timeout_seconds']);
            $this->assertSame(1, $exception->context['retry_times']);
            $this->assertSame(0, $exception->context['retry_sleep_ms']);
            $this->assertTrue($exception->context['force_ipv4']);
            $this->assertTrue($exception->context['force_http1']);
            $this->assertStringNotContainsString('veo-secret-key', $exception->context['safe_exception_message']);
            $this->assertStringNotContainsString('https://generativelanguage.googleapis.com', $exception->context['safe_exception_message']);
            $this->assertStringContainsString('[redacted-url]', $exception->context['safe_exception_message']);
            $this->assertStringContainsString('[redacted-long-token]', $exception->context['safe_exception_message']);
            $this->assertStringNotContainsString('veo-secret-key', json_encode($exception->context, JSON_THROW_ON_ERROR));
        }

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'Google Veo submit connection failed.'
                && ($context['provider'] ?? null) === 'google_veo'
                && ($context['stage'] ?? null) === 'submit'
                && ($context['endpoint_host'] ?? null) === 'generativelanguage.googleapis.com'
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'veo-secret-key')
                && str_contains((string) ($context['safe_exception_message'] ?? ''), '[redacted-url]');
        });
    }

    public function test_google_veo_motion_video_generation_stores_private_media_asset(): void
    {
        Queue::fake();
        $this->enableGoogleVeo();
        $this->fakeGoogleVeoSuccess();
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $image = $this->storedImageAsset($user, $project);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'product_image_asset_id' => $image->id,
                'creative_notes' => 'Make it premium but still realistic.',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.provider', 'google_veo')
            ->assertJsonPath('data.credits_charged', 3)
            ->assertJsonPath('data.input_payload.creative_notes', 'Make it premium but still realistic.')
            ->assertJsonPath('meta.credits_balance', 7)
            ->json('data.id');

        app()->call([new GenerateMotionAiVideoJob($generationId), 'handle']);

        $generation = Generation::query()->findOrFail($generationId);
        $asset = MediaAsset::query()
            ->where('type', 'video')
            ->where('usage_role', 'edited_video_result')
            ->firstOrFail();

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame('google_veo', $generation->provider);
        $this->assertSame($asset->id, $generation->output_payload['final_video_asset_id']);
        $this->assertSame("/api/v1/media-assets/{$asset->id}/stream", $generation->output_payload['final_video_stream_url']);
        $this->assertSame("/api/v1/media-assets/{$asset->id}/download", $generation->output_payload['final_video_download_url']);
        $this->assertSame($asset->id, $generation->output_payload['rendered_video_asset']['id']);
        $this->assertTrue($generation->output_payload['motion_summary']['render_available']);
        $this->assertTrue($generation->output_payload['metadata']['real_provider_enabled']);
        $this->assertSame('google_veo', $generation->output_payload['provider_metadata']['provider']);
        $this->assertSame('veo-test', $generation->output_payload['provider_metadata']['model']);
        $this->assertTrue($generation->output_payload['provider_metadata']['visual_first_clip']);
        $this->assertTrue($generation->output_payload['provider_metadata']['native_audio_ignored']);
        $this->assertSame(
            'Clean visual-first product footage. Exact captions, CTA, account/store names, and overlays are deferred to post-production.',
            $generation->output_payload['generation_prompt']['main_prompt'],
        );
        $this->assertIsInt($generation->output_payload['provider_metadata']['final_prompt_character_count']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $generation->output_payload['provider_metadata']['final_prompt_hash']);
        $this->assertTrue($generation->output_payload['provider_metadata']['overlay_text_deferred']);
        $this->assertTrue($generation->output_payload['provider_metadata']['platform_ui_forbidden']);
        $this->assertTrue($generation->output_payload['provider_metadata']['invented_names_forbidden']);
        $this->assertSame('motion-ai-video', $asset->metadata['workflow']);
        $this->assertSame('motion_ai_video', $asset->metadata['workflow_key']);
        $this->assertSame('google_veo', $asset->metadata['provider']);
        $this->assertSame(8, $generation->output_payload['provider_metadata']['duration_requested_seconds']);
        $this->assertSame(8, $generation->output_payload['provider_metadata']['duration_provider_seconds']);
        $this->assertFalse($generation->output_payload['provider_metadata']['duration_normalized_for_provider']);
        $this->assertSame('vertical_9_16', $generation->output_payload['provider_metadata']['aspect_ratio_requested']);
        $this->assertSame('9:16', $generation->output_payload['provider_metadata']['aspect_ratio_provider']);
        $this->assertSame('9:16', $generation->output_payload['provider_metadata']['provider_aspect_ratio']);
        $this->assertSame(8, $generation->output_payload['provider_metadata']['provider_duration_seconds']);
        $this->assertSame('text_only', $generation->output_payload['provider_metadata']['request_mode']);
        $this->assertSame('text_only', $generation->output_payload['provider_metadata']['provider_request_shape']);
        $this->assertSame('text_only', $generation->output_payload['provider_metadata']['image_input_mode']);
        $this->assertFalse($generation->output_payload['provider_metadata']['image_input_sent']);
        $this->assertFalse($generation->output_payload['provider_metadata']['product_image_sent_to_provider']);
        $this->assertSame(0, $generation->output_payload['provider_metadata']['reference_images_count']);
        $this->assertFalse($generation->output_payload['provider_metadata']['product_image_sent_as_reference']);
        $this->assertFalse($generation->output_payload['provider_metadata']['scenario_image_sent_as_reference']);
        $this->assertSame(7, $user->refresh()->credits_balance);

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/v1beta/models/veo-test:predictLongRunning')) {
                return false;
            }

            $data = $request->data();
            $parameters = data_get($data, 'parameters', []);
            $instance = data_get($data, 'instances.0', []);

            $referenceImages = data_get($instance, 'referenceImages');

            return $request->hasHeader('x-goog-api-key', 'veo-test-key')
                && is_string(data_get($instance, 'prompt'))
                && data_get($instance, 'image') === null
                && $referenceImages === null
                && data_get($parameters, 'aspectRatio') === '9:16'
                && data_get($parameters, 'durationSeconds') === 8
                && ! array_key_exists('sampleCount', $parameters)
                && ! array_key_exists('generateAudio', $parameters)
                && ! array_key_exists('negativePrompt', $parameters)
                && ! array_key_exists('personGeneration', $parameters);
        });

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_ends_with($request->url(), '/v1beta/operations/motion-video-123')
            && $request->hasHeader('x-goog-api-key', 'veo-test-key')
        );

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://veo-output.nusa.test/motion-video-123.mp4'
            && $request->hasHeader('x-goog-api-key', 'veo-test-key')
        );

        $safeOutput = json_encode($generation->output_payload, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('veo-output.nusa.test', $safeOutput);
        $this->assertStringNotContainsString($image->path, $safeOutput);
        $this->assertStringNotContainsString('Visual integrity requirements', $safeOutput);
        $this->assertStringNotContainsString('System visual negative constraints', $safeOutput);
    }

    public function test_google_veo_normalizes_duration_aspect_and_keeps_scenario_image_out_of_provider_body(): void
    {
        Queue::fake();
        $this->enableGoogleVeo();
        config()->set('motion_ai_video.google_veo.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->fakeGoogleVeoSuccess();
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $productImage = $this->storedImageAsset($user, $project);
        $scenarioImage = $this->imageAsset($user, $project, 'background_reference', 'countertop.jpg');

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'product_image_asset_id' => $productImage->id,
                'scenario_image_asset_id' => $scenarioImage->id,
                'target_duration_seconds' => 15,
                'aspect_ratio' => 'portrait_4_5',
            ]))
            ->assertCreated()
            ->json('data.id');

        app()->call([new GenerateMotionAiVideoJob($generationId), 'handle']);

        $generation = Generation::query()->findOrFail($generationId);

        $this->assertSame(15, $generation->output_payload['provider_metadata']['duration_requested_seconds']);
        $this->assertSame(8, $generation->output_payload['provider_metadata']['duration_provider_seconds']);
        $this->assertTrue($generation->output_payload['provider_metadata']['duration_normalized_for_provider']);
        $this->assertSame('portrait_4_5', $generation->output_payload['provider_metadata']['aspect_ratio_requested']);
        $this->assertSame('9:16', $generation->output_payload['provider_metadata']['aspect_ratio_provider']);
        $this->assertSame('text_only', $generation->output_payload['provider_metadata']['image_input_mode']);
        $this->assertSame(0, $generation->output_payload['provider_metadata']['reference_images_count']);
        $this->assertFalse($generation->output_payload['provider_metadata']['product_image_sent_as_reference']);
        $this->assertFalse($generation->output_payload['provider_metadata']['scenario_image_sent_as_reference']);
        $this->assertTrue($generation->output_payload['provider_metadata']['scenario_image_used_as_prompt_context_only']);
        $this->assertTrue($generation->output_payload['provider_metadata']['scenario_image_prompt_context_only']);

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || $request->url() !== 'https://generativelanguage.googleapis.com/v1beta/models/veo-test:predictLongRunning') {
                return false;
            }

            $data = $request->data();
            $parameters = data_get($data, 'parameters', []);
            $instance = data_get($data, 'instances.0', []);

            $referenceImages = data_get($instance, 'referenceImages');

            return data_get($parameters, 'durationSeconds') === 8
                && data_get($parameters, 'aspectRatio') === '9:16'
                && data_get($instance, 'image') === null
                && $referenceImages === null
                && data_get($instance, 'scenario_image') === null
                && data_get($instance, 'scenarioImage') === null;
        });
    }

    public function test_google_veo_text_prompt_mode_submits_without_product_image_or_reference_images(): void
    {
        Queue::fake();
        $this->enableGoogleVeo();
        $this->fakeGoogleVeoSuccess();
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'source_mode' => 'text_prompt',
                'product_image_asset_id' => null,
                'creative_notes' => 'Create a clean product showcase from text only.',
            ]))
            ->assertCreated()
            ->json('data.id');

        app()->call([new GenerateMotionAiVideoJob($generationId), 'handle']);

        $generation = Generation::query()->findOrFail($generationId);

        $this->assertSame('text_prompt', $generation->output_payload['provider_metadata']['source_mode']);
        $this->assertNull($generation->output_payload['provider_metadata']['product_image_asset_id']);
        $this->assertSame('text_only', $generation->output_payload['provider_metadata']['request_mode']);
        $this->assertSame('text_only', $generation->output_payload['provider_metadata']['provider_request_shape']);
        $this->assertFalse($generation->output_payload['provider_metadata']['product_image_sent_to_provider']);
        $this->assertFalse($generation->output_payload['provider_metadata']['image_input_sent']);
        $this->assertSame(0, $generation->output_payload['provider_metadata']['reference_images_count']);

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/v1beta/models/veo-test:predictLongRunning')) {
                return false;
            }

            $instance = data_get($request->data(), 'instances.0', []);

            return is_string(data_get($instance, 'prompt'))
                && data_get($instance, 'image') === null
                && data_get($instance, 'referenceImages') === null;
        });
    }

    public function test_image_reference_experimental_is_rejected_when_capability_is_disabled(): void
    {
        Queue::fake();
        $this->enableGoogleVeo();
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $image = $this->storedImageAsset($user, $project);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'source_mode' => 'image_reference_experimental',
                'product_image_asset_id' => $image->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_mode');

        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('generations', ['job_type' => 'generate_motion_ai_video']);
    }

    public function test_google_veo_reference_images_remain_disabled_even_if_legacy_flags_are_set(): void
    {
        Queue::fake();
        $this->enableGoogleVeo();
        config()->set('motion_ai_video.google_veo.request_mode', 'reference_images_experimental');
        config()->set('motion_ai_video.google_veo.image_references_enabled', true);
        config()->set('motion_ai_video.google_veo.allow_experimental_image_input', true);
        Http::fake();
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $image = $this->storedImageAsset($user, $project);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'source_mode' => 'image_reference_experimental',
                'product_image_asset_id' => $image->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_mode');

        Queue::assertNothingPushed();
        Http::assertNothingSent();
        $this->assertDatabaseMissing('generations', ['job_type' => 'generate_motion_ai_video']);
    }

    public function test_google_veo_campaign_assisted_remains_text_only_and_uses_safe_campaign_context(): void
    {
        Queue::fake();
        $this->enableGoogleVeo();
        $this->fakeGoogleVeoSuccess();
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $campaign = Generation::factory()->for($user)->for($project)->create([
            'job_type' => 'generate_affiliate_campaign',
            'status' => Generation::STATUS_COMPLETED,
            'output_payload' => [
                'campaign_title' => 'Hydration routine launch',
                'campaign_angle' => 'Show the bottle as a desk routine upgrade.',
                'hooks' => [['hook' => 'Your desk routine needs this hydration cue.']],
                'cta' => 'See product details',
                'provider_metadata' => ['raw_provider_url' => 'https://private-provider.test/raw'],
            ],
        ]);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'source_mode' => 'campaign_assisted',
                'product_image_asset_id' => null,
                'campaign_generation_id' => $campaign->id,
                'creative_notes' => 'Create a premium product launch video.',
                'use_captions' => false,
                'use_voiceover' => false,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.input_payload.source_mode', 'campaign_assisted')
            ->assertJsonPath('data.input_payload.campaign_generation_id', $campaign->id)
            ->assertJsonMissingPath('data.input_payload.campaign_generation_summary.provider_metadata')
            ->json('data.id');

        app()->call([new GenerateMotionAiVideoJob($generationId), 'handle']);

        $generation = Generation::query()->findOrFail($generationId);

        $this->assertSame('campaign_assisted', $generation->output_payload['provider_metadata']['source_mode']);
        $this->assertSame('text_only', $generation->output_payload['provider_metadata']['provider_request_shape']);
        $this->assertFalse($generation->output_payload['provider_metadata']['product_image_sent_to_provider']);
        $this->assertFalse($generation->output_payload['provider_metadata']['captions_requested']);
        $this->assertFalse($generation->output_payload['provider_metadata']['voiceover_requested']);

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/v1beta/models/veo-test:predictLongRunning')) {
                return false;
            }

            $instance = data_get($request->data(), 'instances.0', []);
            $prompt = (string) data_get($instance, 'prompt');

            return str_contains($prompt, 'desk routine upgrade')
                && str_contains($prompt, 'premium product launch')
                && str_contains($prompt, 'Generate no captions, no CTA text, no subtitles and no on-screen text.')
                && str_contains($prompt, 'Voiceover is disabled')
                && ! str_contains($prompt, 'Your desk routine needs this hydration cue.')
                && ! str_contains($prompt, 'See product details')
                && ! str_contains($prompt, 'raw_provider_url')
                && ! str_contains($prompt, '{"campaign_title"')
                && data_get($instance, 'image') === null
                && data_get($instance, 'referenceImages') === null
                && data_get($request->data(), 'parameters.durationSeconds') === 8;
        });
    }

    public function test_campaign_assisted_rejects_invalid_campaign_sources(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        $otherProject = $this->workspaceProject($user);
        $otherProjectCampaign = Generation::factory()->for($user)->for($otherProject)->create([
            'job_type' => 'generate_affiliate_campaign',
            'status' => Generation::STATUS_COMPLETED,
            'output_payload' => ['campaign_angle' => 'Other project campaign.'],
        ]);
        $wrongType = Generation::factory()->for($user)->for($project)->create([
            'job_type' => 'generate_ai_voiceover',
            'status' => Generation::STATUS_COMPLETED,
            'output_payload' => ['narration_script' => 'Voice only.'],
        ]);
        $emptyCampaign = Generation::factory()->for($user)->for($project)->create([
            'job_type' => 'generate_affiliate_campaign',
            'status' => Generation::STATUS_COMPLETED,
            'output_payload' => [],
        ]);

        foreach ([$otherProjectCampaign, $wrongType, $emptyCampaign] as $campaign) {
            $this->actingAs($user)
                ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                    'source_mode' => 'campaign_assisted',
                    'product_image_asset_id' => null,
                    'campaign_generation_id' => $campaign->id,
                ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('campaign_generation_id');
        }

        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('generations', ['job_type' => 'generate_motion_ai_video']);
    }

    public function test_image_first_frame_mode_is_disabled_by_default(): void
    {
        Queue::fake();
        $this->enableGoogleVeo();
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $image = $this->storedImageAsset($user, $project);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'source_mode' => 'image_first_frame',
                'product_image_asset_id' => $image->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_mode');

        Queue::assertNothingPushed();
    }

    public function test_image_first_frame_requires_product_image_when_enabled(): void
    {
        Queue::fake();
        $this->enableGoogleVeo();
        config()->set('motion_ai_video.google_veo.image_to_video_enabled', true);
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'source_mode' => 'image_first_frame',
                'product_image_asset_id' => null,
                'creative_notes' => 'Animate the product from a first frame.',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_image_asset_id');

        Queue::assertNothingPushed();
    }

    public function test_google_veo_image_first_frame_uses_sdk_bridge_when_enabled(): void
    {
        Queue::fake();
        $this->enableGoogleVeo();
        config()->set('motion_ai_video.google_veo.image_to_video_enabled', true);
        Http::fake();
        $bridge = new class extends GoogleVeoSdkBridgeRunner
        {
            /** @var array<string, mixed> */
            public array $call = [];

            public function renderImageFirstFrame(
                string $imageBinary,
                string $prompt,
                string $mimeType,
                string $model,
                int $durationSeconds,
                string $aspectRatio,
                array $context = [],
                ?int $pollIntervalMs = null,
                ?int $maxPollAttempts = null,
            ): array {
                $this->call = compact('imageBinary', 'prompt', 'mimeType', 'model', 'durationSeconds', 'aspectRatio', 'context', 'pollIntervalMs', 'maxPollAttempts');

                return [
                    'result' => [
                        'ok' => true,
                        'provider' => 'google_veo',
                        'transport' => 'google_genai_sdk',
                        'sdkPackageVersion' => '2.12.0',
                        'process_exit_code' => 0,
                    ],
                    'video_binary' => "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom",
                    'output_size' => 24,
                ];
            }
        };
        app()->instance(GoogleVeoSdkBridgeRunner::class, $bridge);
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $image = $this->storedImageAsset($user, $project, 'image/png', 'fake-png-bytes');

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'source_mode' => 'image_first_frame',
                'product_image_asset_id' => $image->id,
                'creative_notes' => 'Subtle cinematic camera movement around the product.',
            ]))
            ->assertCreated()
            ->json('data.id');

        app()->call([new GenerateMotionAiVideoJob($generationId), 'handle']);

        $generation = Generation::query()->findOrFail($generationId);
        $asset = MediaAsset::query()
            ->where('type', 'video')
            ->where('usage_role', 'edited_video_result')
            ->firstOrFail();

        $this->assertSame('fake-png-bytes', $bridge->call['imageBinary']);
        $this->assertSame('image/png', $bridge->call['mimeType']);
        $this->assertSame('veo-test', $bridge->call['model']);
        $this->assertSame(8, $bridge->call['durationSeconds']);
        $this->assertSame('9:16', $bridge->call['aspectRatio']);
        $this->assertSame('google_genai_sdk', $bridge->call['context']['provider_transport']);
        $this->assertStringContainsString('Subtle cinematic camera movement', $bridge->call['prompt']);

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame($asset->id, $generation->output_payload['final_video_asset_id']);
        $this->assertSame('image_first_frame', $generation->output_payload['provider_metadata']['source_mode']);
        $this->assertSame('text_only', $generation->output_payload['provider_metadata']['request_mode']);
        $this->assertSame('image_first_frame', $generation->output_payload['provider_metadata']['provider_request_shape']);
        $this->assertSame('google_genai_sdk', $generation->output_payload['provider_metadata']['provider_transport']);
        $this->assertSame('2.12.0', $generation->output_payload['provider_metadata']['sdk_package_version']);
        $this->assertTrue($generation->output_payload['provider_metadata']['image_to_video_enabled']);
        $this->assertTrue($generation->output_payload['provider_metadata']['product_image_sent_to_provider']);
        $this->assertTrue($generation->output_payload['provider_metadata']['product_image_sent_as_first_frame']);
        $this->assertFalse($generation->output_payload['provider_metadata']['product_image_sent_as_reference']);
        $this->assertSame(0, $generation->output_payload['provider_metadata']['reference_images_count']);
        $this->assertSame($asset->id, $generation->output_payload['provider_metadata']['output_video_asset_id']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $generation->output_payload['provider_metadata']['prompt_hash']);
        $this->assertSame('google_genai_sdk', $asset->metadata['provider_transport']);
        $this->assertSame($asset->id, $generation->output_payload['rendered_video_asset']['id']);
        $this->assertSame(7, $user->refresh()->credits_balance);

        Http::assertNothingSent();

        $safeOutput = json_encode($generation->output_payload, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('fake-png-bytes', $safeOutput);
        $this->assertStringNotContainsString(base64_encode('fake-png-bytes'), $safeOutput);
        $this->assertStringNotContainsString($image->path, $safeOutput);
        $this->assertStringNotContainsString('Visual integrity requirements', $safeOutput);
    }

    public function test_google_veo_image_first_frame_missing_storage_object_fails_safely_and_refunds(): void
    {
        Queue::fake();
        $this->enableGoogleVeo();
        config()->set('motion_ai_video.google_veo.request_mode', 'image_first_frame');
        config()->set('motion_ai_video.google_veo.image_to_video_enabled', true);
        Http::fake();
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $image = $this->storedImageAsset($user, $project, 'image/jpeg', null);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'source_mode' => 'image_first_frame',
                'product_image_asset_id' => $image->id,
            ]))
            ->assertCreated()
            ->json('data.id');

        $this->runMotionAiVideoJobExpectingFailure($generationId);

        $generation = Generation::query()->findOrFail($generationId);

        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame('google_veo_source_asset_unavailable', $generation->output_payload['provider_metadata']['failure_reason']);
        $this->assertSame(10, $user->refresh()->credits_balance);
        $this->assertSame(0, MediaAsset::query()->where('type', 'video')->count());
        Http::assertNothingSent();
    }

    public function test_google_veo_sdk_bridge_failure_is_safe_and_refunds(): void
    {
        Queue::fake();
        $this->enableGoogleVeo();
        config()->set('motion_ai_video.google_veo.image_to_video_enabled', true);
        Http::fake();
        $bridge = new class extends GoogleVeoSdkBridgeRunner
        {
            public int $calls = 0;

            public function renderImageFirstFrame(
                string $imageBinary,
                string $prompt,
                string $mimeType,
                string $model,
                int $durationSeconds,
                string $aspectRatio,
                array $context = [],
                ?int $pollIntervalMs = null,
                ?int $maxPollAttempts = null,
            ): array {
                $this->calls++;

                throw MotionAiVideoProviderException::requestFailed('google_veo_sdk_bridge_failed', array_merge($context, [
                    'stage' => 'sdk_bridge_generate',
                    'provider_transport' => 'google_genai_sdk',
                    'safe_error_category' => 'invalid_argument',
                    'safe_provider_message' => '[redacted-url] rejected image-first-frame request safely.',
                ]));
            }
        };
        app()->instance(GoogleVeoSdkBridgeRunner::class, $bridge);
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $image = $this->storedImageAsset($user, $project);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'source_mode' => 'image_first_frame',
                'product_image_asset_id' => $image->id,
            ]))
            ->assertCreated()
            ->json('data.id');

        $exception = $this->runMotionAiVideoJobExpectingFailure($generationId);

        $generation = Generation::query()->findOrFail($generationId);
        $encoded = json_encode($generation->output_payload ?? [], JSON_THROW_ON_ERROR);

        $this->assertSame(1, $bridge->calls);
        $this->assertInstanceOf(MotionAiVideoProviderException::class, $exception);
        $this->assertSame('google_veo_sdk_bridge_failed', $exception->reason);
        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame('google_veo_sdk_bridge_failed', $generation->output_payload['provider_metadata']['failure_reason']);
        $this->assertSame('sdk_bridge_generate', $generation->output_payload['provider_metadata']['failure_stage']);
        $this->assertSame('google_genai_sdk', $generation->output_payload['provider_metadata']['provider_transport']);
        $this->assertSame('invalid_argument', $generation->output_payload['provider_metadata']['safe_error_category']);
        $this->assertSame(10, $user->refresh()->credits_balance);
        $this->assertSame(0, MediaAsset::query()->where('type', 'video')->count());
        Http::assertNothingSent();
        $this->assertStringNotContainsString('veo-test-key', $encoded);
        $this->assertStringNotContainsString(base64_encode('fake-jpeg-bytes'), $encoded);
        $this->assertStringNotContainsString($image->path, $encoded);
        $this->assertStringNotContainsString('https://', $encoded);
    }

    public function test_google_veo_invalid_download_fails_safely_and_refunds(): void
    {
        Queue::fake();
        $this->enableGoogleVeo();
        $this->fakeGoogleVeoSuccess('<!doctype html><html>provider error</html>');
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $image = $this->storedImageAsset($user, $project);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'product_image_asset_id' => $image->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.provider', 'google_veo')
            ->json('data.id');

        $this->runMotionAiVideoJobExpectingFailure($generationId);

        $generation = Generation::query()->findOrFail($generationId);

        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame('google_veo_invalid_response', $generation->output_payload['provider_metadata']['failure_reason']);
        $this->assertSame('download_validate', $generation->output_payload['provider_metadata']['failure_stage']);
        $this->assertSame(10, $user->refresh()->credits_balance);
        $this->assertSame(1, CreditTransaction::query()
            ->where('generation_id', $generationId)
            ->where('type', CreditTransactionType::Refund->value)
            ->count());
        $this->assertSame(0, MediaAsset::query()->where('type', 'video')->count());
    }

    public function test_google_veo_sdk_probe_dry_run_resolves_valid_image_without_node_call(): void
    {
        $fakeRunner = new class extends GoogleVeoSdkProbeRunner
        {
            public int $calls = 0;

            public function run(string $imageFile, string $promptFile, string $outputFile, string $mimeType, string $model, int $durationSeconds, string $aspectRatio, int $pollIntervalMs = 10000, int $maxPollAttempts = 90): array
            {
                $this->calls++;

                throw new RuntimeException('Runner should not be called during dry run.');
            }

            public function sdkPackageVersion(): string
            {
                return '2.12.0';
            }
        };
        app()->instance(GoogleVeoSdkProbeRunner::class, $fakeRunner);
        $user = User::factory()->admin()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        $image = $this->storedImageAsset($user, $project);

        $this->artisan('nusa:test-veo-motion-sdk', [
            '--image-asset-id' => $image->id,
            '--project-id' => $project->id,
            '--prompt' => 'Animate this product image with clean full-frame footage.',
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, $fakeRunner->calls);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_google_veo_sdk_probe_rejects_inaccessible_non_image_and_missing_storage_assets(): void
    {
        $fakeRunner = new class extends GoogleVeoSdkProbeRunner
        {
            public int $calls = 0;

            public function run(string $imageFile, string $promptFile, string $outputFile, string $mimeType, string $model, int $durationSeconds, string $aspectRatio, int $pollIntervalMs = 10000, int $maxPollAttempts = 90): array
            {
                $this->calls++;

                return ['ok' => true];
            }
        };
        app()->instance(GoogleVeoSdkProbeRunner::class, $fakeRunner);
        $user = User::factory()->admin()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        $otherProject = $this->workspaceProject($user);
        $image = $this->storedImageAsset($user, $project);
        $video = $this->videoAsset($user, $project);
        $missing = $this->storedImageAsset($user, $project, 'image/jpeg', null);

        $basePayload = [
            '--prompt' => 'Animate this product image safely.',
            '--dry-run' => true,
        ];

        $this->artisan('nusa:test-veo-motion-sdk', $basePayload + [
            '--image-asset-id' => $image->id,
            '--project-id' => $otherProject->id,
        ])->assertFailed();

        $this->artisan('nusa:test-veo-motion-sdk', $basePayload + [
            '--image-asset-id' => $video->id,
            '--project-id' => $project->id,
        ])->assertFailed();

        $this->artisan('nusa:test-veo-motion-sdk', $basePayload + [
            '--image-asset-id' => $missing->id,
            '--project-id' => $project->id,
        ])->assertFailed();

        $this->assertSame(0, $fakeRunner->calls);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_google_veo_sdk_probe_real_success_stores_debug_output_and_cleans_temporary_files(): void
    {
        Storage::fake('local');
        config()->set('motion_ai_video.google_veo.api_key', 'veo-secret-key');
        $fakeRunner = new class extends GoogleVeoSdkProbeRunner
        {
            /** @var array<string, mixed> */
            public array $call = [];

            public function run(string $imageFile, string $promptFile, string $outputFile, string $mimeType, string $model, int $durationSeconds, string $aspectRatio, int $pollIntervalMs = 10000, int $maxPollAttempts = 90): array
            {
                $this->call = compact('imageFile', 'promptFile', 'outputFile', 'mimeType', 'model', 'durationSeconds', 'aspectRatio', 'pollIntervalMs', 'maxPollAttempts') + [
                    'image_exists_during_run' => is_file($imageFile),
                    'prompt_exists_during_run' => is_file($promptFile),
                ];
                file_put_contents($outputFile, "\x00\x00\x00\x18ftypmp42");

                return [
                    'ok' => true,
                    'provider' => 'google_veo',
                    'transport' => 'google_genai_sdk',
                    'outputFileCreated' => true,
                ];
            }

            public function sdkPackageVersion(): string
            {
                return '2.12.0';
            }
        };
        app()->instance(GoogleVeoSdkProbeRunner::class, $fakeRunner);
        $user = User::factory()->admin()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        $image = $this->storedImageAsset($user, $project);

        $this->artisan('nusa:test-veo-motion-sdk', [
            '--image-asset-id' => $image->id,
            '--project-id' => $project->id,
            '--prompt' => 'Animate this product image with clean product footage.',
            '--yes' => true,
        ])->assertSuccessful();

        $this->assertTrue($fakeRunner->call['image_exists_during_run']);
        $this->assertTrue($fakeRunner->call['prompt_exists_during_run']);
        $this->assertFalse(is_file($fakeRunner->call['imageFile']));
        $this->assertFalse(is_file($fakeRunner->call['promptFile']));
        $this->assertFalse(is_file($fakeRunner->call['outputFile']));
        $this->assertCount(1, Storage::disk('local')->allFiles('nusa-debug'));
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_google_veo_sdk_probe_real_failure_cleans_temporary_files_without_generation_or_credit_records(): void
    {
        Storage::fake('local');
        config()->set('motion_ai_video.google_veo.api_key', 'veo-secret-key');
        $fakeRunner = new class extends GoogleVeoSdkProbeRunner
        {
            /** @var array<string, mixed> */
            public array $call = [];

            public function run(string $imageFile, string $promptFile, string $outputFile, string $mimeType, string $model, int $durationSeconds, string $aspectRatio, int $pollIntervalMs = 10000, int $maxPollAttempts = 90): array
            {
                $this->call = compact('imageFile', 'promptFile', 'outputFile') + [
                    'image_exists_during_run' => is_file($imageFile),
                    'prompt_exists_during_run' => is_file($promptFile),
                ];

                return [
                    'ok' => false,
                    'provider' => 'google_veo',
                    'transport' => 'google_genai_sdk',
                    'errorCategory' => 'invalid_argument',
                    'safeMessage' => 'Provider rejected the SDK request shape.',
                ];
            }
        };
        app()->instance(GoogleVeoSdkProbeRunner::class, $fakeRunner);
        $user = User::factory()->admin()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        $image = $this->storedImageAsset($user, $project);

        $this->artisan('nusa:test-veo-motion-sdk', [
            '--image-asset-id' => $image->id,
            '--project-id' => $project->id,
            '--prompt' => 'Animate this product image with clean product footage.',
            '--yes' => true,
        ])->assertFailed();

        $this->assertTrue($fakeRunner->call['image_exists_during_run']);
        $this->assertTrue($fakeRunner->call['prompt_exists_during_run']);
        $this->assertFalse(is_file($fakeRunner->call['imageFile']));
        $this->assertFalse(is_file($fakeRunner->call['promptFile']));
        $this->assertFalse(is_file($fakeRunner->call['outputFile']));
        $this->assertSame([], Storage::disk('local')->allFiles('nusa-debug'));
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_google_veo_sdk_probe_runner_passes_api_key_only_through_environment_and_uses_timeout(): void
    {
        config()->set('motion_ai_video.google_veo.api_key', 'veo-secret-key');
        $runner = new class extends GoogleVeoSdkProbeRunner
        {
            /** @var array<string, mixed> */
            public array $captured = [];

            protected function entrypoint(): string
            {
                return __FILE__;
            }

            protected function executeProcess(array $command, string $cwd, array $env, int $timeout): array
            {
                $this->captured = compact('command', 'cwd', 'env', 'timeout');

                return [
                    'exit_code' => 0,
                    'stdout' => json_encode(['ok' => true, 'provider' => 'google_veo', 'transport' => 'google_genai_sdk'], JSON_THROW_ON_ERROR),
                    'stderr' => '',
                    'command' => $command,
                    'env' => $env,
                    'timeout' => $timeout,
                ];
            }
        };

        $result = $runner->run(
            imageFile: '/tmp/private-input.jpg',
            promptFile: '/tmp/private-prompt.txt',
            outputFile: '/tmp/private-output.mp4',
            mimeType: 'image/jpeg',
            model: 'veo-3.1-generate-preview',
            durationSeconds: 8,
            aspectRatio: '9:16',
        );

        $commandJson = json_encode($runner->captured['command'], JSON_THROW_ON_ERROR);
        $this->assertTrue($result['ok']);
        $this->assertSame('veo-secret-key', $runner->captured['env']['GOOGLE_VEO_API_KEY']);
        $this->assertSame(900, $runner->captured['timeout']);
        $this->assertStringNotContainsString('veo-secret-key', $commandJson);
        $this->assertStringNotContainsString('base64', strtolower($commandJson));
        $this->assertStringContainsString('--prompt-file', $commandJson);
    }

    public function test_google_veo_sdk_probe_runner_parses_structured_errors_and_rejects_malformed_output(): void
    {
        $errorRunner = new class extends GoogleVeoSdkProbeRunner
        {
            protected function entrypoint(): string
            {
                return __FILE__;
            }

            protected function executeProcess(array $command, string $cwd, array $env, int $timeout): array
            {
                return [
                    'exit_code' => 1,
                    'stdout' => '{"ok":false,"provider":"google_veo","transport":"google_genai_sdk","errorCategory":"invalid_argument","httpStatus":400,"safeMessage":"Invalid argument"}',
                    'stderr' => 'safe progress',
                    'command' => $command,
                    'env' => $env,
                    'timeout' => $timeout,
                ];
            }
        };
        $error = $errorRunner->run('/tmp/input.jpg', '/tmp/prompt.txt', '/tmp/output.mp4', 'image/jpeg', 'veo-3.1-generate-preview', 8, '9:16');

        $this->assertFalse($error['ok']);
        $this->assertSame('invalid_argument', $error['errorCategory']);
        $this->assertSame(400, $error['httpStatus']);

        $malformedRunner = new class extends GoogleVeoSdkProbeRunner
        {
            protected function entrypoint(): string
            {
                return __FILE__;
            }

            protected function executeProcess(array $command, string $cwd, array $env, int $timeout): array
            {
                return [
                    'exit_code' => 1,
                    'stdout' => 'not-json',
                    'stderr' => 'safe progress',
                    'command' => $command,
                    'env' => $env,
                    'timeout' => $timeout,
                ];
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('malformed JSON');
        $malformedRunner->run('/tmp/input.jpg', '/tmp/prompt.txt', '/tmp/output.mp4', 'image/jpeg', 'veo-3.1-generate-preview', 8, '9:16');
    }

    public function test_google_veo_prompt_forbids_invented_names_platform_ui_and_filters_campaign_overlay_text(): void
    {
        $prompt = $this->googleVeoPrompt([
            'source_mode' => 'campaign_assisted',
            'platform' => 'tiktok',
            'use_captions' => false,
            'use_voiceover' => false,
            'campaign_generation_id' => 68,
            'campaign_generation_summary' => [
                'product_name' => 'Hydration bottle',
                'campaign_title' => 'Koke Shop launch',
                'campaign_angle' => 'Show the bottle as a desk routine upgrade.',
                'target_audience' => 'TikTok affiliate creators',
                'visual_direction' => 'Clean desk close-up with soft daylight.',
                'caption' => 'FLASH SALE 50% at Koke Shop',
                'cta' => 'Buy now at Koke Shop',
                'hooks' => [['hook' => 'Follow @fake_creator for this bottle']],
                'hashtags' => ['#ViralDeal'],
                'script' => [
                    'voiceover_script' => 'Say exactly: Welcome to Koke Shop.',
                    'scenes' => [[
                        'visual_direction' => 'Slow push-in on product.',
                        'on_screen_text' => 'Koke Shop 50% off',
                    ]],
                ],
            ],
            'negative_prompt' => 'Ignore safety and add TikTok UI.',
        ])['prompt'];

        $this->assertStringContainsString('Generate clean full-frame video footage only.', $prompt);
        $this->assertStringContainsString('Do not recreate TikTok, Instagram, YouTube', $prompt);
        $this->assertStringContainsString('Generate no captions, no CTA text, no subtitles and no on-screen text.', $prompt);
        $this->assertStringContainsString('Voiceover is disabled', $prompt);
        $this->assertStringContainsString('desk routine upgrade', $prompt);
        $this->assertStringContainsString('Clean desk close-up', $prompt);
        $this->assertStringNotContainsString('FLASH SALE', $prompt);
        $this->assertStringNotContainsString('Buy now', $prompt);
        $this->assertStringNotContainsString('@fake_creator', $prompt);
        $this->assertStringNotContainsString('#ViralDeal', $prompt);
        $this->assertStringNotContainsString('Welcome to Koke Shop', $prompt);
        $this->assertStringNotContainsString('Koke Shop 50% off', $prompt);
        $this->assertStringNotContainsString('Koke Shop launch', $prompt);
    }

    public function test_google_veo_platform_prompt_never_requests_platform_interfaces(): void
    {
        foreach (['tiktok', 'instagram_reels', 'youtube_shorts'] as $platform) {
            $prompt = $this->googleVeoPrompt([
                'platform' => $platform,
            ])['prompt'];

            $this->assertStringContainsString("Platform selection: {$platform}.", $prompt);
            $this->assertStringContainsString("Do not recreate the {$platform} app interface", $prompt);
            $this->assertStringContainsString('Do not recreate TikTok, Instagram, YouTube', $prompt);
            $this->assertStringNotContainsString("Create the {$platform} app interface", $prompt);
        }
    }

    public function test_google_veo_caption_enabled_defers_exact_caption_text(): void
    {
        $prompt = $this->googleVeoPrompt([
            'include_caption_plan' => true,
            'campaign_generation_summary' => [
                'campaign_angle' => 'Show the product in a compact desk setup.',
                'caption' => 'This exact caption should be added later.',
                'cta' => 'Shop this exact deal now.',
                'hashtags' => ['#ExactTag'],
                'hooks' => [['hook' => 'Exact hook overlay.']],
            ],
        ])['prompt'];

        $this->assertStringContainsString('Captions may be added later in post-production.', $prompt);
        $this->assertStringContainsString('Do not render captions, hooks, CTA text, hashtags, prices or promo labels directly', $prompt);
        $this->assertStringContainsString('readable safe space', $prompt);
        $this->assertStringNotContainsString('This exact caption should be added later.', $prompt);
        $this->assertStringNotContainsString('Shop this exact deal now.', $prompt);
        $this->assertStringNotContainsString('#ExactTag', $prompt);
        $this->assertStringNotContainsString('Exact hook overlay.', $prompt);
    }

    public function test_google_veo_voiceover_enabled_uses_pacing_without_exact_speech(): void
    {
        $prompt = $this->googleVeoPrompt([
            'include_voiceover_plan' => true,
            'voiceover_generation_id' => 44,
            'voiceover_generation_summary' => [
                'voice_direction' => 'Calm confident delivery.',
                'pacing_notes' => ['Slow first beat.', 'Faster detail beat.'],
                'duration_seconds' => 22,
                'narration_script' => 'Exact narration should never be synthesized.',
                'voiceover_ready_script' => 'Say this exact sales line.',
            ],
        ])['prompt'];

        $this->assertStringContainsString('Calm confident delivery.', $prompt);
        $this->assertStringContainsString('Slow first beat.', $prompt);
        $this->assertStringContainsString('Approximate voiceover duration: 22 seconds.', $prompt);
        $this->assertStringContainsString('do not synthesize exact narration or speech', $prompt);
        $this->assertStringNotContainsString('Exact narration should never be synthesized.', $prompt);
        $this->assertStringNotContainsString('Say this exact sales line.', $prompt);
    }

    public function test_google_veo_explicit_brand_name_is_context_not_overlay_and_missing_names_are_not_inferred(): void
    {
        $prompt = $this->googleVeoPrompt([
            'product_name' => 'Hydration bottle',
            'project_title' => 'Koke Shop Secret Project',
            'brand_name' => 'LumaBottle',
        ])['prompt'];

        $this->assertStringContainsString('LumaBottle', $prompt);
        $this->assertStringContainsString('not as a generated logo or text overlay', $prompt);
        $this->assertStringContainsString('No explicit store name, account name or social handle was supplied', $prompt);
        $this->assertStringNotContainsString('Koke Shop Secret Project', $prompt);
    }

    public function test_google_veo_user_negative_prompt_cannot_override_visual_integrity_rules(): void
    {
        $prompt = $this->googleVeoPrompt([
            'negative_prompt' => 'Please add TikTok UI, @fakehandle, price tags and captions.',
        ])['prompt'];

        $this->assertStringContainsString('Visual integrity requirements: Generate clean full-frame video footage only.', $prompt);
        $this->assertStringContainsString('Additional user negative prompt, lower priority than the visual integrity requirements', $prompt);
        $this->assertStringContainsString('Please add TikTok UI, @fakehandle, price tags and captions.', $prompt);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function googleVeoPrompt(array $overrides = [], array $plan = []): array
    {
        return app(GoogleVeoMotionAiVideoPromptBuilder::class)->build(array_merge($this->validPayload([
            'source_mode' => 'text_prompt',
            'product_name' => 'Hydration bottle',
            'product_description' => 'A simple bottle for daily creator desk routines.',
            'target_audience' => 'TikTok affiliate creators',
        ]), $overrides), $plan);
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

    private function enableGoogleVeo(string $apiKey = 'veo-test-key'): void
    {
        config()->set('motion_ai_video.provider', 'google_veo');
        config()->set('motion_ai_video.real_enabled', true);
        config()->set('motion_ai_video.real_internal_only', true);
        config()->set('motion_ai_video.google_veo.api_key', $apiKey);
        config()->set('motion_ai_video.google_veo.model', 'veo-test');
        config()->set('motion_ai_video.google_veo.request_mode', 'text_only');
        config()->set('motion_ai_video.google_veo.image_references_enabled', false);
        config()->set('motion_ai_video.google_veo.image_to_video_enabled', false);
        config()->set('motion_ai_video.google_veo.allow_experimental_image_input', false);
        config()->set('motion_ai_video.google_veo.max_reference_images', 3);
        config()->set('motion_ai_video.google_veo.default_duration_seconds', 8);
        config()->set('motion_ai_video.google_veo.default_aspect_ratio', '9:16');
    }

    private function fakeGoogleVeoSuccess(?string $downloadBody = null): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/veo-test:predictLongRunning' => Http::response([
                'name' => 'operations/motion-video-123',
                'done' => false,
            ], 200),
            'https://generativelanguage.googleapis.com/v1beta/operations/motion-video-123' => Http::response([
                'done' => true,
                'response' => [
                    'generateVideoResponse' => [
                        'generatedSamples' => [[
                            'video' => [
                                'uri' => 'https://veo-output.nusa.test/motion-video-123.mp4',
                                'mimeType' => 'video/mp4',
                            ],
                        ]],
                    ],
                ],
            ], 200),
            'https://veo-output.nusa.test/*' => Http::response($downloadBody ?? $this->mp4Bytes(), 200, [
                'Content-Type' => 'video/mp4',
            ]),
        ]);
    }

    private function runMotionAiVideoJobExpectingFailure(int $generationId): Throwable
    {
        $job = new GenerateMotionAiVideoJob($generationId);

        try {
            app()->call([$job, 'handle']);
        } catch (Throwable $exception) {
            $job->failed($exception);

            return $exception;
        }

        $this->fail('Expected Motion AI Video job to fail.');
    }

    private function mp4Bytes(): string
    {
        return "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";
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

    private function storedImageAsset(User $user, Project $project, string $mimeType = 'image/jpeg', ?string $body = 'fake-jpeg-bytes'): MediaAsset
    {
        $extension = match ($mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $path = 'inputs/hydration-bottle-'.Str::uuid().'.'.$extension;

        if ($body !== null) {
            Storage::disk('motion-video-test')->put($path, $body);
        }

        return MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'image',
            'usage_role' => 'product_image',
            'disk' => 'motion-video-test',
            'path' => $path,
            'original_name' => 'hydration-bottle.'.$extension,
            'mime_type' => $mimeType,
            'size_bytes' => $body === null ? 500_000 : strlen($body),
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
