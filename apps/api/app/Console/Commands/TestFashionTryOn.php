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
        {--project-id= : Project ID containing the private image assets}
        {--person-image-asset-id= : Person/model image MediaAsset ID}
        {--product-image-asset-id= : Clothing/product image MediaAsset ID}
        {--category=auto : Standard category: auto, tops, bottoms, one-pieces}
        {--preset= : Premium preset key, prepared but disabled until validation}
        {--prompt= : Optional Premium styling instruction; not printed}
        {--dry-run : Only resolve assets and print a safe provider request summary}
        {--yes : Required before making a real provider call}';

    protected $description = 'Safely dry-run or manually test the FASHN AI Fashion Try-On Standard provider path';

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

            if ($tier === FashionTryOnCapabilities::TIER_PREMIUM && ! FashionTryOnCapabilities::premiumEnabledForUser($user)) {
                $this->error('Premium is prepared but disabled. Set FASHN_PREMIUM_ENABLED=true only after Standard has been validated.');

                return self::FAILURE;
            }

            $personImage = $this->assets->resolveProjectImage($user, $project, $this->requiredIntegerOption('person-image-asset-id'), 'person_image');
            $productImage = $this->assets->resolveProjectImage($user, $project, $this->requiredIntegerOption('product-image-asset-id'), 'product_image');
            $input = $this->buildInputPayload($project, $tier, $personImage, $productImage);
            $payload = $this->fashn->providerPayload($tier, $input, $personImage, $productImage);
            $summary = $this->summary($project, $tier, $personImage, $productImage, $payload);
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
        $tier = FashionTryOnCapabilities::normalizeTier((string) $this->option('tier'));

        return $tier;
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

    /**
     * @return array<string, mixed>
     */
    private function buildInputPayload(Project $project, string $tier, MediaAsset $personImage, MediaAsset $productImage): array
    {
        return [
            'project_id' => $project->getKey(),
            'user_id' => $project->user_id,
            'tier' => $tier,
            'person_image_asset_id' => $personImage->getKey(),
            'product_image_asset_id' => $productImage->getKey(),
            'category' => (string) $this->option('category'),
            'quality_preset' => $this->option('preset') ? (string) $this->option('preset') : null,
            'prompt' => $this->option('prompt') ? (string) $this->option('prompt') : null,
            'person_image_permission_confirmed' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function summary(Project $project, string $tier, MediaAsset $personImage, MediaAsset $productImage, array $payload): array
    {
        $inputs = is_array($payload['inputs'] ?? null) ? $payload['inputs'] : [];
        $network = $this->client->safeNetworkSummary();
        $capabilities = FashionTryOnCapabilities::forUser($project->user);

        return [
            'provider' => 'fashn',
            'tier' => $tier,
            'model' => (string) ($payload['model_name'] ?? 'unknown'),
            'public_enabled' => $capabilities['public_enabled'] ? 'true' : 'false',
            'real_rendering_enabled_for_user' => FashionTryOnCapabilities::selectedProviderForUser($project->user) === 'fashn' ? 'true' : 'false',
            'person_image_asset_id' => $personImage->getKey(),
            'person_mime' => $personImage->mime_type,
            'person_size_bytes' => $personImage->size_bytes,
            'product_image_asset_id' => $productImage->getKey(),
            'product_mime' => $productImage->mime_type,
            'product_size_bytes' => $productImage->size_bytes,
            'category' => $inputs['category'] ?? 'premium_not_applicable',
            'provider_quality_mode_or_preset' => $inputs['mode'] ?? $inputs['generation_mode'] ?? 'unknown',
            'output_count' => $inputs['num_samples'] ?? $inputs['num_images'] ?? 1,
            'return_base64' => ($inputs['return_base64'] ?? false) ? 'true' : 'false',
            'payload_byte_size' => strlen(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: ''),
            'connect_timeout_seconds' => $network['connect_timeout_seconds'],
            'request_timeout_seconds' => $network['request_timeout_seconds'],
            'poll_interval_seconds' => $network['poll_interval_seconds'],
            'max_poll_attempts' => $network['max_poll_attempts'],
            'estimated_provider_credits' => 'unavailable before provider response',
            'estimated_provider_cost_usd_per_credit' => config('fashion_try_on.fashn.estimated_cost_usd_per_credit') ?? 'unset',
            'api_key_configured' => config('fashion_try_on.fashn.api_key') ? 'true' : 'false',
            'real_call_allowed' => (FashionTryOnCapabilities::selectedProviderForUser($project->user) === 'fashn' && config('fashion_try_on.fashn.api_key')) ? 'true' : 'false',
        ];
    }
}
