<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Jobs\GenerateAffiliateCampaignJob;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\CampaignGenerationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Throwable;

class CampaignGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('campaign.provider', 'mock');
        config()->set('campaign.real_enabled', false);
        config()->set('campaign.real_internal_only', true);
        config()->set('campaign.max_input_characters', 4000);
        config()->set('campaign.max_output_tokens', 2500);
        config()->set('campaign.openai.api_key', null);
        config()->set('campaign.openai.campaign_model', 'gpt-5.4-nano');
        config()->set('campaign.openai.campaign_fallback_model', 'gpt-5.4-mini');
        config()->set('campaign.openai.base_url', 'https://api.openai.com/v1');
        config()->set('campaign.openai.input_cost_per_1m_tokens', null);
        config()->set('campaign.openai.output_cost_per_1m_tokens', null);
    }

    public function test_successful_mock_generation_creates_a_completed_generation_and_result(): void
    {
        $user = User::factory()->create(['credits_balance' => 20]);
        $project = $this->affiliateProject($user);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign");

        $response
            ->assertCreated()
            ->assertJsonPath('data.job_type', 'generate_affiliate_campaign')
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.credits_charged', 1)
            ->assertJsonPath('data.input_payload.campaign_input_mode', 'brief_only')
            ->assertJsonPath('data.input_payload.auto_video_editor_eligible', false)
            ->assertJsonPath('data.result_payload.campaign_input_mode', 'brief_only')
            ->assertJsonPath('data.result_payload.auto_video_editor_eligible', false)
            ->assertJsonCount(5, 'data.result_payload.hooks')
            ->assertJsonCount(10, 'data.result_payload.hashtags')
            ->assertJsonStructure([
                'data' => [
                    'result_payload' => [
                        'campaign_title',
                        'product_angle',
                        'target_audience_summary',
                        'hooks' => [['label', 'text', 'intent']],
                        'main_script' => ['duration_seconds', 'voiceover_script', 'on_screen_text', 'scenes'],
                        'voiceover_ready_script',
                        'captions',
                        'hashtags',
                        'cta_options',
                        'compliance_notes',
                        'usage_notes',
                        'provider_metadata',
                        'script' => ['title', 'duration_seconds', 'scenes'],
                        'caption',
                        'cta',
                        'notes',
                    ],
                ],
                'meta' => ['credits_balance'],
            ]);

        $generationId = $response->json('data.id');

        $this->assertDatabaseHas('generations', [
            'id' => $generationId,
            'user_id' => $user->id,
            'project_id' => $project->id,
            'job_type' => 'generate_affiliate_campaign',
            'provider' => 'mock',
            'status' => 'completed',
            'credits_charged' => 1,
        ]);

        $generation = Generation::query()->findOrFail($generationId);

        $this->assertNotNull($generation->started_at);
        $this->assertNotNull($generation->finished_at);
        $this->assertIsString($generation->result_payload['caption']);
        $this->assertIsString($generation->result_payload['cta']);
        $this->assertIsString($generation->result_payload['voiceover_ready_script']);
    }

    public function test_successful_generation_charges_one_credit_and_records_positive_usage(): void
    {
        $user = User::factory()->create(['credits_balance' => 3]);
        $project = $this->affiliateProject($user);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertCreated()
            ->assertJsonPath('meta.credits_balance', 2);

        $generationId = $response->json('data.id');
        $transaction = CreditTransaction::query()->where('generation_id', $generationId)->firstOrFail();

        $this->assertSame(CreditTransactionType::Usage, $transaction->type);
        $this->assertSame(1, $transaction->amount);
        $this->assertSame(2, $transaction->balance_after);
        $this->assertSame('Mock affiliate campaign generation', $transaction->reason);
        $this->assertSame(2, $user->refresh()->credits_balance);
    }

    public function test_insufficient_credits_returns_a_clear_error_without_negative_balance(): void
    {
        $user = User::factory()->create(['credits_balance' => 0]);
        $project = $this->affiliateProject($user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertPaymentRequired()
            ->assertJsonPath('code', 'insufficient_credits')
            ->assertJsonPath('available_credits', 0)
            ->assertJsonPath('required_credits', 1)
            ->assertJsonPath(
                'message',
                'You do not have enough platform credits to generate this campaign.',
            );

        $this->assertSame(0, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_workspace_project_can_generate_campaign_content_from_project_context(): void
    {
        $user = User::factory()->create(['credits_balance' => 2]);
        $project = Project::factory()->for($user)->create([
            'title' => 'Creator desk launch workspace',
            'type' => 'workspace',
            'status' => 'draft',
            'input_payload' => [
                'product_name' => 'Creator desk light',
                'content_idea' => 'Compact creator desk lighting kit',
                'product_description' => 'A compact desk light for mobile-first creators.',
                'target_audience' => 'TikTok affiliate creators',
                'language' => 'english',
                'tone' => 'natural',
                'platform' => 'tiktok',
            ],
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertCreated()
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.job_type', 'generate_affiliate_campaign')
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonPath('meta.credits_balance', 1);
    }

    public function test_product_content_kit_can_be_image_assisted_without_auto_video_editor_eligibility(): void
    {
        $user = User::factory()->create(['credits_balance' => 2]);
        $project = $this->affiliateProject($user);
        $image = MediaAsset::factory()->for($user)->for($project)->create([
            'type' => 'image',
            'usage_role' => 'product_image',
            'original_name' => 'product-photo.jpg',
            'mime_type' => 'image/jpeg',
        ]);
        $project->update([
            'input_payload' => array_merge($project->input_payload, [
                'campaign_input_mode' => 'image_assisted',
            ]),
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertCreated()
            ->assertJsonPath('data.input_payload.campaign_input_mode', 'image_assisted')
            ->assertJsonPath('data.input_payload.source_image_asset_id', $image->id)
            ->assertJsonPath('data.result_payload.campaign_input_mode', 'image_assisted')
            ->assertJsonPath('data.result_payload.source_image_asset_id', $image->id)
            ->assertJsonPath('data.result_payload.auto_video_editor_eligible', false)
            ->assertJsonPath('data.result_payload.has_source_video', false)
            ->assertJsonPath('data.result_payload.has_edit_plan', false);
    }

    public function test_non_affiliate_projects_cannot_generate_campaign_content(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()
            ->for($user)
            ->create(['type' => 'video_enhancer']);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_project_type')
            ->assertJsonPath(
                'message',
                'Only affiliate campaign or workspace projects can use campaign generation.',
            );

        $this->assertDatabaseCount('generations', 0);
        $this->assertSame(20, $user->refresh()->credits_balance);
    }

    public function test_mock_generation_supports_the_three_campaign_languages(): void
    {
        $user = User::factory()->create(['credits_balance' => 3]);

        foreach ([
            'english' => 'Before you scroll',
            'indonesian' => 'Sebelum scroll',
            'spanish' => '¿Quieres crear contenido rápido?',
        ] as $language => $expectedHook) {
            $project = $this->affiliateProject($user, $language);

            $response = $this->actingAs($user)
                ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
                ->assertCreated();

            $hook = $response->json('data.result_payload.hooks.0.text');

            $this->assertIsString($hook);
            $this->assertStringContainsString($expectedHook, $hook);
        }

        $this->assertSame(0, $user->refresh()->credits_balance);
    }

    public function test_spanish_mock_generation_preserves_unicode_without_mojibake(): void
    {
        $user = User::factory()->create(['credits_balance' => 1]);
        $product = 'Cámara Ágil Á É Í Ó Ú, piñata Ñandú';
        $description = 'Diseñado para creadores que necesitan automatización.';
        $audience = 'Pequeños creadores en España';

        $project = Project::factory()
            ->for($user)
            ->create([
                'title' => 'Campaña para pequeños creadores en España',
                'type' => 'affiliate_campaign',
                'status' => 'draft',
                'input_payload' => [
                    'product_name' => $product,
                    'product_description' => $description,
                    'target_audience' => $audience,
                    'language' => 'spanish',
                    'tone' => 'problem_solution',
                ],
                'settings' => [
                    'platform' => 'tiktok',
                    'format' => 'vertical_9_16',
                ],
            ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign");

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.result_payload.hooks.0.text',
                "¿Quieres crear contenido rápido? Mira de cerca {$product}.",
            )
            ->assertJsonPath(
                'data.result_payload.hooks.3.text',
                '¡Una idea sencilla para mostrar un producto que encaja en tu rutina diaria!',
            )
            ->assertJsonPath(
                'data.result_payload.script.scenes.1.on_screen_text',
                '¿Para quién es?',
            )
            ->assertJsonPath(
                'data.result_payload.script.scenes.1.visual_direction',
                "Muestra honestamente este detalle proporcionado por el creador: {$description}.",
            )
            ->assertJsonPath('data.result_payload.hashtags.0', '#CámaraÁgilÁÉÍÓÚpiñataÑandú')
            ->assertJsonPath('data.result_payload.hashtags.2', '#ReseñaDeProducto');

        $result = $response->json('data.result_payload');
        $unicodeJson = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        foreach (['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ', '¿', '?', '¡', '!'] as $character) {
            $this->assertStringContainsString($character, $unicodeJson);
        }

        foreach (['Ã¡', 'Ã±', 'Â¿', 'Â¡'] as $mojibake) {
            $this->assertStringNotContainsString($mojibake, $unicodeJson);
        }
    }

    public function test_configured_openai_provider_is_selected_for_admin_users(): void
    {
        Queue::fake();
        $this->enableOpenAiProvider(apiKey: 'sk-test-campaign');
        $user = User::factory()->admin()->create(['credits_balance' => 5]);
        $project = $this->affiliateProject($user);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertCreated()
            ->assertJsonPath('data.provider', 'openai')
            ->assertJsonPath('data.status', Generation::STATUS_QUEUED)
            ->json('data.id');

        Queue::assertPushed(
            GenerateAffiliateCampaignJob::class,
            fn (GenerateAffiliateCampaignJob $job): bool => $job->generationId === $generationId,
        );
        $this->assertSame(4, $user->refresh()->credits_balance);
    }

    public function test_normal_users_fallback_to_mock_when_real_campaign_provider_is_internal_only(): void
    {
        Queue::fake();
        $this->enableOpenAiProvider(apiKey: 'sk-test-campaign');
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->affiliateProject($user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertCreated()
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonPath('data.status', Generation::STATUS_QUEUED);

        $this->assertSame(4, $user->refresh()->credits_balance);
    }

    public function test_campaign_input_character_limit_blocks_generation_before_charging(): void
    {
        Queue::fake();
        config()->set('campaign.max_input_characters', 50);
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->affiliateProject($user);
        $project->forceFill([
            'input_payload' => [
                ...$project->input_payload,
                'product_description' => str_repeat('Long product description. ', 20),
            ],
        ])->save();

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('input_payload');

        Queue::assertNothingPushed();
        $this->assertSame(5, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_missing_openai_key_fails_safely_and_refunds_without_leaking_secrets(): void
    {
        Queue::fake();
        Http::fake();
        Log::spy();
        $this->enableOpenAiProvider(apiKey: null);
        $user = User::factory()->admin()->create(['credits_balance' => 2]);
        $project = $this->affiliateProject($user);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertCreated()
            ->assertJsonPath('data.provider', 'openai')
            ->json('data.id');

        $exception = $this->runCampaignJobAndFailOnException($generationId);

        $this->assertNotNull($exception);
        Http::assertNothingSent();
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame('Generation failed due to an internal processing error.', $generation->error_message);
        $this->assertSame(2, $user->refresh()->credits_balance);
        $this->assertSame(1, CreditTransaction::query()
            ->where('generation_id', $generationId)
            ->where('type', CreditTransactionType::Refund->value)
            ->count());

        $responseJson = json_encode(
            $this->actingAs($user)->getJson("/api/v1/generations/{$generationId}")->json(),
            JSON_THROW_ON_ERROR,
        );
        $this->assertStringNotContainsString('sk-test', $responseJson);
    }

    public function test_valid_openai_response_saves_structured_campaign_output(): void
    {
        Queue::fake();
        $this->enableOpenAiProvider(apiKey: 'sk-test-campaign');
        config()->set('campaign.openai.input_cost_per_1m_tokens', '0.10');
        config()->set('campaign.openai.output_cost_per_1m_tokens', '0.20');
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_campaign_test',
                'output_text' => json_encode($this->openAiStructuredOutput(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'usage' => [
                    'input_tokens' => 1000,
                    'output_tokens' => 500,
                    'total_tokens' => 1500,
                ],
            ]),
        ]);
        $user = User::factory()->admin()->create(['credits_balance' => 5]);
        $project = $this->affiliateProject($user, 'spanish');

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertCreated()
            ->json('data.id');

        $this->runCampaignJobAndFailOnException($generationId);

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame('openai', $generation->provider);
        $this->assertSame('Guion honesto para Mini Cámara', $generation->output_payload['campaign_title']);
        $this->assertSame('Antes de comprar, revisa cómo esta cámara puede ayudarte en tu rutina.', $generation->output_payload['voiceover_ready_script']);
        $this->assertSame('openai', $generation->output_payload['provider_metadata']['provider']);
        $this->assertSame('gpt-5.4-nano', $generation->output_payload['provider_metadata']['model']);
        $this->assertSame(1000, $generation->output_payload['provider_metadata']['token_usage']['input_tokens']);
        $this->assertSame(0.0002, $generation->output_payload['provider_metadata']['estimated_cost_usd']);
        $this->assertSame('resp_campaign_test', $generation->output_payload['provider_metadata']['response_id']);
        $this->assertStringNotContainsString('sk-test-campaign', json_encode($generation->output_payload, JSON_THROW_ON_ERROR));
    }

    public function test_invalid_openai_response_fails_generation_and_refunds_credit(): void
    {
        Queue::fake();
        $this->enableOpenAiProvider(apiKey: 'sk-test-campaign');
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_bad_json',
                'output_text' => '{not valid json',
            ]),
        ]);
        $user = User::factory()->admin()->create(['credits_balance' => 2]);
        $project = $this->affiliateProject($user);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertCreated()
            ->json('data.id');

        $exception = $this->runCampaignJobAndFailOnException($generationId);

        $this->assertNotNull($exception);
        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame('Generation failed due to an internal processing error.', $generation->error_message);
        $this->assertSame(2, $user->refresh()->credits_balance);
        $this->assertSame(1, CreditTransaction::query()
            ->where('generation_id', $generationId)
            ->where('type', CreditTransactionType::Refund->value)
            ->count());
    }

    public function test_openai_prompt_contains_honest_marketing_safety_instructions(): void
    {
        Queue::fake();
        $this->enableOpenAiProvider(apiKey: 'sk-test-campaign');
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_campaign_test',
                'output_text' => json_encode($this->openAiStructuredOutput(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]),
        ]);
        $user = User::factory()->admin()->create(['credits_balance' => 5]);
        $project = $this->affiliateProject($user);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertCreated()
            ->json('data.id');

        $this->runCampaignJobAndFailOnException($generationId);

        Http::assertSent(function ($request): bool {
            $body = $request->body();

            return str_contains($body, 'Do not invent fake metrics')
                && str_contains($body, 'fake testimonials')
                && str_contains($body, 'guaranteed viral results')
                && str_contains($body, 'unsupported claims')
                && ! str_contains($body, 'sk-test-campaign');
        });
    }

    public function test_user_cannot_generate_another_users_campaign(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = $this->affiliateProject($owner);

        $this->actingAs($otherUser)
            ->postJson("/api/v1/projects/{$project->id}/generate-campaign")
            ->assertNotFound();

        $this->assertDatabaseCount('generations', 0);
        $this->assertSame(20, $owner->refresh()->credits_balance);
        $this->assertSame(20, $otherUser->refresh()->credits_balance);
    }

    private function affiliateProject(User $user, string $language = 'english'): Project
    {
        return Project::factory()
            ->for($user)
            ->create([
                'title' => 'Portable smoothie blender',
                'type' => 'affiliate_campaign',
                'status' => 'draft',
                'input_payload' => [
                    'product_name' => 'Portable smoothie blender',
                    'product_description' => 'A rechargeable blender for quick smoothies.',
                    'target_audience' => 'Busy students who want healthy breakfasts.',
                    'language' => $language,
                    'tone' => 'problem_solution',
                ],
                'settings' => [
                    'platform' => 'tiktok',
                    'format' => 'vertical_9_16',
                ],
            ]);
    }

    private function enableOpenAiProvider(?string $apiKey): void
    {
        config()->set('campaign.provider', 'openai');
        config()->set('campaign.real_enabled', true);
        config()->set('campaign.real_internal_only', true);
        config()->set('campaign.openai.api_key', $apiKey);
    }

    private function runCampaignJobAndFailOnException(int $generationId): ?Throwable
    {
        $job = new GenerateAffiliateCampaignJob($generationId);

        try {
            $job->handle(app(CampaignGenerationProcessor::class));

            return null;
        } catch (Throwable $exception) {
            $job->failed($exception);

            return $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function openAiStructuredOutput(): array
    {
        return [
            'campaign_title' => 'Guion honesto para Mini Cámara',
            'product_angle' => 'Mostrar cómo ayuda a crear contenido con más claridad, sin prometer resultados.',
            'target_audience_summary' => 'Creadores pequeños que quieren grabar contenido de producto de forma práctica.',
            'hooks' => [
                ['label' => 'Hook 1', 'text' => '¿Quieres mejorar tu video de producto sin complicarte?', 'intent' => 'Problema cotidiano'],
                ['label' => 'Hook 2', 'text' => 'Antes de comprar una cámara, revisa estos detalles.', 'intent' => 'Comparación honesta'],
                ['label' => 'Hook 3', 'text' => 'Así mostraría esta mini cámara en TikTok.', 'intent' => 'Demostración'],
                ['label' => 'Hook 4', 'text' => 'Tres tomas simples para presentar el producto.', 'intent' => 'Educativo'],
                ['label' => 'Hook 5', 'text' => 'Mira si esta cámara encaja con tu rutina.', 'intent' => 'Evaluación'],
            ],
            'main_script' => [
                'duration_seconds' => 30,
                'voiceover_script' => 'Antes de comprar, revisa cómo esta cámara puede ayudarte en tu rutina.',
                'on_screen_text' => ['Revisa detalles', 'Mira el uso real', 'Compara antes de decidir'],
                'scenes' => [
                    [
                        'time_range' => '0-4s',
                        'visual_direction' => 'Primer plano limpio del producto.',
                        'voiceover' => 'Antes de comprar, mira esta mini cámara.',
                        'on_screen_text' => 'Mira primero',
                    ],
                    [
                        'time_range' => '4-14s',
                        'visual_direction' => 'Muestra el tamaño y el uso en escritorio.',
                        'voiceover' => 'Está pensada para creadores que necesitan grabar rápido.',
                        'on_screen_text' => 'Para creadores',
                    ],
                    [
                        'time_range' => '14-24s',
                        'visual_direction' => 'Enseña una prueba real sin exagerar.',
                        'voiceover' => 'Compara los detalles y verifica si encaja con tu contenido.',
                        'on_screen_text' => 'Verifica detalles',
                    ],
                ],
            ],
            'voiceover_ready_script' => 'Antes de comprar, revisa cómo esta cámara puede ayudarte en tu rutina.',
            'captions' => ['Una forma simple de revisar esta mini cámara antes de decidir.'],
            'hashtags' => ['#MiniCamara', '#Creadores', '#TikTokShop', '#Producto', '#Reseña'],
            'cta_options' => ['Revisa los detalles del producto antes de decidir.'],
            'compliance_notes' => ['Verifica las especificaciones antes de publicar.', 'Añade divulgación de afiliado si aplica.'],
            'usage_notes' => ['Usa tomas reales del producto.', 'Evita prometer resultados específicos.'],
        ];
    }
}
