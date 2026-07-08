<?php

namespace Tests\Feature;

use App\Contracts\AiVoiceoverProvider;
use App\Data\Voiceover\AiVoiceoverRequestData;
use App\Data\Voiceover\AiVoiceoverResultData;
use App\Enums\CreditTransactionType;
use App\Jobs\GenerateAiVoiceoverJob;
use App\Models\CreatorTool;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\AiVoiceoverGenerationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AiVoiceoverGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('voiceover.provider', 'mock');
        config()->set('voiceover.real_enabled', false);
        config()->set('voiceover.real_internal_only', true);
        config()->set('voiceover.max_characters', 1000);
        config()->set('voiceover.elevenlabs.api_key', null);
        config()->set('voiceover.elevenlabs.default_voice_id', null);
        config()->set('voiceover.elevenlabs.default_model_id', 'eleven_flash_v2_5');
    }

    public function test_ai_voiceover_project_can_be_created(): void
    {
        $user = User::factory()->create();
        $payload = $this->voiceoverPayload();

        $this->actingAs($user)
            ->postJson('/api/v1/projects', [
                'title' => $payload['title'],
                'type' => 'ai_voiceover',
                'status' => 'draft',
                'input_payload' => $payload,
                'settings' => [
                    'platform' => 'tiktok',
                    'format' => 'vertical_9_16',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'ai_voiceover')
            ->assertJsonPath('data.input_payload.voice_style', 'natural_creator')
            ->assertJsonPath('data.input_payload.duration_seconds', 30);
    }

    public function test_ai_voiceover_tool_appears_in_public_tools_when_available(): void
    {
        $response = $this->getJson('/api/v1/tools')->assertOk();

        $tool = collect($response->json('data'))->firstWhere('key', 'ai_voiceover');

        $this->assertIsArray($tool);
        $this->assertSame('available', $tool['status']);
        $this->assertSame('/voiceover/new', $tool['route']);
        $this->assertSame(1, $tool['credit_cost']);
        $this->assertSame('New mock', $tool['badge_label']);
    }

    public function test_generation_endpoint_requires_authentication(): void
    {
        $project = $this->voiceoverProject(User::factory()->create());

        $this->postJson("/api/v1/projects/{$project->id}/generate-voiceover")
            ->assertUnauthorized();
    }

    public function test_user_cannot_generate_another_users_voiceover_project(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = $this->voiceoverProject($owner);

        $this->actingAs($otherUser)
            ->postJson("/api/v1/projects/{$project->id}/generate-voiceover")
            ->assertNotFound();

        $this->assertDatabaseCount('generations', 0);
    }

    public function test_wrong_project_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['type' => 'affiliate_campaign']);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-voiceover")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_project_type')
            ->assertJsonPath('message', 'Only AI voiceover projects can generate voiceovers.');

        $this->assertDatabaseCount('generations', 0);
    }

    public function test_unavailable_ai_voiceover_tool_blocks_generation(): void
    {
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->voiceoverProject($user);
        CreatorTool::query()->where('key', 'ai_voiceover')->update(['status' => 'maintenance']);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-voiceover")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'tool_unavailable')
            ->assertJsonPath('message', 'This tool is temporarily unavailable.');

        $this->assertSame(5, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_dynamic_credit_cost_is_charged(): void
    {
        $user = User::factory()->create(['credits_balance' => 8]);
        $project = $this->voiceoverProject($user);
        CreatorTool::query()->where('key', 'ai_voiceover')->update(['credit_cost' => 4]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-voiceover")
            ->assertCreated()
            ->assertJsonPath('data.job_type', 'generate_ai_voiceover')
            ->assertJsonPath('data.credits_charged', 4)
            ->assertJsonPath('meta.credits_balance', 4);

        $transaction = CreditTransaction::query()
            ->where('generation_id', $response->json('data.id'))
            ->firstOrFail();

        $this->assertSame(CreditTransactionType::Usage, $transaction->type);
        $this->assertSame(4, $transaction->amount);
        $this->assertSame(4, $transaction->balance_after);
        $this->assertSame('AI voiceover generation', $transaction->reason);
        $this->assertSame(4, $user->refresh()->credits_balance);
    }

    public function test_insufficient_credits_block_dispatch_and_do_not_create_generation(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 2]);
        $project = $this->voiceoverProject($user);
        CreatorTool::query()->where('key', 'ai_voiceover')->update(['credit_cost' => 3]);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-voiceover")
            ->assertPaymentRequired()
            ->assertJsonPath('code', 'insufficient_credits')
            ->assertJsonPath('available_credits', 2)
            ->assertJsonPath('required_credits', 3)
            ->assertJsonPath('message', 'You do not have enough platform credits to generate this voiceover.');

        Queue::assertNothingPushed();
        $this->assertSame(2, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_unlimited_user_is_charged_zero(): void
    {
        $user = User::factory()->internalCreator()->create(['credits_balance' => 0]);
        $project = $this->voiceoverProject($user);
        CreatorTool::query()->where('key', 'ai_voiceover')->update(['credit_cost' => 3]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-voiceover")
            ->assertCreated()
            ->assertJsonPath('data.credits_charged', 0)
            ->assertJsonPath('meta.credits_balance', 0);

        $transaction = CreditTransaction::query()
            ->where('generation_id', $response->json('data.id'))
            ->firstOrFail();

        $this->assertSame(CreditTransactionType::Usage, $transaction->type);
        $this->assertSame(0, $transaction->amount);
        $this->assertSame(0, $transaction->balance_after);
        $this->assertTrue($transaction->metadata['unlimited_access']);
    }

    public function test_queued_job_completes_and_stores_voiceover_output(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->voiceoverProject($user);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-voiceover")
            ->assertCreated()
            ->assertJsonPath('data.status', Generation::STATUS_QUEUED)
            ->assertJsonPath('data.output_payload', null)
            ->json('data.id');

        Queue::assertPushed(
            GenerateAiVoiceoverJob::class,
            fn (GenerateAiVoiceoverJob $job): bool => $job->generationId === $generationId,
        );

        $job = new GenerateAiVoiceoverJob($generationId);
        app()->call([$job, 'handle']);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame('mock', $generation->provider);
        $this->assertArrayHasKey('voiceover_title', $generation->output_payload);
        $this->assertArrayHasKey('mock_audio', $generation->output_payload);
        $this->assertSame('placeholder', $generation->output_payload['mock_audio']['status']);
        $this->assertNotNull($generation->started_at);
        $this->assertNotNull($generation->finished_at);
    }

    public function test_generation_history_and_detail_return_voiceover_output(): void
    {
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->voiceoverProject($user);
        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-voiceover")
            ->assertCreated()
            ->json('data.id');

        $this->getJson("/api/v1/projects/{$project->id}/generations")
            ->assertOk()
            ->assertJsonPath('data.0.id', $generationId)
            ->assertJsonPath('data.0.job_type', 'generate_ai_voiceover')
            ->assertJsonPath('data.0.output_payload.mock_audio.provider', 'mock');

        $this->getJson("/api/v1/generations/{$generationId}")
            ->assertOk()
            ->assertJsonPath('data.id', $generationId)
            ->assertJsonPath('data.output_payload.mock_audio.message', 'Real audio generation will be connected later.')
            ->assertJsonPath('data.result_payload.mock_audio.provider', 'mock');
    }

    public function test_failed_job_uses_safe_error_and_refunds_charge_once(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 2]);
        $project = $this->voiceoverProject($user);
        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-voiceover")
            ->assertCreated()
            ->json('data.id');

        $this->app->bind(AiVoiceoverProvider::class, fn () => new class implements AiVoiceoverProvider
        {
            public function generate(AiVoiceoverRequestData $data): AiVoiceoverResultData
            {
                throw new RuntimeException('SQLSTATE leaked path /var/www/provider.php:99');
            }
        });

        $job = new GenerateAiVoiceoverJob($generationId);
        $exception = null;

        try {
            $job->handle(app(AiVoiceoverGenerationProcessor::class));
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
        $this->assertStringNotContainsString('/var/www', $generation->error_message);
        $this->assertSame(2, $user->refresh()->credits_balance);
        $this->assertSame(1, CreditTransaction::query()
            ->where('generation_id', $generationId)
            ->where('type', CreditTransactionType::Refund->value)
            ->count());
    }

    public function test_spanish_and_indonesian_voiceover_output_remains_valid_utf8(): void
    {
        $spanishUser = User::factory()->create(['credits_balance' => 2]);
        $spanishProject = $this->voiceoverProject($spanishUser, 'spanish', [
            'title' => 'Voz para Cámara Ñandú',
            'source_text' => '¿Quieres crear contenido rápido? Explica la cámara sin prometer resultados mágicos.',
            'product_name' => 'Cámara Pequeña Ñandú',
            'product_description' => 'Diseñada para creadores que necesitan automatización rápida.',
            'target_audience' => 'Pequeños creadores en España',
            'tone' => 'professional',
        ]);

        $spanishPayload = $this->actingAs($spanishUser)
            ->postJson("/api/v1/projects/{$spanishProject->id}/generate-voiceover")
            ->assertCreated()
            ->json('data.output_payload');

        $spanishJson = json_encode($spanishPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        foreach (['á', 'Ñ', 'ñ', '¿', '¡'] as $character) {
            $this->assertStringContainsString($character, $spanishJson);
        }
        foreach (['Ã¡', 'Ã±', 'Â¿', 'Â¡'] as $mojibake) {
            $this->assertStringNotContainsString($mojibake, $spanishJson);
        }

        $indonesianUser = User::factory()->create(['credits_balance' => 2]);
        $indonesianProject = $this->voiceoverProject($indonesianUser, 'indonesian', [
            'title' => 'Narasi produk kreator',
            'source_text' => 'Bantu kreator menjelaskan produk dengan jelas dan santai.',
            'product_name' => 'Lampu Kreator Portabel',
        ]);

        $indonesianPayload = $this->actingAs($indonesianUser)
            ->postJson("/api/v1/projects/{$indonesianProject->id}/generate-voiceover")
            ->assertCreated()
            ->json('data.output_payload');

        $indonesianJson = json_encode($indonesianPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Sebelum lanjut scroll', $indonesianJson);
        $this->assertStringContainsString('Lampu Kreator Portabel', $indonesianJson);
        foreach (['Ã', 'Â'] as $mojibake) {
            $this->assertStringNotContainsString($mojibake, $indonesianJson);
        }
    }

    public function test_default_voiceover_provider_remains_mock_when_real_provider_is_disabled(): void
    {
        Queue::fake();
        config()->set('voiceover.provider', 'elevenlabs');
        config()->set('voiceover.real_enabled', false);

        $user = User::factory()->internalCreator()->create(['credits_balance' => 5]);
        $project = $this->voiceoverProject($user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-voiceover")
            ->assertCreated()
            ->assertJsonPath('data.provider', 'mock');
    }

    public function test_elevenlabs_provider_is_selected_only_when_enabled_for_internal_users(): void
    {
        Queue::fake();
        config()->set('voiceover.provider', 'elevenlabs');
        config()->set('voiceover.real_enabled', true);
        config()->set('voiceover.real_internal_only', true);
        config()->set('voiceover.elevenlabs.api_key', 'local-test-key');
        config()->set('voiceover.elevenlabs.default_voice_id', 'voice_test');

        $user = User::factory()->internalCreator()->create(['credits_balance' => 5]);
        $project = $this->voiceoverProject($user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-voiceover")
            ->assertCreated()
            ->assertJsonPath('data.provider', 'elevenlabs');
    }

    public function test_internal_only_real_provider_falls_back_to_mock_for_normal_users(): void
    {
        Queue::fake();
        config()->set('voiceover.provider', 'elevenlabs');
        config()->set('voiceover.real_enabled', true);
        config()->set('voiceover.real_internal_only', true);
        config()->set('voiceover.elevenlabs.api_key', 'local-test-key');
        config()->set('voiceover.elevenlabs.default_voice_id', 'voice_test');

        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->voiceoverProject($user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-voiceover")
            ->assertCreated()
            ->assertJsonPath('data.provider', 'mock');
    }

    public function test_voiceover_max_characters_is_enforced_before_dispatch(): void
    {
        Queue::fake();
        config()->set('voiceover.max_characters', 20);

        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->voiceoverProject($user, 'english', [
            'source_text' => 'This source script is longer than twenty characters.',
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-voiceover")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_text');

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_source_generation_id_must_belong_to_current_user(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $user = User::factory()->create(['credits_balance' => 5]);
        $sourceGeneration = Generation::factory()->for($owner)->create([
            'job_type' => 'generate_affiliate_campaign',
            'status' => Generation::STATUS_COMPLETED,
            'output_payload' => [
                'narration_script' => 'Do not let another user reuse this script.',
            ],
        ]);
        $project = $this->voiceoverProject($user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-voiceover", [
                'source_generation_id' => $sourceGeneration->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_generation_id');

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('generations', 1);
    }

    public function test_source_generation_text_is_stored_for_current_user_source(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $sourceGeneration = Generation::factory()->for($user)->create([
            'job_type' => 'generate_affiliate_campaign',
            'status' => Generation::STATUS_COMPLETED,
            'output_payload' => [
                'script' => [
                    'scenes' => [
                        ['voiceover' => 'Open with a clear creator hook.'],
                        ['voiceover' => 'Explain the product without exaggeration.'],
                    ],
                ],
            ],
        ]);
        $project = $this->voiceoverProject($user, 'english', ['source_text' => null]);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-voiceover", [
                'source_generation_id' => $sourceGeneration->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame($sourceGeneration->id, $generation->input_payload['source_generation_id']);
        $this->assertStringContainsString('Open with a clear creator hook.', $generation->input_payload['source_text']);
        $this->assertStringContainsString('Explain the product without exaggeration.', $generation->input_payload['source_text']);
    }

    public function test_real_elevenlabs_voiceover_generation_stores_audio_media_asset(): void
    {
        Queue::fake();
        Storage::fake('media-test');
        config()->set('media.disk', 'media-test');
        config()->set('voiceover.provider', 'elevenlabs');
        config()->set('voiceover.real_enabled', true);
        config()->set('voiceover.real_internal_only', true);
        config()->set('voiceover.elevenlabs.api_key', 'local-test-key');
        config()->set('voiceover.elevenlabs.default_voice_id', 'voice_test');
        config()->set('voiceover.elevenlabs.default_model_id', 'eleven_flash_v2_5');

        Http::fake([
            'https://api.elevenlabs.io/*' => Http::response('FAKE_MP3_BYTES', 200, [
                'Content-Type' => 'audio/mpeg',
                'request-id' => 'req_test_123',
            ]),
        ]);

        $user = User::factory()->internalCreator()->create(['credits_balance' => 5]);
        $project = $this->voiceoverProject($user, 'english', [
            'source_text' => str_repeat('a', 500),
        ]);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-voiceover")
            ->assertCreated()
            ->assertJsonPath('data.provider', 'elevenlabs')
            ->json('data.id');

        $job = new GenerateAiVoiceoverJob($generationId);
        $job->handle(app(AiVoiceoverGenerationProcessor::class));

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame('elevenlabs', $generation->provider);
        $this->assertSame('generated', $generation->output_payload['audio']['status']);
        $this->assertSame('elevenlabs', $generation->output_payload['audio']['provider']);
        $this->assertSame('eleven_flash_v2_5', $generation->output_payload['audio']['provider_model']);
        $this->assertSame('voice_test', $generation->output_payload['audio']['provider_voice_id']);
        $this->assertSame(500, $generation->output_payload['audio']['character_count']);
        $this->assertEqualsWithDelta(0.025, $generation->output_payload['audio']['estimated_cost_usd'], 0.000001);
        $this->assertSame(
            "/api/v1/media-assets/{$generation->output_payload['audio_asset_id']}/stream",
            $generation->output_payload['audio']['stream_url'],
        );
        $this->assertSame(
            "/api/v1/media-assets/{$generation->output_payload['audio_asset_id']}/download",
            $generation->output_payload['audio']['download_url'],
        );

        $asset = MediaAsset::query()->firstOrFail();
        $this->assertSame($asset->id, $generation->output_payload['audio_asset_id']);
        $this->assertSame('audio', $asset->type);
        $this->assertSame('audio_track', $asset->usage_role);
        $this->assertSame('elevenlabs', $asset->metadata['provider']);
        $this->assertSame($generationId, $asset->metadata['generation_id']);
        Storage::disk('media-test')->assertExists($asset->path);
        $this->assertStringNotContainsString($asset->path, json_encode($generation->output_payload, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('local-test-key', json_encode($generation->output_payload, JSON_THROW_ON_ERROR));
    }

    public function test_missing_elevenlabs_api_key_fails_safely_and_refunds(): void
    {
        Queue::fake();
        config()->set('voiceover.provider', 'elevenlabs');
        config()->set('voiceover.real_enabled', true);
        config()->set('voiceover.real_internal_only', true);
        config()->set('voiceover.elevenlabs.api_key', '');
        config()->set('voiceover.elevenlabs.default_voice_id', 'voice_test');

        $user = User::factory()->internalCreator()->create(['credits_balance' => 2]);
        $project = $this->voiceoverProject($user);
        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-voiceover")
            ->assertCreated()
            ->assertJsonPath('data.provider', 'elevenlabs')
            ->json('data.id');

        $job = new GenerateAiVoiceoverJob($generationId);
        $exception = null;

        try {
            $job->handle(app(AiVoiceoverGenerationProcessor::class));
        } catch (\Throwable $caught) {
            $exception = $caught;
            $job->failed($caught);
        }

        $this->assertNotNull($exception);
        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame('Generation failed due to an internal processing error.', $generation->error_message);
        $this->assertSame(2, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_voice_listing_returns_safe_elevenlabs_metadata_when_enabled(): void
    {
        Cache::flush();
        config()->set('voiceover.provider', 'elevenlabs');
        config()->set('voiceover.real_enabled', true);
        config()->set('voiceover.real_internal_only', true);
        config()->set('voiceover.elevenlabs.api_key', 'local-test-key');

        Http::fake([
            'https://api.elevenlabs.io/v2/voices*' => Http::response([
                'voices' => [[
                    'voice_id' => 'voice_test',
                    'name' => 'Studio Voice',
                    'category' => 'premade',
                    'labels' => ['language' => 'en'],
                    'preview_url' => 'https://example.com/preview.mp3',
                    'settings' => ['stability' => 0.5],
                ]],
            ]),
        ]);

        $response = $this->actingAs(User::factory()->internalCreator()->create())
            ->getJson('/api/v1/voiceover/voices')
            ->assertOk()
            ->assertJsonPath('data.0.provider', 'elevenlabs')
            ->assertJsonPath('data.0.voice_id', 'voice_test')
            ->assertJsonPath('data.0.name', 'Studio Voice');

        $json = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('local-test-key', $json);
        $this->assertStringNotContainsString('settings', $json);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function voiceoverPayload(string $language = 'english', array $overrides = []): array
    {
        return array_merge([
            'title' => 'Portable creator light voiceover',
            'source_text' => 'A short narration for a practical product demo.',
            'product_name' => 'Portable creator light',
            'product_description' => 'A compact light for creator desk setups.',
            'target_audience' => 'Mobile-first creators',
            'language' => $language,
            'voice_style' => 'natural_creator',
            'tone' => 'natural',
            'pace' => 'medium',
            'duration_seconds' => 30,
            'platform' => 'tiktok',
            'notes' => 'Keep it warm and clear.',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function voiceoverProject(User $user, string $language = 'english', array $overrides = []): Project
    {
        return Project::factory()->for($user)->create([
            'title' => $overrides['title'] ?? 'Portable creator light voiceover',
            'type' => 'ai_voiceover',
            'status' => 'draft',
            'input_payload' => $this->voiceoverPayload($language, $overrides),
            'settings' => [
                'platform' => 'tiktok',
                'format' => 'vertical_9_16',
            ],
        ]);
    }
}
