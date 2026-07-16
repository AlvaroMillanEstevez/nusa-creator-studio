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
use App\Services\Generation\MotionAiVideoProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
        config()->set('motion_ai_video.google_veo.allow_experimental_image_input', false);
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
        $this->assertFalse(data_get($action, 'provider_capabilities.image_reference_experimental'));
        $this->assertSame('Google Veo', data_get($action, 'provider_capabilities.provider_label'));
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

    public function test_google_veo_reference_images_request_only_exists_when_experiment_is_enabled(): void
    {
        Queue::fake();
        $this->enableGoogleVeo();
        config()->set('motion_ai_video.google_veo.request_mode', 'reference_images_experimental');
        config()->set('motion_ai_video.google_veo.image_references_enabled', true);
        config()->set('motion_ai_video.google_veo.allow_experimental_image_input', true);
        $this->fakeGoogleVeoSuccess();
        $user = User::factory()->admin()->create(['credits_balance' => 10, 'plan_key' => 'creator']);
        $project = $this->workspaceProject($user);
        $image = $this->storedImageAsset($user, $project);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-motion-ai-video", $this->validPayload([
                'source_mode' => 'image_reference_experimental',
                'product_image_asset_id' => $image->id,
            ]))
            ->assertCreated()
            ->json('data.id');

        app()->call([new GenerateMotionAiVideoJob($generationId), 'handle']);

        $generation = Generation::query()->findOrFail($generationId);

        $this->assertSame('image_reference_experimental', $generation->output_payload['provider_metadata']['source_mode']);
        $this->assertSame('reference_images_experimental', $generation->output_payload['provider_metadata']['request_mode']);
        $this->assertSame('reference_images', $generation->output_payload['provider_metadata']['provider_request_shape']);
        $this->assertTrue($generation->output_payload['provider_metadata']['image_reference_enabled']);
        $this->assertTrue($generation->output_payload['provider_metadata']['product_image_sent_to_provider']);
        $this->assertSame(1, $generation->output_payload['provider_metadata']['reference_images_count']);

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/v1beta/models/veo-test:predictLongRunning')) {
                return false;
            }

            $instance = data_get($request->data(), 'instances.0', []);
            $referenceImages = data_get($instance, 'referenceImages');

            return data_get($instance, 'image') === null
                && is_array($referenceImages)
                && count($referenceImages) === 1
                && data_get($instance, 'referenceImages.0.image.inlineData.mimeType') === 'image/jpeg'
                && data_get($instance, 'referenceImages.0.image.inlineData.data') === base64_encode('fake-jpeg-bytes')
                && data_get($instance, 'referenceImages.0.referenceType') === 'asset';
        });
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
        config()->set('motion_ai_video.google_veo.allow_experimental_image_input', false);
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

    private function runMotionAiVideoJobExpectingFailure(int $generationId): void
    {
        $job = new GenerateMotionAiVideoJob($generationId);

        try {
            app()->call([$job, 'handle']);
        } catch (Throwable $exception) {
            $job->failed($exception);

            return;
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

    private function storedImageAsset(User $user, Project $project): MediaAsset
    {
        $path = 'inputs/hydration-bottle.jpg';

        Storage::disk('motion-video-test')->put($path, 'fake-jpeg-bytes');

        return MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'image',
            'usage_role' => 'product_image',
            'disk' => 'motion-video-test',
            'path' => $path,
            'original_name' => 'hydration-bottle.jpg',
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
