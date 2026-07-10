<?php

namespace Tests\Feature;

use App\Jobs\GenerateVideoToCampaignJob;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\VideoToCampaignGenerationProcessor;
use App\Services\Generation\VideoToCampaignProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GeminiVideoToCampaignProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('video_to_campaign.provider', 'mock');
        config()->set('video_to_campaign.real_enabled', false);
        config()->set('video_to_campaign.real_internal_only', true);
        config()->set('video_to_campaign.gemini.api_key', null);
        config()->set('video_to_campaign.gemini.model', 'gemini-2.5-flash');
        config()->set('video_to_campaign.gemini.base_url', 'https://generativelanguage.googleapis.com');
        config()->set('video_to_campaign.gemini.max_file_size_mb', 50);
        config()->set('video_to_campaign.gemini.max_duration_seconds', 60);
        config()->set('video_to_campaign.gemini.request_timeout_seconds', 180);
        config()->set('video_to_campaign.gemini.file_processing_timeout_seconds', 120);
        config()->set('video_to_campaign.gemini.file_processing_poll_interval_seconds', 1);
        config()->set('video_to_campaign.gemini.input_cost_per_1m_tokens', null);
        config()->set('video_to_campaign.gemini.output_cost_per_1m_tokens', null);
    }

    public function test_provider_manager_defaults_to_mock(): void
    {
        $manager = app(VideoToCampaignProviderManager::class);

        config()->set('video_to_campaign.provider', 'mock');
        config()->set('video_to_campaign.real_enabled', true);

        $this->assertSame('mock', $manager->selectedProviderForUser(User::factory()->admin()->create()));
    }

    public function test_provider_manager_returns_mock_when_real_provider_is_disabled(): void
    {
        $manager = app(VideoToCampaignProviderManager::class);

        config()->set('video_to_campaign.provider', 'gemini');
        config()->set('video_to_campaign.real_enabled', false);
        config()->set('video_to_campaign.gemini.api_key', 'local-test-key');

        $this->assertSame('mock', $manager->selectedProviderForUser(User::factory()->admin()->create()));
    }

    public function test_provider_manager_returns_mock_for_normal_users_when_internal_only(): void
    {
        $manager = app(VideoToCampaignProviderManager::class);

        config()->set('video_to_campaign.provider', 'gemini');
        config()->set('video_to_campaign.real_enabled', true);
        config()->set('video_to_campaign.real_internal_only', true);
        config()->set('video_to_campaign.gemini.api_key', 'local-test-key');

        $this->assertSame('mock', $manager->selectedProviderForUser(User::factory()->create()));
    }

    public function test_provider_manager_returns_gemini_for_eligible_internal_users(): void
    {
        $manager = app(VideoToCampaignProviderManager::class);

        config()->set('video_to_campaign.provider', 'gemini');
        config()->set('video_to_campaign.real_enabled', true);
        config()->set('video_to_campaign.real_internal_only', true);
        config()->set('video_to_campaign.gemini.api_key', 'local-test-key');

        $this->assertSame('gemini', $manager->selectedProviderForUser(User::factory()->admin()->create()));
        $this->assertSame('gemini', $manager->selectedProviderForUser(User::factory()->internalCreator()->create()));
        $this->assertSame('gemini', $manager->selectedProviderForUser(User::factory()->unlimited()->create()));
    }

    public function test_missing_gemini_api_key_fails_safely_and_refunds(): void
    {
        Queue::fake();
        config()->set('video_to_campaign.provider', 'gemini');
        config()->set('video_to_campaign.real_enabled', true);
        config()->set('video_to_campaign.real_internal_only', true);
        config()->set('video_to_campaign.gemini.api_key', '');

        $user = User::factory()->admin()->create(['credits_balance' => 5]);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-video-campaign", [
                'source_video_asset_id' => $sourceVideo->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'gemini')
            ->assertJsonPath('meta.credits_balance', 3)
            ->json('data.id');

        $job = new GenerateVideoToCampaignJob($generationId);
        $exception = null;

        try {
            $job->handle(app(VideoToCampaignGenerationProcessor::class));
        } catch (\Throwable $caught) {
            $exception = $caught;
            $job->failed($caught);
        }

        $this->assertNotNull($exception);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame('Generation failed due to an internal processing error.', $generation->error_message);
        $this->assertSame(5, $user->refresh()->credits_balance);
    }

    public function test_gemini_provider_processes_http_faked_structured_response(): void
    {
        Queue::fake();
        Storage::fake('gemini-test');
        config()->set('video_to_campaign.provider', 'gemini');
        config()->set('video_to_campaign.real_enabled', true);
        config()->set('video_to_campaign.real_internal_only', true);
        config()->set('video_to_campaign.gemini.api_key', 'local-test-key');
        config()->set('video_to_campaign.gemini.input_cost_per_1m_tokens', '0.10');
        config()->set('video_to_campaign.gemini.output_cost_per_1m_tokens', '0.40');

        Http::fake(fn (Request $request) => $this->fakeGeminiResponse($request, $this->validGeminiPayload()));

        $user = User::factory()->admin()->create(['credits_balance' => 5]);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, disk: 'gemini-test');
        Storage::disk('gemini-test')->put($sourceVideo->path, 'FAKE_MP4_BYTES');

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-video-campaign", [
                'source_video_asset_id' => $sourceVideo->id,
                'product_category' => 'Desk accessories',
                'selling_angle' => 'Simple creator desk setup',
            ])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'gemini')
            ->json('data.id');

        $job = new GenerateVideoToCampaignJob($generationId);
        $job->handle(app(VideoToCampaignGenerationProcessor::class));

        $generation = Generation::query()->findOrFail($generationId);

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame('gemini', $generation->provider);
        $this->assertSame('Creator desk light video summary.', $generation->output_payload['video_summary']);
        $this->assertSame('Use the light to improve a creator desk setup.', $generation->output_payload['campaign_angle']);
        $this->assertSame('Open with the before-and-after desk lighting.', $generation->output_payload['hooks'][0]['hook']);
        $this->assertSame('Show the light honestly, then invite viewers to check the product details.', $generation->output_payload['narration_voice_script']);
        $this->assertSame($sourceVideo->id, $generation->output_payload['source_video_asset_id']);
        $this->assertSame('gemini', $generation->output_payload['provider_metadata']['provider']);
        $this->assertSame('gemini-2.5-flash', $generation->output_payload['provider_metadata']['model']);
        $this->assertSame('real', $generation->output_payload['provider_metadata']['mode']);
        $this->assertSame(1000, $generation->output_payload['provider_metadata']['token_usage']['input_tokens']);
        $this->assertSame(250, $generation->output_payload['provider_metadata']['token_usage']['output_tokens']);
        $this->assertEqualsWithDelta(0.0002, $generation->output_payload['provider_metadata']['estimated_cost_usd'], 0.000001);
        $this->assertTrue($generation->output_payload['provider_metadata']['file']['provider_file_deleted']);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/v1beta/models/gemini-2.5-flash:generateContent')
            && data_get($request->data(), 'contents.0.parts.0.fileData.mimeType') === 'video/mp4'
            && data_get($request->data(), 'contents.0.parts.0.fileData.fileUri') === 'gemini://files/test-video'
            && data_get($request->data(), 'generationConfig.responseMimeType') === 'application/json'
            && is_array(data_get($request->data(), 'generationConfig.responseSchema'))
        );

        $json = json_encode($generation->output_payload, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('local-test-key', $json);
        $this->assertStringNotContainsString('gemini://files/test-video', $json);
        $this->assertStringNotContainsString($sourceVideo->path, $json);
    }

    public function test_gemini_invalid_json_fails_safely_and_refunds(): void
    {
        Queue::fake();
        Storage::fake('gemini-test');
        config()->set('video_to_campaign.provider', 'gemini');
        config()->set('video_to_campaign.real_enabled', true);
        config()->set('video_to_campaign.real_internal_only', true);
        config()->set('video_to_campaign.gemini.api_key', 'local-test-key');

        Http::fake(fn (Request $request) => $this->fakeGeminiResponse($request, null, '{not valid json'));

        $user = User::factory()->admin()->create(['credits_balance' => 5]);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, disk: 'gemini-test');
        Storage::disk('gemini-test')->put($sourceVideo->path, 'FAKE_MP4_BYTES');

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-video-campaign", [
                'source_video_asset_id' => $sourceVideo->id,
            ])
            ->assertCreated()
            ->assertJsonPath('meta.credits_balance', 3)
            ->json('data.id');

        $job = new GenerateVideoToCampaignJob($generationId);
        $exception = null;

        try {
            $job->handle(app(VideoToCampaignGenerationProcessor::class));
        } catch (\Throwable $caught) {
            $exception = $caught;
            $job->failed($caught);
        }

        $this->assertNotNull($exception);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame(5, $user->refresh()->credits_balance);
    }

    public function test_gemini_missing_narration_uses_safe_fallback_and_completes(): void
    {
        $payload = $this->validGeminiPayload();
        $payload['narration_voice_script'] = '';

        $generation = $this->processSuccessfulGeminiPayload($payload);

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertNotSame('', $generation->output_payload['narration_voice_script']);
        $this->assertContains('narration_voice_script_fallback_used', $generation->output_payload['provider_metadata']['normalization_warnings']);
    }

    public function test_gemini_edit_plan_as_string_completes_with_fallback_timeline(): void
    {
        $payload = $this->validGeminiPayload();
        $payload['edit_plan'] = 'Open with the product in use, then show one practical detail, then close with a simple CTA.';

        $generation = $this->processSuccessfulGeminiPayload($payload);

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertNotEmpty($generation->output_payload['edit_plan']['timeline']);
        $this->assertSame('Open with the product in use, then show one practical detail, then close with a simple CTA.', $generation->output_payload['edit_plan']['timeline'][0]['instruction']);
        $this->assertTrue($generation->output_payload['provider_metadata']['normalized_with_fallbacks']);
        $this->assertContains('edit_plan_string_normalized', $generation->output_payload['provider_metadata']['normalization_warnings']);
    }

    public function test_gemini_missing_edit_plan_completes_with_fallback_edit_plan(): void
    {
        $payload = $this->validGeminiPayload();
        unset($payload['edit_plan']);

        $generation = $this->processSuccessfulGeminiPayload($payload);

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertNotEmpty($generation->output_payload['edit_plan']['timeline']);
        $this->assertContains('edit_plan_derived_from_recommended_cuts', $generation->output_payload['provider_metadata']['normalization_warnings']);
    }

    public function test_gemini_hooks_as_strings_complete_with_normalized_hooks(): void
    {
        $payload = $this->validGeminiPayload();
        $payload['hooks'] = ['Need a cleaner creator desk setup?'];

        $generation = $this->processSuccessfulGeminiPayload($payload);

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame('Need a cleaner creator desk setup?', $generation->output_payload['hooks'][0]['hook']);
        $this->assertContains('hooks_strings_normalized', $generation->output_payload['provider_metadata']['normalization_warnings']);
    }

    public function test_gemini_recommended_cuts_as_strings_complete_with_normalized_cuts(): void
    {
        $payload = $this->validGeminiPayload();
        $payload['recommended_cuts'] = ['Trim the first pause and start on the product.'];

        $generation = $this->processSuccessfulGeminiPayload($payload);

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame('Trim the first pause and start on the product.', $generation->output_payload['recommended_cuts'][0]['reason']);
        $this->assertContains('recommended_cuts_strings_normalized', $generation->output_payload['provider_metadata']['normalization_warnings']);
    }

    public function test_gemini_markdown_fenced_json_response_completes(): void
    {
        $payload = $this->validGeminiPayload();
        $rawJson = '```json
'.json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).'
```';

        $generation = $this->processSuccessfulGeminiPayload($payload, rawJson: $rawJson);

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame('Creator desk light video summary.', $generation->output_payload['video_summary']);
    }

    public function test_gemini_missing_timestamps_complete_with_null_timing(): void
    {
        $payload = $this->validGeminiPayload();
        unset(
            $payload['detected_product_moments'][0]['start_seconds'],
            $payload['detected_product_moments'][0]['end_seconds'],
            $payload['recommended_cuts'][0]['start_seconds'],
            $payload['recommended_cuts'][0]['end_seconds'],
            $payload['edit_plan']['timeline'][0]['start_seconds'],
            $payload['edit_plan']['timeline'][0]['end_seconds'],
        );

        $generation = $this->processSuccessfulGeminiPayload($payload);

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertNull($generation->output_payload['detected_product_moments'][0]['start_seconds']);
        $this->assertNull($generation->output_payload['edit_plan']['timeline'][0]['start_seconds']);
        $this->assertContains('detected_product_moments_missing_timestamps', $generation->output_payload['provider_metadata']['normalization_warnings']);
    }

    public function test_gemini_completely_empty_response_fails_safely_and_refunds(): void
    {
        Queue::fake();
        Storage::fake('gemini-test');
        config()->set('video_to_campaign.provider', 'gemini');
        config()->set('video_to_campaign.real_enabled', true);
        config()->set('video_to_campaign.real_internal_only', true);
        config()->set('video_to_campaign.gemini.api_key', 'local-test-key');

        Http::fake(fn (Request $request) => $this->fakeGeminiResponse(
            request: $request,
            payload: $this->validGeminiPayload(),
            analysisBody: [],
        ));

        $user = User::factory()->admin()->create(['credits_balance' => 5]);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, disk: 'gemini-test');
        Storage::disk('gemini-test')->put($sourceVideo->path, 'FAKE_MP4_BYTES');

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-video-campaign", [
                'source_video_asset_id' => $sourceVideo->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->runVideoToCampaignJobExpectingFailure($generationId);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame('gemini_unexpected_response_shape', data_get($generation->output_payload, 'provider_metadata.failure_reason'));
        $this->assertSame(5, $user->refresh()->credits_balance);
    }

    #[DataProvider('geminiAnalysisFailureStatuses')]
    public function test_gemini_analysis_http_failure_logs_safe_context_and_refunds(int $status): void
    {
        Queue::fake();
        Storage::fake('gemini-test');
        config()->set('video_to_campaign.provider', 'gemini');
        config()->set('video_to_campaign.real_enabled', true);
        config()->set('video_to_campaign.real_internal_only', true);
        config()->set('video_to_campaign.gemini.api_key', 'local-test-key');

        Log::spy();
        Http::fake(fn (Request $request) => $this->fakeGeminiResponse(
            request: $request,
            payload: $this->validGeminiPayload(),
            analysisStatus: $status,
            analysisBody: [
                'error' => [
                    'code' => $status,
                    'status' => $status === 403 ? 'PERMISSION_DENIED' : 'INVALID_ARGUMENT',
                    'message' => 'Provider rejected gemini://files/test-video while using local-test-key.',
                ],
            ],
        ));

        $user = User::factory()->admin()->create(['credits_balance' => 5]);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, disk: 'gemini-test');
        Storage::disk('gemini-test')->put($sourceVideo->path, 'FAKE_MP4_BYTES');

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-video-campaign", [
                'source_video_asset_id' => $sourceVideo->id,
            ])
            ->assertCreated()
            ->assertJsonPath('meta.credits_balance', 3)
            ->json('data.id');

        $this->runVideoToCampaignJobExpectingFailure($generationId);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame(5, $user->refresh()->credits_balance);
        $this->assertSame('gemini_analysis_request_failed', data_get($generation->output_payload, 'provider_metadata.failure_reason'));
        $this->assertSame($status, data_get($generation->output_payload, 'provider_metadata.http_status'));

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) use ($status): bool {
            $encoded = json_encode($context, JSON_THROW_ON_ERROR);

            return $message === 'Gemini Video-to-Campaign provider request failed.'
                && ($context['stage'] ?? null) === 'request_analysis'
                && ($context['reason'] ?? null) === 'gemini_analysis_request_failed'
                && ($context['http_status'] ?? null) === $status
                && ! str_contains($encoded, 'local-test-key')
                && ! str_contains($encoded, 'gemini://files/test-video');
        });
    }

    public function test_gemini_unexpected_response_shape_fails_safely_and_refunds(): void
    {
        Queue::fake();
        Storage::fake('gemini-test');
        config()->set('video_to_campaign.provider', 'gemini');
        config()->set('video_to_campaign.real_enabled', true);
        config()->set('video_to_campaign.real_internal_only', true);
        config()->set('video_to_campaign.gemini.api_key', 'local-test-key');

        Http::fake(fn (Request $request) => $this->fakeGeminiResponse(
            request: $request,
            payload: $this->validGeminiPayload(),
            analysisBody: [
                'candidates' => [
                    ['content' => ['parts' => [['inlineData' => ['mimeType' => 'text/plain']]]]],
                ],
            ],
        ));

        $user = User::factory()->admin()->create(['credits_balance' => 5]);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, disk: 'gemini-test');
        Storage::disk('gemini-test')->put($sourceVideo->path, 'FAKE_MP4_BYTES');

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-video-campaign", [
                'source_video_asset_id' => $sourceVideo->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->runVideoToCampaignJobExpectingFailure($generationId);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame('gemini_unexpected_response_shape', data_get($generation->output_payload, 'provider_metadata.failure_reason'));
        $this->assertSame(5, $user->refresh()->credits_balance);
    }

    public static function geminiAnalysisFailureStatuses(): array
    {
        return [
            'bad request' => [400],
            'forbidden' => [403],
        ];
    }

    private function fakeGeminiResponse(
        Request $request,
        ?array $payload,
        ?string $rawJson = null,
        int $analysisStatus = 200,
        mixed $analysisBody = null,
    ): mixed {
        $url = $request->url();

        if ($request->method() === 'POST' && str_contains($url, '/upload/v1beta/files')) {
            return Http::response([], 200, [
                'x-goog-upload-url' => 'https://upload.example.test/session',
            ]);
        }

        if ($request->method() === 'POST' && $url === 'https://upload.example.test/session') {
            return Http::response([
                'file' => [
                    'name' => 'files/test-video',
                    'uri' => 'gemini://files/test-video',
                    'state' => 'ACTIVE',
                    'mimeType' => 'video/mp4',
                ],
            ], 200);
        }

        if ($request->method() === 'POST' && str_contains($url, ':generateContent')) {
            if ($analysisBody !== null) {
                return Http::response($analysisBody, $analysisStatus);
            }

            if ($analysisStatus !== 200) {
                return Http::response([
                    'error' => [
                        'code' => $analysisStatus,
                        'message' => 'Gemini request failed.',
                        'status' => 'INVALID_ARGUMENT',
                    ],
                ], $analysisStatus);
            }

            return Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $rawJson ?? json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 1000,
                    'candidatesTokenCount' => 250,
                    'totalTokenCount' => 1250,
                ],
            ], 200);
        }

        if ($request->method() === 'DELETE' && str_contains($url, '/v1beta/files/test-video')) {
            return Http::response([], 200);
        }

        return Http::response([], 500);
    }

    /**
     * @return array<string, mixed>
     */
    private function validGeminiPayload(): array
    {
        return [
            'video_summary' => 'Creator desk light video summary.',
            'observed_product_features' => [
                [
                    'feature' => 'Compact desk light',
                    'evidence' => 'The video shows a small light on a desk.',
                    'confidence' => 'high',
                ],
            ],
            'detected_product_moments' => [
                [
                    'start_seconds' => 0,
                    'end_seconds' => 4,
                    'title' => 'Desk setup reveal',
                    'description' => 'The product appears in a creator desk setup.',
                    'why_it_matters' => 'It quickly shows practical context.',
                    'visual_quality' => 'medium',
                ],
            ],
            'scene_observations' => [
                [
                    'start_seconds' => 0,
                    'end_seconds' => 5,
                    'observation' => 'The desk shot is clear enough for an opening hook.',
                    'usable_for_edit' => true,
                ],
            ],
            'recommended_cuts' => [
                [
                    'start_seconds' => 0,
                    'end_seconds' => 2,
                    'reason' => 'Open directly on the product setup.',
                    'priority' => 'high',
                ],
            ],
            'campaign_angle' => 'Use the light to improve a creator desk setup.',
            'hooks' => [
                [
                    'hook' => 'Open with the before-and-after desk lighting.',
                    'style' => 'problem_solution',
                    'best_for' => 'Creator desk audience',
                ],
            ],
            'narration_voice_script' => 'Show the light honestly, then invite viewers to check the product details.',
            'captions' => ['A cleaner desk lighting setup for creators.'],
            'hashtags' => ['#CreatorSetup', '#DeskLight', '#ProductVideo'],
            'cta_options' => ['Review the product details before buying.'],
            'edit_plan' => [
                'target_duration_seconds' => 30,
                'opening' => 'Start on the product in the desk setup.',
                'middle' => 'Show the light from one or two practical angles.',
                'closing' => 'End with a simple detail-check CTA.',
                'timeline' => [
                    [
                        'start_seconds' => 0,
                        'end_seconds' => 3,
                        'instruction' => 'Open with the clearest desk shot.',
                        'caption' => 'Creator desk lighting idea',
                    ],
                ],
            ],
            'compliance_notes' => ['Verify product specifications before publishing.'],
            'usage_notes' => ['Use as a draft campaign plan.'],
            'missing_information' => ['Exact product specifications are not visible.'],
            'unverified_claims' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processSuccessfulGeminiPayload(array $payload, ?string $rawJson = null, mixed $analysisBody = null): Generation
    {
        Queue::fake();
        Storage::fake('gemini-test');
        config()->set('video_to_campaign.provider', 'gemini');
        config()->set('video_to_campaign.real_enabled', true);
        config()->set('video_to_campaign.real_internal_only', true);
        config()->set('video_to_campaign.gemini.api_key', 'local-test-key');

        Http::fake(fn (Request $request) => $this->fakeGeminiResponse(
            request: $request,
            payload: $payload,
            rawJson: $rawJson,
            analysisBody: $analysisBody,
        ));

        $user = User::factory()->admin()->create(['credits_balance' => 5]);
        $project = $this->workspaceProject($user);
        $sourceVideo = $this->videoAsset($user, $project, disk: 'gemini-test');
        Storage::disk('gemini-test')->put($sourceVideo->path, 'FAKE_MP4_BYTES');

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-video-campaign", [
                'source_video_asset_id' => $sourceVideo->id,
            ])
            ->assertCreated()
            ->assertJsonPath('meta.credits_balance', 3)
            ->json('data.id');

        $job = new GenerateVideoToCampaignJob($generationId);
        $job->handle(app(VideoToCampaignGenerationProcessor::class));

        return Generation::query()->findOrFail($generationId);
    }

    private function runVideoToCampaignJobExpectingFailure(int $generationId): \Throwable
    {
        $job = new GenerateVideoToCampaignJob($generationId);

        try {
            $job->handle(app(VideoToCampaignGenerationProcessor::class));
        } catch (\Throwable $caught) {
            $job->failed($caught);

            return $caught;
        }

        $this->fail('Expected the Video-to-Campaign job to fail.');
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

    private function videoAsset(User $user, Project $project, string $disk = 'local'): MediaAsset
    {
        return MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'video',
            'usage_role' => 'campaign_source_video',
            'disk' => $disk,
            'path' => 'videos/raw-product-demo.mp4',
            'original_name' => 'raw-product-demo.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1_500_000,
            'metadata' => [
                'duration_seconds' => 30,
            ],
        ]);
    }
}
