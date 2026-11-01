<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Exceptions\FashionTryOn\FashionTryOnProviderException;
use App\Jobs\GenerateFashionTryOnJob;
use App\Models\CreatorTool;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\FashionTryOnGenerationProcessor;
use App\Services\Generation\FashionTryOnPromptBuilder;
use App\Services\Generation\FashnFashionTryOnClient;
use App\Services\Generation\FashnFashionTryOnProvider;
use App\Support\FashionTryOnCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class FashionTryOnGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media-test');
        config()->set('media.disk', 'media-test');
        $this->enableTool();
        config()->set('fashion_try_on.provider', 'mock');
        config()->set('fashion_try_on.real_enabled', false);
        config()->set('fashion_try_on.variations_enabled', false);
        config()->set('fashion_try_on.fashn.api_key', null);
        config()->set('fashion_try_on.fashn.poll_interval_seconds', 0);
        config()->set('fashion_try_on.fashn.max_poll_attempts', 1);
    }

    public function test_tool_and_capabilities_are_safe_for_frontend(): void
    {
        $tool = collect($this->getJson('/api/v1/tools')->assertOk()->json('data'))->firstWhere('key', 'fashion_try_on');

        $this->assertSame('AI Fashion Try-On', $tool['title']);
        $this->assertSame('available', $tool['status']);
        $this->assertSame('/dashboard?tool=ai_fashion_try_on', $tool['route']);
        $this->assertSame('real_provider', $tool['implementation_stage']);
        $this->assertSame(2, $tool['credit_cost']);

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/fashion-try-on/capabilities')
            ->assertOk()
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonPath('data.mock_mode', true)
            ->assertJsonPath('data.standard_enabled', true)
            ->assertJsonPath('data.premium_supported', true)
            ->assertJsonPath('data.premium_enabled', false)
            ->assertJsonPath('data.premium_real_available_for_user', false)
            ->assertJsonPath('data.premium_pricing_configured', true)
            ->assertJsonPath('data.premium_default_preset', 'balanced_2k')
            ->assertJsonPath('data.premium_output_count', 1)
            ->assertJsonPath('data.standard_credit_cost', 2)
            ->assertJsonPath('data.available_premium_presets', [])
            ->assertJsonPath('data.known_premium_presets.0.key', 'fast_1k')
            ->assertJsonPath('data.known_premium_presets.0.enabled', false)
            ->assertJsonPath('data.known_premium_presets.0.nusa_credit_cost', 3)
            ->assertJsonPath('data.known_premium_presets.1.key', 'balanced_2k')
            ->assertJsonPath('data.known_premium_presets.1.expected_provider_credits', 3)
            ->assertJsonPath('data.known_premium_presets.1.nusa_credit_cost', 7)
            ->assertJsonPath('data.known_premium_presets.2.key', 'quality_4k')
            ->assertJsonPath('data.known_premium_presets.2.nusa_credit_cost', 11)
            ->assertJsonPath('data.known_premium_presets.2.disabled_reason', 'premium_disabled')
            ->assertJsonPath('data.maximum_outputs', 4)
            ->assertJsonPath('data.active_packages.0', 'single_result')
            ->assertJsonPath('data.default_package', 'single_result')
            ->assertJsonPath('data.future_packages.0', 'variations')
            ->assertJsonPath('data.active_person_sources.0', 'user_upload')
            ->assertJsonPath('data.default_person_source', 'user_upload')
            ->assertJsonPath('data.future_person_sources.0', 'nusa_ai_model')
            ->assertJsonPath('data.max_outputs_enabled', 1)
            ->assertJsonPath('data.multi_output_generation_enabled', false);

        $encoded = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('api_key', $encoded);
        $this->assertStringNotContainsString('https://api.fashn.ai', $encoded);
    }

    public function test_endpoint_requires_auth_and_project_ownership(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $project = $this->workspaceProject($owner);
        [$person, $product] = $this->attachAssets($project, $owner);

        $this->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", [])->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product))
            ->assertNotFound();

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_consent_and_required_images_are_validated_before_charge(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", [
                'tier' => 'standard',
                'person_image_permission_confirmed' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['person_image_asset_id', 'product_image_asset_id']);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                'person_image_permission_confirmed' => false,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('person_image_permission_confirmed');

        Queue::assertNothingPushed();
        $this->assertSame(5, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_future_packages_person_sources_and_output_counts_are_rejected_before_charge(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                'package_key' => 'variations',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package_key');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                'person_source' => 'nusa_ai_model',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('person_source');

        foreach (['num_samples', 'num_images', 'output_count'] as $field) {
            $this->actingAs($user)
                ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                    $field => 2,
                ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                'requested_output_count' => 2,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('requested_output_count');

        foreach (['nusa_credit_cost', 'configured_nusa_credit_cost', 'credit_cost', 'credits_charged', 'per_output_credit_cost', 'actual_provider_credits', 'actual_provider_cost_usd'] as $field) {
            $this->actingAs($user)
                ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                    $field => 1,
                ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }

        Queue::assertNothingPushed();
        $this->assertSame(5, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_single_result_package_and_user_upload_person_source_are_accepted(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                'package_key' => 'single_result',
                'person_source' => 'user_upload',
            ]))
            ->assertCreated()
            ->json('data.id');

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame('single_result', $generation->input_payload['package_key']);
        $this->assertSame('user_upload', $generation->input_payload['person_source']);
        Queue::assertPushed(GenerateFashionTryOnJob::class);
    }

    public function test_foreign_non_image_and_missing_storage_assets_are_rejected_before_charge(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->workspaceProject($user);
        $otherProject = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);
        [$foreignPerson] = $this->attachAssets($otherProject, $user);
        $pdf = $this->storedAsset($project, $user, 'garment_reference', ['type' => 'document', 'mime_type' => 'application/pdf'], '%PDF-1.4');
        $missing = $this->assetRecord($project, $user, 'model_reference');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($foreignPerson, $product))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'fashn_input_validation');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $pdf))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'fashn_input_validation');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($missing, $product))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'fashn_image_load_failed');

        Queue::assertNothingPushed();
        $this->assertSame(5, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_mock_generation_charges_once_and_creates_honest_plan_without_fake_image(): void
    {
        Http::fake();
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->workspaceProject($user, ['language' => 'spanish']);
        [$person, $product] = $this->attachAssets($project, $user);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product))
            ->assertCreated()
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonPath('data.credits_charged', 2)
            ->assertJsonPath('meta.credits_balance', 3)
            ->json('data.id');

        Queue::assertPushed(GenerateFashionTryOnJob::class, fn (GenerateFashionTryOnJob $job): bool => $job->generationId === $generationId);
        $this->runJob($generationId);

        $generation = Generation::query()->findOrFail($generationId);
        $transaction = CreditTransaction::query()->where('generation_id', $generationId)->firstOrFail();

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame('single_result', $generation->input_payload['package_key']);
        $this->assertSame('user_upload', $generation->input_payload['person_source']);
        $this->assertSame('single_result', $generation->output_payload['package_key']);
        $this->assertSame('user_upload', $generation->output_payload['person_source']);
        $this->assertSame(0, $generation->output_payload['provider_request_summary']['output_count']);
        $this->assertArrayNotHasKey('final_image_asset_ids', $generation->output_payload);
        $this->assertArrayNotHasKey('rendered_image_assets', $generation->output_payload);
        $this->assertSame('not_generated', $generation->output_payload['generated_image']['status']);
        $this->assertSame('Preview plan only — no try-on image was generated.', $generation->output_payload['generated_image']['message']);
        $this->assertArrayNotHasKey('final_image_asset_id', $generation->output_payload);
        $this->assertStringContainsString('probador virtual', $generation->output_payload['concept_summary']);
        $this->assertSame(CreditTransactionType::Usage, $transaction->type);
        $this->assertSame(2, $transaction->amount);
        $this->assertSame(3, $transaction->balance_after);
        $this->assertSame(0, MediaAsset::query()->where('usage_role', 'fashion_try_on_result')->count());
        Http::assertNothingSent();
    }

    public function test_insufficient_credits_block_dispatch_without_charge(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 1]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product))
            ->assertPaymentRequired()
            ->assertJsonPath('code', 'insufficient_credits')
            ->assertJsonPath('available_credits', 1)
            ->assertJsonPath('required_credits', 2);

        Queue::assertNothingPushed();
        $this->assertSame(1, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_standard_and_premium_payloads_use_distinct_provider_contracts(): void
    {
        config()->set('fashion_try_on.fashn.moderation_level', 'none');
        $user = User::factory()->create();
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);
        $provider = app(FashnFashionTryOnProvider::class);

        $standard = $provider->providerPayload('standard', $this->payload($person, $product, [
            'category' => 'tops', 'garment_photo_type' => 'flat_lay', 'standard_mode' => 'quality',
        ]), $person, $product);

        $this->assertSame('tryon-v1.6', $standard['model_name']);
        $this->assertStringStartsWith('data:image/png;base64,', data_get($standard, 'inputs.model_image'));
        $this->assertStringStartsWith('data:image/png;base64,', data_get($standard, 'inputs.garment_image'));
        $this->assertArrayNotHasKey('product_image', $standard['inputs']);
        $this->assertSame('permissive', data_get($standard, 'inputs.moderation_level'));
        $this->assertSame(1, data_get($standard, 'inputs.num_samples'));

        $premium = $provider->providerPayload('premium', $this->payload($person, $product, [
            'premium_preset' => 'balanced_2k', 'premium_prompt' => 'Clean catalogue look', 'provider_seed' => 123456,
        ]), $person, $product);

        $this->assertSame('tryon-max', $premium['model_name']);
        $this->assertStringStartsWith('data:image/png;base64,', data_get($premium, 'inputs.model_image'));
        $this->assertStringStartsWith('data:image/png;base64,', data_get($premium, 'inputs.product_image'));
        $this->assertArrayNotHasKey('garment_image', $premium['inputs']);
        $this->assertSame('2k', data_get($premium, 'inputs.resolution'));
        $this->assertSame('balanced', data_get($premium, 'inputs.generation_mode'));
        $this->assertSame(123456, data_get($premium, 'inputs.seed'));
        $this->assertSame(1, data_get($premium, 'inputs.num_images'));
        $this->assertArrayNotHasKey('category', $premium['inputs']);
        $this->assertArrayNotHasKey('garment_photo_type', $premium['inputs']);
        $this->assertArrayNotHasKey('segmentation_free', $premium['inputs']);
        $this->assertArrayNotHasKey('mode', $premium['inputs']);
        $this->assertArrayNotHasKey('num_samples', $premium['inputs']);
    }

    public function test_premium_tier_is_rejected_while_disabled_and_client_cannot_submit_provider_controls(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, ['tier' => 'premium']))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'fashn_premium_disabled');

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                'model_name' => 'tryon-max',
                'resolution' => '4k',
                'generation_mode' => 'quality',
                'seed' => 123,
                'return_base64' => true,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['model_name', 'resolution', 'generation_mode', 'seed', 'return_base64']);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_premium_capabilities_enable_fast_and_balanced_but_gate_quality_by_default(): void
    {
        $this->enablePremiumFashn();
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/fashion-try-on/capabilities')
            ->assertOk()
            ->assertJsonPath('data.provider', 'fashn')
            ->assertJsonPath('data.premium_enabled', true)
            ->assertJsonPath('data.premium_quality_enabled', false)
            ->assertJsonPath('data.premium_pricing_configured', true)
            ->assertJsonPath('data.available_premium_presets.0.key', 'fast_1k')
            ->assertJsonPath('data.available_premium_presets.0.nusa_credit_cost', 3)
            ->assertJsonPath('data.available_premium_presets.1.key', 'balanced_2k')
            ->assertJsonPath('data.available_premium_presets.1.nusa_credit_cost', 7)
            ->assertJsonPath('data.known_premium_presets.2.key', 'quality_4k')
            ->assertJsonPath('data.known_premium_presets.2.nusa_credit_cost', 11)
            ->assertJsonPath('data.known_premium_presets.2.enabled', false)
            ->assertJsonPath('data.known_premium_presets.2.disabled_reason', 'premium_quality_validation_pending')
            ->assertJsonPath('data.known_premium_presets.2.validation_status', 'final_provider_validation_pending')
            ->assertJsonPath('data.variations_pricing_ready', true)
            ->assertJsonPath('data.variations_enabled', false);
    }

    public function test_quality_4k_premium_preset_is_rejected_until_quality_flag_is_enabled(): void
    {
        Queue::fake();
        $this->enablePremiumFashn();
        $user = User::factory()->admin()->create(['credits_balance' => 20]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                'tier' => 'premium',
                'premium_preset' => FashionTryOnCapabilities::PREMIUM_PRESET_QUALITY_4K,
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'fashn_premium_quality_validation_pending');

        Queue::assertNothingPushed();
        $this->assertSame(20, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_premium_presets_charge_canonical_nusa_credit_costs_without_provider_call(): void
    {
        Queue::fake();
        Http::fake();
        $this->enablePremiumFashn();
        config()->set('fashion_try_on.fashn.premium_quality_enabled', true);
        $user = User::factory()->admin()->create(['credits_balance' => 30]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);

        foreach ([
            FashionTryOnCapabilities::PREMIUM_PRESET_FAST_1K => 3,
            FashionTryOnCapabilities::PREMIUM_PRESET_BALANCED_2K => 7,
            FashionTryOnCapabilities::PREMIUM_PRESET_QUALITY_4K => 11,
        ] as $preset => $expectedCost) {
            $generationId = $this->actingAs($user)
                ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                    'tier' => 'premium',
                    'premium_preset' => $preset,
                    'requested_output_count' => 1,
                ]))
                ->assertCreated()
                ->assertJsonPath('data.credits_charged', $expectedCost)
                ->json('data.id');

            $generation = Generation::query()->findOrFail($generationId);
            $this->assertSame($expectedCost, $generation->input_payload['nusa_credit_cost']);
            $this->assertSame($expectedCost, $generation->input_payload['configured_nusa_credit_cost']);
            $this->assertSame($expectedCost, $generation->input_payload['per_output_credit_cost']);
            $this->assertSame(1, $generation->input_payload['requested_output_count']);
            $this->assertSame(1, $generation->input_payload['output_count']);
            $this->assertTrue($generation->input_payload['variations_pricing_ready']);
            $this->assertFalse($generation->input_payload['variations_enabled']);
        }

        $this->assertSame(9, $user->refresh()->credits_balance);
        Queue::assertPushed(GenerateFashionTryOnJob::class, 3);
        Http::assertNothingSent();
    }

    public function test_real_fashn_standard_completion_persists_prediction_and_stores_private_image(): void
    {
        Queue::fake();
        $this->enableFashn();
        $user = User::factory()->admin()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);
        $output = 'data:image/png;base64,'.base64_encode($this->pngBytes());

        Http::fake([
            'https://api.fashn.ai/v1/run' => Http::response(['id' => 'pred_standard_123', 'status' => 'starting'], 200, ['x-fashn-credits-used' => '7']),
            'https://api.fashn.ai/v1/status/pred_standard_123' => Http::response(['id' => 'pred_standard_123', 'status' => 'completed', 'output' => [$output]], 200),
        ]);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, ['category' => 'tops']))
            ->assertCreated()
            ->assertJsonPath('data.provider', 'fashn')
            ->assertJsonPath('meta.credits_balance', 8)
            ->json('data.id');

        $this->runJob($generationId);
        $generation = Generation::query()->findOrFail($generationId);
        $asset = MediaAsset::query()->where('usage_role', 'fashion_try_on_result')->firstOrFail();

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame('pred_standard_123', $generation->input_payload['provider_prediction_id']);
        $this->assertSame('tryon-v1.6', $generation->input_payload['provider_model']);
        $this->assertSame('rendered', $generation->output_payload['generated_image']['status']);
        $this->assertSame(7, $generation->output_payload['provider_metadata']['provider_credits_used']);
        $this->assertSame('response_header', $generation->output_payload['provider_metadata']['provider_credits_source']);
        $this->assertArrayHasKey('actual_provider_cost_usd', $generation->output_payload['provider_metadata']);
        $this->assertSame(0.525, $generation->output_payload['provider_metadata']['actual_provider_cost_usd']);
        $this->assertSame('single_result', $generation->input_payload['package_key']);
        $this->assertSame('user_upload', $generation->input_payload['person_source']);
        $this->assertSame('single_result', $generation->output_payload['package_key']);
        $this->assertSame('user_upload', $generation->output_payload['person_source']);
        $this->assertSame($asset->id, $generation->output_payload['final_image_asset_id']);
        $this->assertSame([$asset->id], $generation->output_payload['final_image_asset_ids']);
        $this->assertSame($asset->id, $generation->output_payload['rendered_image_asset']['id']);
        $this->assertSame($asset->id, $generation->output_payload['rendered_image_assets'][0]['id']);
        $this->assertSame(0, $generation->output_payload['rendered_image_assets'][0]['output_index']);
        $this->assertSame(1, $generation->output_payload['generation_summary']['output_count']);
        $this->assertSame('single_result', $asset->metadata['package_key']);
        $this->assertSame('user_upload', $asset->metadata['person_source']);
        $this->assertSame('fashion_try_on', $asset->metadata['workflow']);
        $this->assertSame($generationId, $asset->metadata['generation_id']);
        $this->assertSame(0, $asset->metadata['output_index']);
        $this->assertSame(1, $asset->metadata['output_count']);
        $this->assertSame('image/png', $asset->mime_type);
        Storage::disk('media-test')->assertExists($asset->path);
        $this->assertSame(1, CreditTransaction::query()->where('generation_id', $generationId)->where('type', CreditTransactionType::Usage->value)->count());

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/run')
                && data_get($data, 'model_name') === 'tryon-v1.6'
                && is_string(data_get($data, 'inputs.model_image'))
                && is_string(data_get($data, 'inputs.garment_image'))
                && data_get($data, 'inputs.product_image') === null
                && data_get($data, 'inputs.num_samples') === 1;
        });
    }

    public function test_premium_pricing_must_be_configured_for_normal_paid_generation(): void
    {
        Queue::fake();
        $this->enablePremiumFashn();
        config()->set('fashion_try_on.credit_costs.premium_presets.fast_1k', null);
        $user = User::factory()->admin()->create(['credits_balance' => 20]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                'tier' => 'premium',
                'premium_preset' => 'fast_1k',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'fashn_premium_pricing_unconfigured');

        Queue::assertNothingPushed();
        $this->assertSame(20, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_internal_unlimited_can_create_premium_validation_generation_with_included_access(): void
    {
        Queue::fake();
        $this->enablePremiumFashn();
        $user = User::factory()->internalCreator()->create(['credits_balance' => 0]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                'tier' => 'premium',
                'premium_preset' => 'fast_1k',
                'premium_prompt' => '  tuck in the shirt  ',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.credits_charged', 0)
            ->json('data.id');

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame('premium', $generation->input_payload['tier']);
        $this->assertSame('fast_1k', $generation->input_payload['premium_preset']);
        $this->assertSame('tuck in the shirt', $generation->input_payload['premium_prompt']);
        $this->assertSame(1, $generation->input_payload['expected_provider_credits']);
        $this->assertSame(3, $generation->input_payload['configured_nusa_credit_cost']);
        $this->assertSame(3, $generation->input_payload['nusa_credit_cost']);
        $this->assertSame(0, $generation->credits_charged);
        $this->assertSame(1, CreditTransaction::query()->where('generation_id', $generationId)->where('type', CreditTransactionType::Usage->value)->where('amount', 0)->count());
        $this->assertArrayHasKey('provider_seed', $generation->input_payload);
        $this->assertIsInt($generation->input_payload['provider_seed']);
        Queue::assertPushed(GenerateFashionTryOnJob::class);
    }

    public function test_real_fashn_premium_completion_uses_tryon_max_one_output_and_safe_metadata(): void
    {
        Queue::fake();
        $this->enablePremiumFashn();
        $user = User::factory()->admin()->create(['credits_balance' => 20]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);
        $output = 'data:image/png;base64,'.base64_encode($this->pngBytes());
        $extraOutput = 'data:image/png;base64,'.base64_encode($this->pngBytes());

        Http::fake([
            'https://api.fashn.ai/v1/run' => Http::response(['id' => 'pred_premium_123', 'status' => 'starting'], 200),
            'https://api.fashn.ai/v1/status/pred_premium_123' => Http::response(['id' => 'pred_premium_123', 'status' => 'completed', 'output' => [$output, $extraOutput]], 200, ['x-fashn-credits-used' => '1']),
        ]);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                'tier' => 'premium',
                'premium_preset' => 'fast_1k',
                'premium_prompt' => '  Open the jacket and keep product visible.  ',
                'category' => 'tops',
                'garment_photo_type' => 'flat_lay',
                'standard_mode' => 'quality',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.provider', 'fashn')
            ->assertJsonPath('data.credits_charged', 3)
            ->assertJsonPath('meta.credits_balance', 17)
            ->json('data.id');

        $generationBeforeJob = Generation::query()->findOrFail($generationId);
        $persistedSeed = $generationBeforeJob->input_payload['provider_seed'];
        $this->assertIsInt($persistedSeed);
        $this->assertSame('fast_1k', $generationBeforeJob->input_payload['premium_preset']);
        $this->assertSame('Open the jacket and keep product visible.', $generationBeforeJob->input_payload['premium_prompt']);
        $this->assertSame('1k', $generationBeforeJob->input_payload['provider_resolution']);
        $this->assertSame('fast', $generationBeforeJob->input_payload['provider_generation_mode']);
        $this->assertSame(1, $generationBeforeJob->input_payload['expected_provider_credits']);
        $this->assertSame(3, $generationBeforeJob->input_payload['nusa_credit_cost']);
        $this->assertSame(3, $generationBeforeJob->input_payload['configured_nusa_credit_cost']);
        $this->assertSame(3, $generationBeforeJob->input_payload['per_output_credit_cost']);
        $this->assertArrayNotHasKey('category', $generationBeforeJob->input_payload);
        $this->assertArrayNotHasKey('standard_mode', $generationBeforeJob->input_payload);

        $this->runJob($generationId);
        $generation = Generation::query()->findOrFail($generationId);
        $asset = MediaAsset::query()->where('usage_role', 'fashion_try_on_result')->firstOrFail();

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame('pred_premium_123', $generation->input_payload['provider_prediction_id']);
        $this->assertSame($persistedSeed, $generation->input_payload['provider_seed']);
        $this->assertSame('tryon-max', $generation->input_payload['provider_model']);
        $this->assertSame('premium', $generation->output_payload['tier']);
        $this->assertSame('fast_1k', $generation->output_payload['premium_preset']);
        $this->assertSame([$asset->id], $generation->output_payload['final_image_asset_ids']);
        $this->assertSame($asset->id, $generation->output_payload['rendered_image_assets'][0]['id']);
        $this->assertCount(1, $generation->output_payload['rendered_image_assets']);
        $this->assertSame(1, $generation->output_payload['generation_summary']['output_count']);
        $this->assertSame('1K', $generation->output_payload['generation_summary']['provider_resolution']);
        $this->assertSame('Fast', $generation->output_payload['generation_summary']['provider_generation_mode']);
        $this->assertSame(1, $generation->output_payload['provider_metadata']['expected_provider_credits']);
        $this->assertSame(1, $generation->output_payload['provider_metadata']['actual_provider_credits']);
        $this->assertSame('response_header', $generation->output_payload['provider_metadata']['provider_credits_source']);
        $this->assertSame($persistedSeed, $generation->output_payload['provider_metadata']['provider_seed']);
        $this->assertSame(2, $generation->output_payload['provider_metadata']['observed_provider_output_count']);
        $this->assertSame('provider_returned_more_outputs_than_requested_extra_outputs_ignored', $generation->output_payload['provider_metadata']['provider_output_warning']);
        $this->assertSame('premium', $asset->metadata['tier']);
        $this->assertSame('fast_1k', $asset->metadata['premium_preset']);
        $this->assertSame('1k', $asset->metadata['provider_resolution']);
        $this->assertSame('fast', $asset->metadata['provider_generation_mode']);
        $this->assertSame($persistedSeed, $asset->metadata['provider_seed']);
        $this->assertSame(1, $asset->metadata['expected_provider_credits']);
        $this->assertArrayNotHasKey('premium_prompt', $asset->metadata);
        $this->assertArrayNotHasKey('prompt', $asset->metadata);
        $this->assertSame(1, CreditTransaction::query()->where('generation_id', $generationId)->where('type', CreditTransactionType::Usage->value)->where('amount', 3)->count());

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request) use ($persistedSeed): bool {
            $data = $request->data();

            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/run')
                && data_get($data, 'model_name') === 'tryon-max'
                && is_string(data_get($data, 'inputs.model_image'))
                && is_string(data_get($data, 'inputs.product_image'))
                && data_get($data, 'inputs.garment_image') === null
                && data_get($data, 'inputs.category') === null
                && data_get($data, 'inputs.garment_photo_type') === null
                && data_get($data, 'inputs.segmentation_free') === null
                && data_get($data, 'inputs.mode') === null
                && data_get($data, 'inputs.num_samples') === null
                && data_get($data, 'inputs.num_images') === 1
                && data_get($data, 'inputs.resolution') === '1k'
                && data_get($data, 'inputs.generation_mode') === 'fast'
                && data_get($data, 'inputs.seed') === $persistedSeed
                && data_get($data, 'inputs.prompt') === 'Open the jacket and keep product visible.';
        });
    }

    public function test_existing_prediction_is_polled_instead_of_resubmitted(): void
    {
        $this->enableFashn();
        $user = User::factory()->admin()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);
        $generation = Generation::query()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'job_type' => 'generate_fashion_try_on',
            'provider' => 'fashn',
            'status' => Generation::STATUS_PROCESSING,
            'cost_usd' => 0,
            'credits_charged' => 2,
            'input_payload' => $this->payload($person, $product, ['project_id' => $project->id, 'user_id' => $user->id, 'provider_prediction_id' => 'pred_existing_456']),
            'output_payload' => null,
        ]);
        $output = 'data:image/png;base64,'.base64_encode($this->pngBytes());
        Http::fake(['https://api.fashn.ai/v1/status/pred_existing_456' => Http::response(['status' => 'completed', 'output' => [$output]], 200)]);

        $this->runJob($generation->id);
        $this->runJob($generation->id);

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->refresh()->status);
        $this->assertSame(1, MediaAsset::query()->where('usage_role', 'fashion_try_on_result')->count());
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/run'));
    }

    public function test_provider_failure_or_invalid_output_refunds_and_creates_no_fake_asset(): void
    {
        Queue::fake();
        $this->enableFashn();
        $user = User::factory()->admin()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);
        Http::fake([
            'https://api.fashn.ai/v1/run' => Http::response(['id' => 'pred_bad_output', 'status' => 'starting'], 200),
            'https://api.fashn.ai/v1/status/pred_bad_output' => Http::response(['status' => 'completed', 'output' => ['not-an-image']], 200),
        ]);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product))
            ->assertCreated()
            ->json('data.id');

        try {
            $this->runJob($generationId);
        } catch (FashionTryOnProviderException $caught) {
            (new GenerateFashionTryOnJob($generationId))->failed($caught);
        }

        $generation = Generation::query()->findOrFail($generationId);
        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertSame('fashn_output_invalid', $generation->output_payload['provider_metadata']['failure_reason']);
        $this->assertSame(10, $user->refresh()->credits_balance);
        $this->assertSame(0, MediaAsset::query()->where('usage_role', 'fashion_try_on_result')->count());
        $this->assertSame(1, CreditTransaction::query()->where('generation_id', $generationId)->where('type', CreditTransactionType::Refund->value)->count());
    }

    public function test_fashn_submit_has_no_retry_and_logs_safely(): void
    {
        $this->enableFashn('secret-fashn-key');
        $calls = 0;
        Log::spy();
        Http::fake(function (Request $request) use (&$calls): never {
            $calls++;
            throw new ConnectionException('timeout secret-fashn-key https://api.fashn.ai/v1/run data:image/png;base64,'.base64_encode($this->pngBytes()).' /home/koke/projects/private/media/person.png');
        });

        try {
            app(FashnFashionTryOnClient::class)->submit(['model_name' => 'tryon-v1.6', 'inputs' => []], ['generation_id' => 999]);
            $this->fail('Expected wrapped connection exception.');
        } catch (FashionTryOnProviderException $exception) {
            $this->assertSame('fashn_connection_failed', $exception->reason);
            $this->assertSame(1, $calls);
            $encoded = json_encode($exception->context, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('secret-fashn-key', $encoded);
            $this->assertStringNotContainsString('data:image/png;base64', $encoded);
            $this->assertStringNotContainsString('/home/koke/projects/private', $encoded);
            $this->assertStringContainsString('[redacted-url]', $exception->context['safe_exception_message']);
            $this->assertStringContainsString('[redacted-data-uri]', $exception->context['safe_exception_message']);
        }

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            $encoded = json_encode($context, JSON_THROW_ON_ERROR);

            return $message === 'FASHN submit connection failed.'
                && ($context['provider'] ?? null) === 'fashn'
                && ($context['stage'] ?? null) === 'submit'
                && ($context['endpoint_host'] ?? null) === 'api.fashn.ai'
                && ! str_contains($encoded, 'secret-fashn-key')
                && ! str_contains($encoded, 'data:image/png;base64')
                && ! str_contains($encoded, '/home/koke/projects/private');
        });
    }

    public function test_fashn_runtime_error_shapes_are_normalized_without_array_to_string_warnings(): void
    {
        $this->enableFashn('secret-fashn-key');
        $client = app(FashnFashionTryOnClient::class);
        $longMessage = str_repeat('Provider says pose could not be detected safely. ', 12)
            .'secret-fashn-key data:image/png;base64,'.base64_encode($this->pngBytes()).' /home/koke/projects/private/media/person.png';

        foreach ([
            'image_object' => [
                'body' => ['status' => 'failed', 'error' => ['name' => 'ImageLoadError', 'message' => 'Could not load the garment image.']],
                'category' => 'fashn_image_load_failed',
                'name' => 'ImageLoadError',
                'message' => 'Could not load',
            ],
            'pose_object' => [
                'body' => ['status' => 'failed', 'error' => ['name' => 'PoseError', 'message' => 'Pose could not be detected.']],
                'category' => 'fashn_pose_not_detected',
                'name' => 'PoseError',
                'message' => 'Pose could not',
            ],
            'moderation_object' => [
                'body' => ['status' => 'failed', 'error' => ['name' => 'ModerationError', 'message' => 'Content safety policy blocked the image.']],
                'category' => 'fashn_content_moderation',
                'name' => 'ModerationError',
                'message' => 'Content safety',
            ],
            'plain_string_error' => [
                'body' => ['status' => 'failed', 'error' => 'Garment image could not be loaded.'],
                'category' => 'fashn_image_load_failed',
                'name' => null,
                'message' => 'Garment image',
            ],
            'null_error' => [
                'body' => ['status' => 'failed', 'error' => null],
                'category' => 'fashn_unknown_error',
                'name' => null,
                'message' => 'Provider did not provide',
            ],
            'nested_error_arrays' => [
                'body' => ['status' => 'failed', 'errors' => [['detail' => ['message' => 'Rate limit exceeded for provider.']]]],
                'category' => 'fashn_provider_unavailable',
                'name' => null,
                'message' => 'Rate limit',
            ],
            'numeric_error' => [
                'body' => ['status' => 'failed', 'error' => 12345],
                'category' => 'fashn_processing_failed',
                'name' => null,
                'message' => '12345',
            ],
            'boolean_error' => [
                'body' => ['status' => 'failed', 'error' => false],
                'category' => 'fashn_processing_failed',
                'name' => null,
                'message' => 'false',
            ],
            'detail_message_fallback' => [
                'body' => ['status' => 'failed', 'detail' => ['message' => 'Unauthorized: Invalid token.']],
                'category' => 'fashn_auth_failed',
                'name' => null,
                'message' => 'Unauthorized',
            ],
            'name_precedence_over_message' => [
                'body' => ['status' => 'failed', 'error' => ['name' => 'ImageLoadError', 'message' => 'The person was not detected clearly.']],
                'category' => 'fashn_image_load_failed',
                'name' => 'ImageLoadError',
                'message' => 'person was not',
            ],
            'bounded_and_redacted_message' => [
                'body' => ['status' => 'failed', 'error' => ['name' => 'PoseError', 'message' => $longMessage]],
                'category' => 'fashn_pose_not_detected',
                'name' => 'PoseError',
                'message' => 'Provider says pose',
            ],
        ] as $caseName => $case) {
            $predictionId = 'pred_'.$caseName;
            Http::fake([
                "https://api.fashn.ai/v1/status/{$predictionId}" => Http::response($case['body'], 200),
            ]);

            try {
                $client->waitForCompletion($predictionId, ['generation_id' => 999]);
                $this->fail("Expected failed prediction exception for {$caseName}.");
            } catch (FashionTryOnProviderException $exception) {
                $this->assertSame('fashn_processing_failed', $exception->reason, $caseName);
                $this->assertSame($case['category'], $exception->context['safe_error_category'], $caseName);
                $this->assertSame($predictionId, $exception->context['provider_prediction_id'], $caseName);
                $this->assertSame('failed', $exception->context['provider_status'], $caseName);
                $this->assertStringContainsString($case['message'], $exception->context['provider_error_message'], $caseName);
                $this->assertLessThanOrEqual(240, mb_strlen($exception->context['provider_error_message']), $caseName);

                if ($case['name'] === null) {
                    $this->assertArrayNotHasKey('provider_error_name', array_filter($exception->context), $caseName);
                } else {
                    $this->assertSame($case['name'], $exception->context['provider_error_name'], $caseName);
                }

                $encodedContext = json_encode($exception->context, JSON_THROW_ON_ERROR);
                $this->assertStringNotContainsString('secret-fashn-key', $encodedContext, $caseName);
                $this->assertStringNotContainsString('data:image/png;base64', $encodedContext, $caseName);
                $this->assertStringNotContainsString('/home/koke/projects/private', $encodedContext, $caseName);
            }

            Http::assertSentCount(1);
            Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
        }
    }

    public function test_fashn_api_level_error_shapes_are_normalized_without_prediction_id_or_retry(): void
    {
        $this->enableFashn('secret-fashn-key');
        Log::spy();
        Http::fake([
            'https://api.fashn.ai/v1/run' => Http::response([
                'error' => 'UnauthorizedAccess',
                'message' => 'Unauthorized: Invalid token secret-fashn-key data:image/png;base64,'.base64_encode($this->pngBytes()).' /home/koke/projects/private/media/person.png',
            ], 401),
        ]);

        try {
            app(FashnFashionTryOnClient::class)->submit(['model_name' => 'tryon-v1.6', 'inputs' => []], ['generation_id' => 999]);
            $this->fail('Expected API-level provider exception.');
        } catch (FashionTryOnProviderException $exception) {
            $this->assertSame('fashn_auth_failed', $exception->reason);
            $this->assertSame('fashn_auth_failed', $exception->context['safe_error_category']);
            $this->assertSame('UnauthorizedAccess', $exception->context['provider_error_name']);
            $this->assertStringContainsString('Unauthorized: Invalid token', $exception->context['provider_error_message']);
            $this->assertArrayNotHasKey('provider_prediction_id', $exception->context);

            $encodedContext = json_encode($exception->context, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('secret-fashn-key', $encodedContext);
            $this->assertStringNotContainsString('data:image/png;base64', $encodedContext);
            $this->assertStringNotContainsString('/home/koke/projects/private', $encodedContext);
        }

        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            $encodedContext = json_encode($context, JSON_THROW_ON_ERROR);

            return $message === 'FASHN submit failed.'
                && ($context['safe_error_category'] ?? null) === 'fashn_auth_failed'
                && ($context['provider_error_name'] ?? null) === 'UnauthorizedAccess'
                && ! array_key_exists('provider_prediction_id', $context)
                && ! str_contains($encodedContext, 'secret-fashn-key')
                && ! str_contains($encodedContext, 'data:image/png;base64')
                && ! str_contains($encodedContext, '/home/koke/projects/private');
        });
    }

    public function test_fashn_runtime_failed_prediction_persists_safe_metadata_refunds_and_is_not_resubmitted(): void
    {
        Queue::fake();
        $this->enableFashn('secret-fashn-key');
        $user = User::factory()->admin()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);
        Http::fake([
            'https://api.fashn.ai/v1/run' => Http::response(['id' => 'pred_runtime_fail', 'status' => 'starting'], 200),
            'https://api.fashn.ai/v1/status/pred_runtime_fail' => Http::response([
                'id' => 'pred_runtime_fail',
                'status' => 'failed',
                'error' => [
                    'name' => 'ImageLoadError',
                    'message' => 'Could not load garment image from provider input.',
                ],
            ], 200),
        ]);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product))
            ->assertCreated()
            ->assertJsonPath('data.provider', 'fashn')
            ->assertJsonPath('meta.credits_balance', 8)
            ->json('data.id');

        try {
            $this->runJob($generationId);
            $this->fail('Expected provider failed prediction exception.');
        } catch (FashionTryOnProviderException $caught) {
            (new GenerateFashionTryOnJob($generationId))->failed($caught);
            (new GenerateFashionTryOnJob($generationId))->failed($caught);
        }

        $this->runJob($generationId);

        $generation = Generation::query()->findOrFail($generationId);
        $metadata = $generation->output_payload['provider_metadata'];

        $this->assertSame(Generation::STATUS_FAILED, $generation->status);
        $this->assertNotNull($generation->finished_at);
        $this->assertSame('pred_runtime_fail', $generation->input_payload['provider_prediction_id']);
        $this->assertSame('pred_runtime_fail', $metadata['provider_prediction_id']);
        $this->assertSame('fashn_processing_failed', $metadata['failure_reason']);
        $this->assertSame('fashn_image_load_failed', $metadata['safe_error_category']);
        $this->assertSame('ImageLoadError', $metadata['provider_error_name']);
        $this->assertSame('Could not load garment image from provider input.', $metadata['provider_error_message']);
        $this->assertSame(10, $user->refresh()->credits_balance);
        $this->assertSame(0, MediaAsset::query()->where('usage_role', 'fashion_try_on_result')->count());
        $this->assertSame(1, CreditTransaction::query()->where('generation_id', $generationId)->where('type', CreditTransactionType::Usage->value)->count());
        $this->assertSame(1, CreditTransaction::query()->where('generation_id', $generationId)->where('type', CreditTransactionType::Refund->value)->count());

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST' && str_ends_with($request->url(), '/run');
        });
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET' && str_ends_with($request->url(), '/status/pred_runtime_fail');
        });
    }

    public function test_variations_capabilities_are_exposed_only_when_feature_flag_is_enabled(): void
    {
        config()->set('fashion_try_on.variations_enabled', true);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/fashion-try-on/capabilities')
            ->assertOk()
            ->assertJsonPath('data.variations_enabled', true)
            ->assertJsonPath('data.multi_output_generation_enabled', true)
            ->assertJsonPath('data.maximum_outputs', 4)
            ->assertJsonPath('data.max_outputs_enabled', 4)
            ->assertJsonPath('data.active_packages.0', 'single_result')
            ->assertJsonPath('data.active_packages.1', 'variations')
            ->assertJsonPath('data.packages.1.key', 'variations')
            ->assertJsonPath('data.packages.1.enabled', true)
            ->assertJsonPath('data.packages.1.status', 'beta')
            ->assertJsonPath('data.variations.allowed_output_counts.0', 2)
            ->assertJsonPath('data.variations.allowed_output_counts.1', 3)
            ->assertJsonPath('data.variations.allowed_output_counts.2', 4)
            ->assertJsonPath('data.variations.default_output_count', 2)
            ->assertJsonPath('data.variations.selection_required_for_continuation', true)
            ->assertJsonPath('data.known_premium_presets.0.total_credit_costs.2', 6)
            ->assertJsonPath('data.known_premium_presets.1.total_credit_costs.3', 21)
            ->assertJsonPath('data.known_premium_presets.2.total_credit_costs.2', 22)
            ->assertJsonPath('data.known_premium_presets.2.allowed_output_counts.0', 2)
            ->assertJsonPath('data.known_premium_presets.2.max_output_count', 2)
            ->assertJsonPath('data.known_premium_presets.2.limited_for_beta_response_safety', true)
            ->assertJsonPath('data.variations.standard.allowed_output_counts.2', 4)
            ->assertJsonPath('data.variations.premium_presets.quality_4k.allowed_output_counts.0', 2);
    }

    public function test_variations_output_count_validation_and_total_credit_charge_are_server_controlled(): void
    {
        Queue::fake();
        config()->set('fashion_try_on.variations_enabled', true);
        $user = User::factory()->create(['credits_balance' => 40]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);

        foreach ([2 => 4, 3 => 6, 4 => 8] as $count => $expectedCost) {
            $generationId = $this->actingAs($user)
                ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                    'package_key' => 'variations',
                    'requested_output_count' => $count,
                ]))
                ->assertCreated()
                ->assertJsonPath('data.credits_charged', $expectedCost)
                ->json('data.id');

            $generation = Generation::query()->findOrFail($generationId);
            $this->assertSame('variations', $generation->input_payload['package_key']);
            $this->assertSame($count, $generation->input_payload['requested_output_count']);
            $this->assertSame($count, $generation->input_payload['output_count']);
            $this->assertSame(2, $generation->input_payload['per_output_credit_cost']);
            $this->assertSame($expectedCost, $generation->input_payload['total_nusa_credit_cost']);
            $this->assertSame($count, $generation->input_payload['expected_provider_credits_total']);
            $this->assertArrayHasKey('provider_seed', $generation->input_payload);
            $this->assertIsInt($generation->input_payload['provider_seed']);
        }

        $this->assertSame(22, $user->refresh()->credits_balance);
        Queue::assertPushed(GenerateFashionTryOnJob::class, 3);
    }

    public function test_invalid_variations_output_counts_are_rejected_without_charge(): void
    {
        Queue::fake();
        config()->set('fashion_try_on.variations_enabled', true);
        $user = User::factory()->create(['credits_balance' => 20]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);

        foreach ([1, 0, 5, '2'] as $value) {
            $this->actingAs($user)
                ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                    'package_key' => 'variations',
                    'requested_output_count' => $value,
                ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('requested_output_count');
        }

        Queue::assertNothingPushed();
        $this->assertSame(20, $user->refresh()->credits_balance);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_variations_provider_payload_uses_num_samples_for_standard_and_num_images_for_premium(): void
    {
        config()->set('fashion_try_on.variations_enabled', true);
        config()->set('fashion_try_on.fashn.moderation_level', 'none');
        $user = User::factory()->admin()->create();
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);
        $provider = app(FashnFashionTryOnProvider::class);

        $standard = $provider->providerPayload('standard', $this->payload($person, $product, [
            'package_key' => 'variations',
            'requested_output_count' => 3,
            'provider_seed' => 987654,
        ]), $person, $product);

        $this->assertSame('tryon-v1.6', $standard['model_name']);
        $this->assertSame(3, data_get($standard, 'inputs.num_samples'));
        $this->assertSame(987654, data_get($standard, 'inputs.seed'));
        $this->assertNull(data_get($standard, 'inputs.num_images'));

        $premium = $provider->providerPayload('premium', $this->payload($person, $product, [
            'package_key' => 'variations',
            'tier' => 'premium',
            'premium_preset' => 'balanced_2k',
            'requested_output_count' => 4,
            'provider_seed' => 123456,
        ]), $person, $product);

        $this->assertSame('tryon-max', $premium['model_name']);
        $this->assertSame(4, data_get($premium, 'inputs.num_images'));
        $this->assertSame(123456, data_get($premium, 'inputs.seed'));
        $this->assertNull(data_get($premium, 'inputs.num_samples'));
    }

    public function test_variation_prompt_builder_preserves_single_result_and_guides_premium_variations(): void
    {
        config()->set('fashion_try_on.variations_enabled', true);
        $builder = app(FashionTryOnPromptBuilder::class);

        $singleMetadata = $builder->safeMetadata([
            'package_key' => 'single_result',
            'premium_prompt' => 'Clean catalogue look',
        ], 'premium');

        $this->assertArrayNotHasKey('variation_diversity_requested', $singleMetadata);
        $this->assertTrue($singleMetadata['user_styling_prompt_present']);
        $this->assertSame('Clean catalogue look', $builder->premiumProviderPrompt([
            'package_key' => 'single_result',
            'premium_prompt' => 'Clean catalogue look',
        ]));

        $variationsPrompt = $builder->premiumProviderPrompt([
            'package_key' => 'variations',
            'premium_prompt' => 'Use soft studio lighting.',
        ]);

        $this->assertIsString($variationsPrompt);
        $this->assertStringStartsWith('Create distinct fashion try-on variations.', $variationsPrompt);
        $this->assertStringContainsString('Avoid near-duplicate compositions.', $variationsPrompt);
        $this->assertStringContainsString('Creator styling note: Use soft studio lighting.', $variationsPrompt);
        $this->assertLessThanOrEqual(FashionTryOnCapabilities::premiumPromptMaxCharacters(), mb_strlen($variationsPrompt));

        $variationsMetadata = $builder->safeMetadata([
            'package_key' => 'variations',
            'premium_prompt' => 'Use soft studio lighting.',
        ], 'premium');

        $this->assertTrue($variationsMetadata['variation_diversity_requested']);
        $this->assertSame('controlled_pose_and_framing_diversity', $variationsMetadata['variation_diversity_mode']);
        $this->assertTrue($variationsMetadata['variation_prompt_applied']);
        $this->assertSame('premium_prompt', $variationsMetadata['variation_prompt_channel']);
        $this->assertTrue($variationsMetadata['user_styling_prompt_present']);
        $this->assertArrayNotHasKey('premium_prompt', $variationsMetadata);
        $this->assertArrayNotHasKey('prompt', $variationsMetadata);
    }

    public function test_variations_provider_payload_uses_supported_fields_and_diversity_prompt_where_available(): void
    {
        config()->set('fashion_try_on.variations_enabled', true);
        $user = User::factory()->admin()->create();
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);
        $provider = app(FashnFashionTryOnProvider::class);

        $standard = $provider->providerPayload('standard', $this->payload($person, $product, [
            'package_key' => 'variations',
            'requested_output_count' => 2,
            'provider_seed' => 987654,
        ]), $person, $product);

        $this->assertSame('tryon-v1.6', $standard['model_name']);
        $this->assertSame(2, data_get($standard, 'inputs.num_samples'));
        $this->assertSame(987654, data_get($standard, 'inputs.seed'));
        $this->assertEqualsCanonicalizing([
            'model_image',
            'garment_image',
            'category',
            'garment_photo_type',
            'mode',
            'segmentation_free',
            'moderation_level',
            'num_samples',
            'output_format',
            'return_base64',
            'seed',
        ], array_keys($standard['inputs']));
        $this->assertArrayNotHasKey('prompt', $standard['inputs']);
        $this->assertArrayNotHasKey('product_image', $standard['inputs']);
        $this->assertArrayNotHasKey('num_images', $standard['inputs']);

        $premium = $provider->providerPayload('premium', $this->payload($person, $product, [
            'package_key' => 'variations',
            'tier' => 'premium',
            'premium_preset' => 'fast_1k',
            'requested_output_count' => 2,
            'provider_seed' => 123456,
            'premium_prompt' => 'Keep product color accurate.',
        ]), $person, $product);

        $this->assertSame('tryon-max', $premium['model_name']);
        $this->assertSame(2, data_get($premium, 'inputs.num_images'));
        $this->assertSame(123456, data_get($premium, 'inputs.seed'));
        $this->assertStringStartsWith('Create distinct fashion try-on variations.', data_get($premium, 'inputs.prompt'));
        $this->assertStringContainsString('Keep product color accurate.', data_get($premium, 'inputs.prompt'));
        $this->assertNull(data_get($premium, 'inputs.num_samples'));
        $this->assertNull(data_get($premium, 'inputs.garment_image'));
    }

    public function test_variations_real_completion_stores_multiple_outputs_without_default_favorite(): void
    {
        Queue::fake();
        $this->enableFashn();
        config()->set('fashion_try_on.variations_enabled', true);
        $user = User::factory()->admin()->create(['credits_balance' => 20]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);
        $output = 'data:image/png;base64,'.base64_encode($this->pngBytes());

        Http::fake([
            'https://api.fashn.ai/v1/run' => Http::response(['id' => 'pred_variations_123', 'status' => 'starting'], 200),
            'https://api.fashn.ai/v1/status/pred_variations_123' => Http::response(['id' => 'pred_variations_123', 'status' => 'completed', 'output' => [$output, $output, $output]], 200, ['x-fashn-credits-used' => '3']),
        ]);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                'package_key' => 'variations',
                'requested_output_count' => 3,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.credits_charged', 6)
            ->assertJsonPath('meta.credits_balance', 14)
            ->json('data.id');

        $this->runJob($generationId);
        $generation = Generation::query()->findOrFail($generationId);
        $assets = MediaAsset::query()->where('usage_role', 'fashion_try_on_result')->orderBy('id')->get();

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertCount(3, $assets);
        $this->assertSame('variations', $generation->output_payload['package_key']);
        $this->assertSame(3, $generation->output_payload['requested_output_count']);
        $this->assertSame(3, $generation->output_payload['delivered_output_count']);
        $this->assertSame(0, $generation->output_payload['missing_output_count']);
        $this->assertFalse($generation->output_payload['partial_output']);
        $this->assertTrue($generation->input_payload['variation_diversity_requested']);
        $this->assertSame('controlled_pose_and_framing_diversity', $generation->input_payload['variation_diversity_mode']);
        $this->assertFalse($generation->input_payload['variation_prompt_applied']);
        $this->assertSame('standard_provider_contract_unavailable', $generation->input_payload['variation_prompt_channel']);
        $this->assertTrue($generation->output_payload['variation_diversity_requested']);
        $this->assertFalse($generation->output_payload['variation_prompt_applied']);
        $this->assertSame('metadata_only_no_standard_prompt_field', $generation->output_payload['prompt_strategy']);
        $this->assertSame('controlled_pose_and_framing_diversity', $generation->output_payload['provider_metadata']['variation_diversity_mode']);
        $this->assertArrayNotHasKey('prompt', $generation->output_payload['provider_metadata']);
        $this->assertArrayNotHasKey('premium_prompt', $generation->output_payload['provider_metadata']);
        $this->assertSame(0, $generation->output_payload['refunded_credit_amount']);
        $this->assertSame(6, $generation->output_payload['net_credit_cost']);
        $this->assertNull($generation->output_payload['selected_image_asset_id']);
        $this->assertNull($generation->output_payload['selected_output_index']);
        $this->assertSame($assets->pluck('id')->all(), $generation->output_payload['final_image_asset_ids']);
        $this->assertCount(3, $generation->output_payload['rendered_image_assets']);
        $this->assertSame([0, 1, 2], $assets->pluck('metadata.output_index')->all());
        $this->assertSame([3, 3, 3], $assets->pluck('metadata.output_count')->all());
        $this->assertSame(6, $generation->credits_charged);
        $this->assertSame(14, $user->refresh()->credits_balance);
        $this->assertSame(0, CreditTransaction::query()->where('generation_id', $generationId)->where('type', CreditTransactionType::Refund->value)->count());

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/run')
                && data_get($data, 'model_name') === 'tryon-v1.6'
                && data_get($data, 'inputs.num_samples') === 3
                && data_get($data, 'inputs.num_images') === null
                && data_get($data, 'inputs.prompt') === null;
        });
    }

    public function test_partial_variations_completion_refunds_missing_outputs_only(): void
    {
        Queue::fake();
        $this->enableFashn();
        config()->set('fashion_try_on.variations_enabled', true);
        $user = User::factory()->admin()->create(['credits_balance' => 20]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);
        $output = 'data:image/png;base64,'.base64_encode($this->pngBytes());

        Http::fake([
            'https://api.fashn.ai/v1/run' => Http::response(['id' => 'pred_variations_partial', 'status' => 'starting'], 200),
            'https://api.fashn.ai/v1/status/pred_variations_partial' => Http::response(['id' => 'pred_variations_partial', 'status' => 'completed', 'output' => [$output, 'not-an-image', $output]], 200, ['x-fashn-credits-used' => '3']),
        ]);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                'package_key' => 'variations',
                'requested_output_count' => 4,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.credits_charged', 8)
            ->assertJsonPath('meta.credits_balance', 12)
            ->json('data.id');

        $this->runJob($generationId);
        $generation = Generation::query()->findOrFail($generationId);
        $refund = CreditTransaction::query()->where('generation_id', $generationId)->where('type', CreditTransactionType::Refund->value)->firstOrFail();

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame(4, $generation->output_payload['requested_output_count']);
        $this->assertSame(2, $generation->output_payload['delivered_output_count']);
        $this->assertSame(2, $generation->output_payload['missing_output_count']);
        $this->assertTrue($generation->output_payload['partial_output']);
        $this->assertSame(4, $generation->output_payload['refunded_credit_amount']);
        $this->assertSame(4, $generation->output_payload['net_credit_cost']);
        $this->assertSame(4, $generation->credits_charged);
        $this->assertSame(16, $user->refresh()->credits_balance);
        $this->assertSame(4, $refund->amount);
        $this->assertSame('fashion_try_on_partial_output', $refund->metadata['refund_scope']);
        $this->assertSame('provider_returned_fewer_valid_outputs_than_requested', $generation->output_payload['provider_metadata']['provider_output_warning']);
        $this->assertCount(2, $generation->output_payload['rendered_image_assets']);
    }

    public function test_variations_selection_endpoint_marks_favorite_result_safely(): void
    {
        $user = User::factory()->create();
        $project = $this->workspaceProject($user);
        $generation = Generation::query()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'job_type' => 'generate_fashion_try_on',
            'provider' => 'fashn',
            'status' => Generation::STATUS_COMPLETED,
            'cost_usd' => 0,
            'credits_charged' => 4,
            'input_payload' => ['package_key' => 'variations'],
            'output_payload' => [
                'package_key' => 'variations',
                'generated_image' => ['status' => 'rendered', 'message' => 'Rendered', 'provider' => 'fashn'],
                'final_image_asset_ids' => [],
                'rendered_image_assets' => [],
                'selected_image_asset_id' => null,
                'selected_output_index' => null,
                'generation_summary' => ['output_count' => 2],
            ],
        ]);
        $assetA = $this->storedAsset($project, $user, 'fashion_try_on_result', [
            'metadata' => ['workflow' => 'fashion_try_on', 'generation_id' => $generation->id, 'output_index' => 0],
        ]);
        $assetB = $this->storedAsset($project, $user, 'fashion_try_on_result', [
            'metadata' => ['workflow' => 'fashion_try_on', 'generation_id' => $generation->id, 'output_index' => 1],
        ]);

        $generation->forceFill([
            'output_payload' => array_merge($generation->output_payload, [
                'final_image_asset_ids' => [$assetA->id, $assetB->id],
                'rendered_image_assets' => [
                    ['id' => $assetA->id, 'output_index' => 0],
                    ['id' => $assetB->id, 'output_index' => 1],
                ],
            ]),
        ])->save();

        $this->actingAs($user)
            ->patchJson("/api/v1/generations/{$generation->id}/fashion-try-on/selection", ['media_asset_id' => $assetB->id])
            ->assertOk()
            ->assertJsonPath('data.result_payload.selected_image_asset_id', $assetB->id)
            ->assertJsonPath('data.result_payload.selected_output_index', 1)
            ->assertJsonPath('data.result_payload.generation_summary.selected_image_asset_id', $assetB->id)
            ->assertJsonPath('data.result_payload.generation_summary.selected_output_index', 1);

        $this->actingAs(User::factory()->create())
            ->patchJson("/api/v1/generations/{$generation->id}/fashion-try-on/selection", ['media_asset_id' => $assetA->id])
            ->assertNotFound();
    }

    public function test_variations_limits_are_enforced_per_tier_and_premium_preset_before_charge(): void
    {
        Queue::fake();
        Http::fake();
        config()->set('fashion_try_on.variations_enabled', true);
        $this->enablePremiumFashn();
        config()->set('fashion_try_on.fashn.premium_quality_enabled', true);

        $user = User::factory()->admin()->create(['credits_balance' => 300]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);

        $matrix = [
            ['tier' => 'standard', 'preset' => null, 'counts' => [2, 3, 4], 'per_output_cost' => 2],
            ['tier' => 'premium', 'preset' => 'fast_1k', 'counts' => [2, 3, 4], 'per_output_cost' => 3],
            ['tier' => 'premium', 'preset' => 'balanced_2k', 'counts' => [2, 3, 4], 'per_output_cost' => 7],
            ['tier' => 'premium', 'preset' => 'quality_4k', 'counts' => [2], 'per_output_cost' => 11],
        ];

        foreach ($matrix as $case) {
            foreach ($case['counts'] as $count) {
                $payload = $this->payload($person, $product, [
                    'package_key' => 'variations',
                    'tier' => $case['tier'],
                    'requested_output_count' => $count,
                ]);

                if (is_string($case['preset'])) {
                    $payload['premium_preset'] = $case['preset'];
                }

                $generationId = $this->actingAs($user)
                    ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $payload)
                    ->assertCreated()
                    ->assertJsonPath('data.credits_charged', $case['per_output_cost'] * $count)
                    ->json('data.id');

                $generation = Generation::query()->findOrFail($generationId);
                $this->assertSame($count, $generation->input_payload['requested_output_count']);
                $this->assertSame($case['per_output_cost'] * $count, $generation->input_payload['total_nusa_credit_cost']);
                $this->assertSame($case['per_output_cost'], $generation->input_payload['per_output_credit_cost']);
                $this->assertContains($count, $generation->input_payload['allowed_output_counts']);
            }
        }

        $balanceAfterAllowedRequests = $user->refresh()->credits_balance;
        $generationCount = Generation::query()->count();

        foreach ([3, 4] as $invalidQualityCount) {
            $this->actingAs($user)
                ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                    'package_key' => 'variations',
                    'tier' => 'premium',
                    'premium_preset' => 'quality_4k',
                    'requested_output_count' => $invalidQualityCount,
                ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('requested_output_count');
        }

        $this->assertSame($balanceAfterAllowedRequests, $user->refresh()->credits_balance);
        $this->assertSame($generationCount, Generation::query()->count());
        Queue::assertPushed(GenerateFashionTryOnJob::class, 10);
        Http::assertNothingSent();
    }

    public function test_cli_rejects_quality_variation_counts_above_two_before_provider_payload(): void
    {
        Http::fake();
        config()->set('fashion_try_on.variations_enabled', true);
        config()->set('fashion_try_on.fashn.premium_quality_enabled', true);
        $project = $this->workspaceProject(User::factory()->admin()->create());

        $this->artisan('nusa:test-fashion-try-on', [
            '--project-id' => $project->id,
            '--package' => 'variations',
            '--tier' => 'premium',
            '--preset' => 'quality_4k',
            '--outputs' => '3',
            '--dry-run' => true,
        ])
            ->expectsOutput('Fashion Try-On setup failed safely: fashn_input_validation')
            ->assertFailed();

        $this->artisan('nusa:test-fashion-try-on', [
            '--project-id' => $project->id,
            '--package' => 'variations',
            '--tier' => 'premium',
            '--preset' => 'quality_4k',
            '--outputs' => '4',
            '--dry-run' => true,
        ])->assertFailed();

        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_output_byte_limits_accept_confirmed_quality_4k_sized_output_equivalent(): void
    {
        Queue::fake();
        $this->enableFashn();
        $user = User::factory()->admin()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);
        $output = 'data:image/png;base64,'.base64_encode($this->pngBytesOfSize(18_268_510));

        Http::fake([
            'https://api.fashn.ai/v1/run' => Http::response(['id' => 'pred_large_4k', 'status' => 'starting'], 200),
            'https://api.fashn.ai/v1/status/pred_large_4k' => Http::response(['id' => 'pred_large_4k', 'status' => 'completed', 'output' => [$output]], 200),
        ]);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product))
            ->assertCreated()
            ->json('data.id');

        $this->runJob($generationId);
        $generation = Generation::query()->findOrFail($generationId);
        $asset = MediaAsset::query()->where('usage_role', 'fashion_try_on_result')->firstOrFail();

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame(18_268_510, $asset->size_bytes);
        $this->assertSame(18_268_510, $generation->output_payload['provider_metadata']['total_decoded_output_bytes']);
        $this->assertSame(33_554_432, $generation->output_payload['provider_metadata']['max_decoded_bytes_per_output']);
        $this->assertSame(134_217_728, $generation->output_payload['provider_metadata']['max_decoded_bytes_total']);
    }

    public function test_output_byte_limits_reject_oversized_individual_output_safely(): void
    {
        Queue::fake();
        $this->enableFashn();
        config()->set('fashion_try_on.fashn.output_max_bytes_per_image', 128);
        config()->set('fashion_try_on.fashn.output_max_bytes_total', 1024);
        $user = User::factory()->admin()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);
        $output = 'data:image/png;base64,'.base64_encode($this->pngBytesOfSize(129));

        Http::fake([
            'https://api.fashn.ai/v1/run' => Http::response(['id' => 'pred_oversized_one', 'status' => 'starting'], 200),
            'https://api.fashn.ai/v1/status/pred_oversized_one' => Http::response(['id' => 'pred_oversized_one', 'status' => 'completed', 'output' => [$output]], 200),
        ]);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product))
            ->assertCreated()
            ->json('data.id');

        try {
            $this->runJob($generationId);
            $this->fail('Expected oversized output to be rejected.');
        } catch (FashionTryOnProviderException $caught) {
            (new GenerateFashionTryOnJob($generationId))->failed($caught);
        }

        $this->assertSame(0, MediaAsset::query()->where('usage_role', 'fashion_try_on_result')->count());
        $this->assertSame(Generation::STATUS_FAILED, Generation::query()->findOrFail($generationId)->status);
    }

    public function test_total_decoded_byte_limit_is_enforced_with_partial_refund(): void
    {
        Queue::fake();
        $this->enableFashn();
        config()->set('fashion_try_on.variations_enabled', true);
        config()->set('fashion_try_on.fashn.output_max_bytes_per_image', 512);
        config()->set('fashion_try_on.fashn.output_max_bytes_total', 300);
        $user = User::factory()->admin()->create(['credits_balance' => 10]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);
        $output = 'data:image/png;base64,'.base64_encode($this->pngBytesOfSize(300));

        Http::fake([
            'https://api.fashn.ai/v1/run' => Http::response(['id' => 'pred_total_limit', 'status' => 'starting'], 200),
            'https://api.fashn.ai/v1/status/pred_total_limit' => Http::response(['id' => 'pred_total_limit', 'status' => 'completed', 'output' => [$output, $output]], 200),
        ]);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                'package_key' => 'variations',
                'requested_output_count' => 2,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.credits_charged', 4)
            ->json('data.id');

        $this->runJob($generationId);
        $generation = Generation::query()->findOrFail($generationId);

        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame(1, $generation->output_payload['delivered_output_count']);
        $this->assertSame(1, $generation->output_payload['missing_output_count']);
        $this->assertSame(2, $generation->output_payload['refunded_credit_amount']);
        $this->assertSame(2, $generation->credits_charged);
        $this->assertSame(8, $user->refresh()->credits_balance);
        $this->assertSame(300, $generation->output_payload['provider_metadata']['total_decoded_output_bytes']);
        $this->assertSame(1, $generation->output_payload['provider_metadata']['invalid_output_count']);
    }

    public function test_internal_unlimited_partial_variations_do_not_create_positive_refund_or_balance_change(): void
    {
        Queue::fake();
        $this->enableFashn();
        config()->set('fashion_try_on.variations_enabled', true);
        $user = User::factory()->internalCreator()->create(['credits_balance' => 0]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);
        $output = 'data:image/png;base64,'.base64_encode($this->pngBytes());

        Http::fake([
            'https://api.fashn.ai/v1/run' => Http::response(['id' => 'pred_internal_partial', 'status' => 'starting'], 200),
            'https://api.fashn.ai/v1/status/pred_internal_partial' => Http::response(['id' => 'pred_internal_partial', 'status' => 'completed', 'output' => [$output, 'bad-output', 'bad-output', 'bad-output']], 200),
        ]);

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, [
                'package_key' => 'variations',
                'requested_output_count' => 4,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.credits_charged', 0)
            ->assertJsonPath('meta.credits_balance', 0)
            ->json('data.id');

        $this->runJob($generationId);
        $generation = Generation::query()->findOrFail($generationId);
        $usage = CreditTransaction::query()->where('generation_id', $generationId)->where('type', CreditTransactionType::Usage->value)->firstOrFail();
        $refund = CreditTransaction::query()->where('generation_id', $generationId)->where('type', CreditTransactionType::Refund->value)->firstOrFail();

        $this->assertSame(0, $usage->amount);
        $this->assertSame(0, $refund->amount);
        $this->assertSame(0, $refund->balance_after);
        $this->assertSame(0, $user->refresh()->credits_balance);
        $this->assertSame(0, $generation->credits_charged);
        $this->assertSame(0, $generation->output_payload['refunded_credit_amount']);
        $this->assertSame(0, $generation->output_payload['net_credit_cost']);
        $this->assertSame(6, $refund->metadata['requested_refund_amount']);
        $this->assertSame(0, $refund->metadata['refunded_credit_amount']);
    }

    public function test_variations_resume_reuses_existing_output_assets_and_does_not_resubmit_prediction(): void
    {
        $this->enableFashn();
        config()->set('fashion_try_on.variations_enabled', true);
        $user = User::factory()->admin()->create(['credits_balance' => 20]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);
        $generation = Generation::query()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'job_type' => 'generate_fashion_try_on',
            'provider' => 'fashn',
            'status' => Generation::STATUS_PROCESSING,
            'cost_usd' => 0,
            'credits_charged' => 6,
            'input_payload' => $this->payload($person, $product, [
                'project_id' => $project->id,
                'user_id' => $user->id,
                'package_key' => 'variations',
                'requested_output_count' => 3,
                'configured_nusa_credit_cost' => 6,
                'per_output_credit_cost' => 2,
                'provider_prediction_id' => 'pred_resume_variations',
            ]),
            'output_payload' => null,
        ]);
        $existing = $this->storedAsset($project, $user, 'fashion_try_on_result', [
            'metadata' => ['workflow' => 'fashion_try_on', 'generation_id' => $generation->id, 'output_index' => 0],
        ]);
        $output = 'data:image/png;base64,'.base64_encode($this->pngBytes());

        Http::fake([
            'https://api.fashn.ai/v1/status/pred_resume_variations' => Http::response(['id' => 'pred_resume_variations', 'status' => 'completed', 'output' => [$output, $output, $output]], 200),
        ]);

        $this->runJob($generation->id);
        $this->runJob($generation->id);

        $generation->refresh();
        $this->assertSame(Generation::STATUS_COMPLETED, $generation->status);
        $this->assertSame($existing->id, $generation->output_payload['final_image_asset_ids'][0]);
        $this->assertSame([0, 1, 2], collect($generation->output_payload['rendered_image_assets'])->pluck('output_index')->all());
        $this->assertSame(3, MediaAsset::query()->where('usage_role', 'fashion_try_on_result')->count());
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/run'));
    }

    private function enableTool(): void
    {
        CreatorTool::query()->updateOrCreate(['key' => 'fashion_try_on'], [
            'title' => 'AI Fashion Try-On',
            'short_description' => 'Combine a person photo with a clothing or wearable product image to create a realistic fashion result.',
            'long_description' => 'Create an AI Fashion Try-On from one person photo and one clothing or wearable product image.',
            'category' => 'fashion',
            'service_tier' => 'premium',
            'output_type' => 'image',
            'implementation_stage' => 'real_provider',
            'status' => 'available',
            'route' => '/dashboard?tool=ai_fashion_try_on',
            'credit_cost' => 2,
            'credit_cost_label' => '2 credits',
            'badge_label' => 'Available now',
            'is_recommended' => false,
            'is_visible' => true,
            'sort_order' => 50,
            'metadata' => [],
        ]);
    }

    private function enableFashn(string $apiKey = 'fashn-test-key'): void
    {
        config()->set('fashion_try_on.provider', 'fashn');
        config()->set('fashion_try_on.real_enabled', true);
        config()->set('fashion_try_on.real_internal_only', true);
        config()->set('fashion_try_on.fashn.api_key', $apiKey);
        config()->set('fashion_try_on.fashn.base_url', 'https://api.fashn.ai/v1');
        config()->set('fashion_try_on.fashn.standard_enabled', true);
        config()->set('fashion_try_on.fashn.premium_enabled', false);
        config()->set('fashion_try_on.fashn.poll_interval_seconds', 0);
        config()->set('fashion_try_on.fashn.max_poll_attempts', 1);
    }

    private function enablePremiumFashn(string $apiKey = 'fashn-test-key'): void
    {
        $this->enableFashn($apiKey);
        config()->set('fashion_try_on.fashn.premium_enabled', true);
        config()->set('fashion_try_on.fashn.premium_quality_enabled', false);
        config()->set('fashion_try_on.fashn.premium_model', 'tryon-max');
        config()->set('fashion_try_on.fashn.premium_default_preset', 'balanced_2k');
        config()->set('fashion_try_on.credit_costs.premium_presets.fast_1k', 3);
        config()->set('fashion_try_on.credit_costs.premium_presets.balanced_2k', 7);
        config()->set('fashion_try_on.credit_costs.premium_presets.quality_4k', 11);
    }

    private function workspaceProject(User $user, array $overrides = []): Project
    {
        return Project::factory()->for($user)->create([
            'title' => 'Creator jacket launch',
            'type' => 'workspace',
            'status' => 'draft',
            'input_payload' => array_merge([
                'product_name' => 'Everyday creator jacket',
                'product_description' => 'A lightweight jacket for everyday content shoots.',
                'target_audience' => 'Fashion affiliate creators',
                'language' => 'english',
                'platform' => 'tiktok',
            ], $overrides),
            'settings' => ['platform' => 'tiktok', 'format' => 'vertical_9_16'],
        ]);
    }

    private function attachAssets(Project $project, User $user): array
    {
        return [
            $this->storedAsset($project, $user, 'model_reference', ['original_name' => 'person-reference.png']),
            $this->storedAsset($project, $user, 'garment_reference', ['original_name' => 'garment-reference.png']),
        ];
    }

    private function storedAsset(Project $project, User $user, string $role, array $attributes = [], ?string $contents = null): MediaAsset
    {
        $contents ??= $this->pngBytes();
        $asset = $this->assetRecord($project, $user, $role, $attributes);
        Storage::disk('media-test')->put($asset->path, $contents);
        $asset->forceFill(['size_bytes' => strlen($contents)])->save();

        return $asset->refresh();
    }

    private function assetRecord(Project $project, User $user, string $role, array $attributes = []): MediaAsset
    {
        return MediaAsset::factory()->for($user)->for($project)->create(array_merge([
            'usage_role' => $role,
            'type' => 'image',
            'disk' => 'media-test',
            'path' => 'test-media/'.uniqid('', true).'.png',
            'original_name' => $role.'.png',
            'mime_type' => 'image/png',
            'size_bytes' => strlen($this->pngBytes()),
        ], $attributes));
    }

    private function payload(MediaAsset $person, MediaAsset $product, array $overrides = []): array
    {
        return array_merge([
            'tier' => 'standard',
            'person_image_asset_id' => $person->id,
            'product_image_asset_id' => $product->id,
            'category' => 'auto',
            'garment_photo_type' => 'auto',
            'standard_mode' => 'balanced',
            'person_image_permission_confirmed' => true,
        ], $overrides);
    }

    private function runJob(int $generationId): void
    {
        (new GenerateFashionTryOnJob($generationId))->handle(app(FashionTryOnGenerationProcessor::class));
    }

    private function pngBytesOfSize(int $size): string
    {
        $png = $this->pngBytes();

        if ($size <= strlen($png)) {
            return substr($png, 0, $size);
        }

        return $png.str_repeat("\0", $size - strlen($png));
    }

    private function pngBytes(): string
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true);

        if (! is_string($bytes)) {
            throw new RuntimeException('Test PNG fixture could not be decoded.');
        }

        return $bytes;
    }
}
