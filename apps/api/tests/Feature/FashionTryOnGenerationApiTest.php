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
            ->assertJsonPath('data.maximum_outputs', 1)
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
        $this->assertSame('provider_returned_more_outputs_than_requested_first_output_used', $generation->output_payload['provider_metadata']['provider_output_warning']);
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

    private function pngBytes(): string
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true);

        if (! is_string($bytes)) {
            throw new RuntimeException('Test PNG fixture could not be decoded.');
        }

        return $bytes;
    }
}
