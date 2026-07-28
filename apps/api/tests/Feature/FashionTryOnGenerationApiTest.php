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
            ->assertJsonPath('data.premium_enabled', false)
            ->assertJsonPath('data.maximum_outputs', 1);

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
            'quality_preset' => 'premium_balanced', 'prompt' => 'Clean catalogue look',
        ]), $person, $product);

        $this->assertSame('tryon-max', $premium['model_name']);
        $this->assertStringStartsWith('data:image/png;base64,', data_get($premium, 'inputs.model_image'));
        $this->assertStringStartsWith('data:image/png;base64,', data_get($premium, 'inputs.product_image'));
        $this->assertArrayNotHasKey('garment_image', $premium['inputs']);
        $this->assertSame('2k', data_get($premium, 'inputs.resolution'));
        $this->assertSame('balanced', data_get($premium, 'inputs.generation_mode'));
        $this->assertSame(1, data_get($premium, 'inputs.num_images'));
    }

    public function test_premium_tier_is_rejected_while_disabled_and_client_cannot_submit_model_name(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits_balance' => 5]);
        $project = $this->workspaceProject($user);
        [$person, $product] = $this->attachAssets($project, $user);

        $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, ['tier' => 'premium']))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'fashn_premium_disabled');

        $generationId = $this->actingAs($user)
            ->postJson("/api/v1/projects/{$project->id}/generate-fashion-try-on", $this->payload($person, $product, ['model_name' => 'tryon-max']))
            ->assertCreated()
            ->json('data.id');

        $this->assertArrayNotHasKey('model_name', Generation::query()->findOrFail($generationId)->input_payload);
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
        $this->assertNull($generation->output_payload['provider_metadata']['actual_provider_cost_usd']);
        $this->assertSame($asset->id, $generation->output_payload['final_image_asset_id']);
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
