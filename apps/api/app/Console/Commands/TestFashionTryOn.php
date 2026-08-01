<?php

namespace App\Console\Commands;

use App\Exceptions\FashionTryOn\FashionTryOnProviderException;
use App\Jobs\GenerateFashionTryOnJob;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Services\Generation\FashionTryOnAssetResolver;
use App\Services\Generation\FashionTryOnGenerationProcessor;
use App\Services\Generation\FashionTryOnGenerationService;
use App\Services\Generation\FashnFashionTryOnClient;
use App\Services\Generation\FashnFashionTryOnProvider;
use App\Support\FashionTryOnCapabilities;
use Illuminate\Console\Command;
use Throwable;

class TestFashionTryOn extends Command
{
    protected $signature = 'nusa:test-fashion-try-on
        {--tier=standard : standard or premium}
        {--preset= : Premium preset: fast_1k, balanced_2k, quality_4k}
        {--prompt= : Optional Premium styling instruction; never printed}
        {--seed= : Optional diagnostic unsigned 32-bit Premium seed}
        {--project-id= : Project ID containing the private image assets}
        {--person-image-asset-id= : Person/model image MediaAsset ID}
        {--product-image-asset-id= : Clothing/product image MediaAsset ID}
        {--category=auto : Standard category: auto, tops, bottoms, one-pieces}
        {--dry-run : Only resolve assets and print a safe provider request summary}
        {--yes : Required before making a real provider call}';

    protected $description = 'Safely dry-run or manually test the FASHN AI Fashion Try-On provider path';

    public function __construct(
        private readonly FashionTryOnAssetResolver $assets,
        private readonly FashnFashionTryOnProvider $fashn,
        private readonly FashnFashionTryOnClient $client,
        private readonly FashionTryOnGenerationService $generationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This development helper is only available in local and testing environments.');

            return self::FAILURE;
        }

        try {
            $project = $this->project();
            $user = $project->user()->firstOrFail();
            $tier = $this->tier();

            if ($tier === FashionTryOnCapabilities::TIER_PREMIUM && ! (bool) config('fashion_try_on.fashn.premium_enabled', false)) {
                $this->error('Premium is prepared but disabled. Set FASHN_PREMIUM_ENABLED=true only for controlled validation.');

                return self::FAILURE;
            }

            if ($tier === FashionTryOnCapabilities::TIER_PREMIUM
                && $this->premiumPresetOption() === FashionTryOnCapabilities::PREMIUM_PRESET_QUALITY_4K
                && ! FashionTryOnCapabilities::premiumQualityEnabled()) {
                $this->error('Premium Quality 4K is visible but gated. Set FASHN_PREMIUM_QUALITY_ENABLED=true only after controlled provider validation.');

                return self::FAILURE;
            }

            $personImage = $this->assets->resolveProjectImage($user, $project, $this->requiredIntegerOption('person-image-asset-id'), 'person_image');
            $productImage = $this->assets->resolveProjectImage($user, $project, $this->requiredIntegerOption('product-image-asset-id'), 'product_image');
            $input = $this->buildInputPayload($project, $tier, $personImage, $productImage);
            $payload = $this->fashn->providerPayload($tier, $input, $personImage, $productImage);
            $summary = $this->summary($project, $tier, $personImage, $productImage, $payload, $input);
        } catch (FashionTryOnProviderException $exception) {
            $this->error('Fashion Try-On setup failed safely: '.$exception->reason);

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Fashion Try-On setup failed safely. Check application logs for internal diagnostics.');

            return self::FAILURE;
        }

        $this->info('Safe FASHN Fashion Try-On request summary:');
        $this->table(['key', 'value'], collect($summary)->map(fn (mixed $value, string $key): array => [$key, (string) $value])->all());

        if ($this->option('dry-run')) {
            $this->comment('Dry run only. No FASHN provider call was made. No base64, private path, prompt text or API key was printed.');

            return self::SUCCESS;
        }

        if (FashionTryOnCapabilities::selectedProviderForUser($project->user) !== 'fashn') {
            $this->error('Real FASHN provider is not selected for this user. Set FASHION_TRY_ON_PROVIDER=fashn and FASHION_TRY_ON_REAL_ENABLED=true for an internal/admin test user.');

            return self::FAILURE;
        }

        if (! $this->option('yes') && ! $this->confirm('Make one real FASHN provider call now?', false)) {
            $this->comment('Cancelled before provider call.');

            return self::SUCCESS;
        }

        try {
            $generation = $this->generationService->generate($project, $project->user, $input)->refresh();

            if (in_array($generation->status, [Generation::STATUS_QUEUED, 'pending'], true)) {
                $job = new GenerateFashionTryOnJob((int) $generation->getKey());

                try {
                    $job->handle(app(FashionTryOnGenerationProcessor::class));
                } catch (Throwable $exception) {
                    $job->failed($exception);

                    throw $exception;
                }

                $generation->refresh();
            }
        } catch (FashionTryOnProviderException $exception) {
            $this->error('FASHN Fashion Try-On request failed safely: '.$exception->reason);

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('FASHN Fashion Try-On request failed safely. Check application logs for internal diagnostics.');

            return self::FAILURE;
        }

        $this->info('Fashion Try-On generation finished with status: '.$generation->status);
        $this->line('Generation ID: '.$generation->getKey());
        $this->line('Provider: '.$generation->provider);
        $this->line('Final image asset ID: '.(data_get($generation->output_payload, 'final_image_asset_id') ?? 'none'));

        return $generation->status === Generation::STATUS_COMPLETED ? self::SUCCESS : self::FAILURE;
    }

    private function project(): Project
    {
        $projectId = $this->requiredIntegerOption('project-id');
        $project = Project::query()->with('user')->find($projectId);

        if (! $project instanceof Project) {
            throw FashionTryOnProviderException::invalidInput('fashn_input_validation', [
                'stage' => 'cli_project_lookup',
                'project_id' => $projectId,
            ]);
        }

        return $project;
    }

    private function tier(): string
    {
        return FashionTryOnCapabilities::normalizeTier((string) $this->option('tier'));
    }

    private function requiredIntegerOption(string $name): int
    {
        $value = $this->option($name);

        if (! is_numeric($value) || (int) $value <= 0) {
            throw FashionTryOnProviderException::invalidInput('fashn_input_validation', [
                'stage' => 'cli_option_validation',
                'option' => $name,
            ]);
        }

        return (int) $value;
    }

    private function premiumPresetOption(): string
    {
        $value = $this->option('preset');
        $preset = is_string($value) && trim($value) !== ''
            ? trim($value)
            : FashionTryOnCapabilities::defaultPremiumPresetKey();

        if (! in_array($preset, FashionTryOnCapabilities::premiumPresetKeys(), true)) {
            throw FashionTryOnProviderException::invalidInput('fashn_input_validation', [
                'stage' => 'cli_option_validation',
                'option' => 'preset',
            ]);
        }

        return $preset;
    }

    private function seedOption(): int
    {
        $value = $this->option('seed');

        if ($value === null || $value === '') {
            return FashionTryOnCapabilities::generateProviderSeed();
        }

        $seed = FashionTryOnCapabilities::normalizeProviderSeed($value);

        if ($seed === null) {
            throw FashionTryOnProviderException::invalidInput('fashn_input_validation', [
                'stage' => 'cli_option_validation',
                'option' => 'seed',
            ]);
        }

        return $seed;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInputPayload(Project $project, string $tier, MediaAsset $personImage, MediaAsset $productImage): array
    {
        $input = [
            'project_id' => $project->getKey(),
            'user_id' => $project->user_id,
            'package_key' => FashionTryOnCapabilities::PACKAGE_SINGLE_RESULT,
            'person_source' => FashionTryOnCapabilities::PERSON_SOURCE_USER_UPLOAD,
            'tier' => $tier,
            'person_image_asset_id' => $personImage->getKey(),
            'product_image_asset_id' => $productImage->getKey(),
            'person_image_permission_confirmed' => true,
        ];

        if ($tier === FashionTryOnCapabilities::TIER_PREMIUM) {
            $input['premium_preset'] = $this->premiumPresetOption();
            $input['premium_prompt'] = FashionTryOnCapabilities::sanitizePremiumPrompt($this->option('prompt'));
            $input['provider_seed'] = $this->seedOption();

            return array_filter($input, fn (mixed $value): bool => $value !== null && $value !== '');
        }

        $input['category'] = (string) $this->option('category');

        return $input;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function summary(Project $project, string $tier, MediaAsset $personImage, MediaAsset $productImage, array $payload, array $input): array
    {
        $inputs = is_array($payload['inputs'] ?? null) ? $payload['inputs'] : [];
        $network = $this->client->safeNetworkSummary();
        $capabilities = FashionTryOnCapabilities::forUser($project->user);
        $preset = $tier === FashionTryOnCapabilities::TIER_PREMIUM
            ? FashionTryOnCapabilities::premiumPreset(FashionTryOnCapabilities::premiumPresetKeyFromInput($input))
            : null;
        $presetCost = is_array($preset) ? FashionTryOnCapabilities::premiumCreditCost($preset['key']) : null;
        $expectedProviderCredits = is_array($preset) ? (int) $preset['expected_provider_credits'] : null;

        return array_filter([
            'provider' => 'fashn',
            'tier' => $tier,
            'model' => (string) ($payload['model_name'] ?? 'unknown'),
            'premium_enabled' => ((bool) config('fashion_try_on.fashn.premium_enabled', false)) ? 'true' : 'false',
            'premium_quality_enabled' => FashionTryOnCapabilities::premiumQualityEnabled() ? 'true' : 'false',
            'premium_preset' => $preset['key'] ?? null,
            'resolution' => $inputs['resolution'] ?? null,
            'quality_mode' => $inputs['generation_mode'] ?? $inputs['mode'] ?? 'unknown',
            'output_count' => $inputs['num_samples'] ?? $inputs['num_images'] ?? 1,
            'person_image_asset_id' => $personImage->getKey(),
            'person_mime' => $personImage->mime_type,
            'person_size_bytes' => $personImage->size_bytes,
            'product_image_asset_id' => $productImage->getKey(),
            'product_mime' => $productImage->mime_type,
            'product_size_bytes' => $productImage->size_bytes,
            'category' => $inputs['category'] ?? null,
            'prompt_provided' => isset($input['premium_prompt']) ? 'true' : 'false',
            'seed' => $inputs['seed'] ?? null,
            'return_base64' => ($inputs['return_base64'] ?? false) ? 'true' : 'false',
            'payload_byte_size' => strlen(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: ''),
            'expected_provider_credits' => $expectedProviderCredits,
            'estimated_provider_cost_usd' => FashionTryOnCapabilities::estimatedProviderCostUsd($expectedProviderCredits) ?? 'unavailable',
            'nusa_credit_pricing_configured' => $presetCost !== null ? 'true' : 'false',
            'nusa_credit_cost' => $presetCost ?? 'unset',
            'public_enabled' => $capabilities['public_enabled'] ? 'true' : 'false',
            'real_provider_configured_for_user' => FashionTryOnCapabilities::selectedProviderForUser($project->user) === 'fashn' ? 'true' : 'false',
            'api_key_configured' => config('fashion_try_on.fashn.api_key') ? 'true' : 'false',
            'real_call_allowed' => (FashionTryOnCapabilities::selectedProviderForUser($project->user) === 'fashn' && config('fashion_try_on.fashn.api_key') && ($tier === FashionTryOnCapabilities::TIER_STANDARD || (bool) config('fashion_try_on.fashn.premium_enabled', false))) ? 'true' : 'false',
            'connect_timeout_seconds' => $network['connect_timeout_seconds'],
            'request_timeout_seconds' => $network['request_timeout_seconds'],
            'poll_interval_seconds' => $network['poll_interval_seconds'],
            'max_poll_attempts' => $network['max_poll_attempts'],
            'submit_endpoint_host' => $network['endpoint_host'],
            'submit_endpoint_path' => $network['submit_endpoint_path'],
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
