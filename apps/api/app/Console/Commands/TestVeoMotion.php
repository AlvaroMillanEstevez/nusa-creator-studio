<?php

namespace App\Console\Commands;

use App\Exceptions\MotionAiVideo\MotionAiVideoProviderException;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\GeneratedEditedVideoMediaService;
use App\Services\Generation\GoogleVeoMotionAiVideoClient;
use App\Services\Generation\GoogleVeoMotionAiVideoPromptBuilder;
use App\Services\Generation\MockMotionAiVideoProvider;
use App\Support\MotionAiVideoCapabilities;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class TestVeoMotion extends Command
{
    protected $signature = 'nusa:test-veo-motion
        {--mode=text-only : text-only or campaign-assisted. Use nusa:test-veo-motion-sdk for image-first-frame; reference-images is disabled}
        {--prompt= : Prompt for the selected test mode}
        {--campaign-generation-id= : Completed campaign generation ID for campaign-assisted mode}
        {--image-asset-id= : Product image MediaAsset ID for image-first-frame/reference-images mode}
        {--generation-id= : Legacy alias for --campaign-generation-id, or optional output context}
        {--asset-id= : Legacy alias for --image-asset-id}
        {--project-id= : Optional project context for safer asset resolution and saving output}
        {--user-id= : Optional user context for saving output as MediaAsset}
        {--yes : Skip confirmation before making a real provider call}
        {--dry-run : Only print a safe request summary}';

    protected $description = 'Safely dry-run or test the Google Veo Motion AI Video request contract locally';

    public function __construct(
        private readonly GoogleVeoMotionAiVideoClient $client,
        private readonly GeneratedEditedVideoMediaService $videos,
        private readonly GoogleVeoMotionAiVideoPromptBuilder $promptBuilder,
        private readonly MockMotionAiVideoProvider $planner,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This development helper is only available in local and testing environments.');

            return self::FAILURE;
        }

        $mode = $this->mode();

        if (in_array($mode, ['image-first-frame', 'reference-images'], true)) {
            $this->error($mode === 'image-first-frame'
                ? 'Image-first-frame uses the official SDK bridge. Run php artisan nusa:test-veo-motion-sdk for CLI diagnostics.'
                : 'Reference-images mode is disabled until that provider path is separately verified.');

            return self::FAILURE;
        }

        $requestMode = MotionAiVideoCapabilities::normalizeRequestMode();
        $imageToVideoEnabled = $this->imageToVideoEnabled();
        $imageReferencesEnabled = $this->imageReferencesEnabled();
        try {
            $campaignGeneration = $mode === 'campaign-assisted' ? $this->campaignGenerationOrFail() : $this->campaignGeneration();
            $asset = in_array($mode, ['image-first-frame', 'reference-images'], true) ? $this->imageAssetOrFail() : $this->asset();
            $sourceMode = $this->sourceMode($mode);
            $prompt = $this->promptForMode($mode, $sourceMode, $campaignGeneration, $asset);
            $providerRequestShape = $this->providerRequestShape($mode, $asset, $imageToVideoEnabled, $imageReferencesEnabled);
            $payload = $this->payloadForShape($providerRequestShape, $prompt['prompt'], $asset);
        } catch (MotionAiVideoProviderException $exception) {
            $this->error('Google Veo request setup failed safely: '.$exception->reason);

            return self::FAILURE;
        }
        $networkSummary = $this->client->safeNetworkSummary('submit');
        $summary = $this->summary(
            mode: $mode,
            sourceMode: $sourceMode,
            requestMode: $requestMode,
            providerRequestShape: $providerRequestShape,
            imageToVideoEnabled: $imageToVideoEnabled,
            imageReferencesEnabled: $imageReferencesEnabled,
            campaignGeneration: $campaignGeneration,
            asset: $asset,
            prompt: $prompt,
            payload: $payload,
            networkSummary: $networkSummary,
        );

        $this->info('Safe Google Veo request summary:');
        $this->table(['key', 'value'], collect($summary)->map(fn (mixed $value, string $key): array => [$key, (string) $value])->all());

        if ($this->option('dry-run')) {
            $this->comment('Dry run only. No provider call was made. No base64 or full prompt was printed.');

            return self::SUCCESS;
        }

        if ($mode === 'image-first-frame' && ! $imageToVideoEnabled) {
            $this->error('Image-first-frame mode is disabled. Set GOOGLE_VEO_IMAGE_TO_VIDEO_ENABLED=true before a real probe.');

            return self::FAILURE;
        }

        if ($mode === 'reference-images' && ! $imageReferencesEnabled) {
            $this->error('Reference-image mode is disabled. It will remain unavailable until the provider path is separately verified.');

            return self::FAILURE;
        }

        if (! $this->option('yes') && ! $this->confirm('Make a real Google Veo provider call now?', false)) {
            $this->comment('Cancelled before provider call.');

            return self::SUCCESS;
        }

        $context = [
            'provider' => 'google_veo',
            'workflow' => 'motion_ai_video',
            'stage' => 'cli_test',
            'request_mode' => $requestMode,
            'provider_request_shape' => $providerRequestShape,
            'source_mode' => $sourceMode,
            'has_product_image' => $asset instanceof MediaAsset,
            'product_image_asset_id' => $asset?->getKey(),
            'campaign_generation_id' => $campaignGeneration?->getKey(),
        ];

        try {
            $submit = $this->client->submit($payload, $context);
            $completion = is_array($submit['video'] ?? null)
                ? ['video' => $submit['video'], 'render_status' => 'done']
                : $this->client->waitForCompletion((string) $submit['operation_name'], $context);
            $binary = $this->client->videoBinary($completion['video'], $context + ['stage' => 'cli_download']);
        } catch (MotionAiVideoProviderException $exception) {
            $this->error('Google Veo request failed safely: '.$exception->reason);

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Google Veo request failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $stored = $this->storeDebugOutput($binary, $campaignGeneration);
        $this->info('Google Veo test completed.');
        $this->line('Stored output: '.$stored);

        return self::SUCCESS;
    }

    private function mode(): string
    {
        $mode = Str::of((string) $this->option('mode'))->lower()->replace('_', '-')->toString();

        return match ($mode) {
            'campaign', 'campaign-assisted' => 'campaign-assisted',
            'image-first-frame', 'image-to-video', 'first-frame' => 'image-first-frame',
            'reference-image', 'reference-images' => 'reference-images',
            default => 'text-only',
        };
    }

    private function sourceMode(string $mode): string
    {
        return match ($mode) {
            'campaign-assisted' => MotionAiVideoCapabilities::SOURCE_MODE_CAMPAIGN_ASSISTED,
            'image-first-frame' => MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_FIRST_FRAME,
            'reference-images' => MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_REFERENCE_EXPERIMENTAL,
            default => MotionAiVideoCapabilities::SOURCE_MODE_TEXT_PROMPT,
        };
    }

    private function providerRequestShape(string $mode, ?MediaAsset $asset, bool $imageToVideoEnabled, bool $imageReferencesEnabled): string
    {
        if ($mode === 'image-first-frame' && $imageToVideoEnabled && $asset instanceof MediaAsset) {
            return 'image_first_frame';
        }

        if ($mode === 'reference-images' && $imageReferencesEnabled && $asset instanceof MediaAsset) {
            return 'reference_images';
        }

        return 'text_only';
    }

    private function imageToVideoEnabled(): bool
    {
        return (bool) config('motion_ai_video.google_veo.image_to_video_enabled', false);
    }

    private function imageReferencesEnabled(): bool
    {
        return false;
    }

    private function campaignGeneration(): ?Generation
    {
        $id = $this->option('campaign-generation-id') ?: $this->option('generation-id');

        return is_numeric($id) ? Generation::query()->find((int) $id) : null;
    }

    private function campaignGenerationOrFail(): Generation
    {
        $generation = $this->campaignGeneration();

        if (! $generation instanceof Generation) {
            throw new MotionAiVideoProviderException('Campaign-assisted mode requires --campaign-generation-id.', 'google_veo_missing_campaign_generation');
        }

        if (! in_array($generation->job_type, $this->compatibleCampaignJobTypes(), true)
            || $generation->status !== Generation::STATUS_COMPLETED
            || ! is_array($generation->output_payload)
            || $generation->output_payload === []) {
            throw new MotionAiVideoProviderException('The selected generation is not a usable completed campaign source.', 'google_veo_invalid_campaign_generation');
        }

        if (is_numeric($this->option('project-id')) && (int) $this->option('project-id') !== (int) $generation->project_id) {
            throw new MotionAiVideoProviderException('The selected campaign generation does not belong to the requested project.', 'google_veo_invalid_campaign_generation_project');
        }

        return $generation;
    }

    private function asset(): ?MediaAsset
    {
        $id = $this->option('image-asset-id') ?: $this->option('asset-id');

        return is_numeric($id) ? MediaAsset::query()->find((int) $id) : null;
    }

    private function imageAssetOrFail(): MediaAsset
    {
        $asset = $this->asset();

        if (! $asset instanceof MediaAsset) {
            throw new MotionAiVideoProviderException('Image mode requires --image-asset-id.', 'google_veo_missing_image_asset');
        }

        if ($asset->type !== 'image' || $this->supportedImageMimeType($asset) === null) {
            throw new MotionAiVideoProviderException('The selected MediaAsset is not a supported image.', 'google_veo_unsupported_image_input', [
                'media_asset_id' => $asset->getKey(),
                'mime_type' => $asset->mime_type,
            ]);
        }

        if (is_numeric($this->option('project-id')) && $asset->project_id !== null && (int) $this->option('project-id') !== (int) $asset->project_id) {
            throw new MotionAiVideoProviderException('The selected image asset does not belong to the requested project.', 'google_veo_invalid_image_project', [
                'media_asset_id' => $asset->getKey(),
            ]);
        }

        $disk = Storage::disk($asset->disk);

        if (! $disk->exists($asset->path) || $disk->size($asset->path) <= 0) {
            throw new MotionAiVideoProviderException('The selected image asset is missing or empty on storage.', 'google_veo_source_asset_unavailable', [
                'media_asset_id' => $asset->getKey(),
                'mime_type' => $asset->mime_type,
            ]);
        }

        return $asset;
    }

    /** @return array{prompt: string, summary: array<string, mixed>} */
    private function promptForMode(string $mode, string $sourceMode, ?Generation $generation, ?MediaAsset $asset): array
    {
        $input = $this->promptInput($sourceMode, $generation, $asset);
        $plan = $this->planner->generate($input);

        return $this->promptBuilder->build($input, $plan, $asset, null);
    }

    /** @return array<string, mixed> */
    private function promptInput(string $sourceMode, ?Generation $generation, ?MediaAsset $asset): array
    {
        $project = $generation instanceof Generation && $generation->project_id !== null
            ? Project::query()->find($generation->project_id)
            : (is_numeric($this->option('project-id')) ? Project::query()->find((int) $this->option('project-id')) : null);
        $projectInput = $project instanceof Project && is_array($project->input_payload) ? $project->input_payload : [];
        $prompt = trim((string) $this->option('prompt'));

        return array_merge($projectInput, [
            'project_id' => $project?->getKey(),
            'project_title' => $project?->title ?? 'Nusa Motion AI Video test',
            'generation_type' => 'motion_ai_video',
            'source_mode' => $sourceMode,
            'product_image_asset_id' => $asset?->getKey(),
            'product_image_asset' => $asset instanceof MediaAsset ? $this->assetSummary($asset) : null,
            'campaign_generation_id' => $generation?->getKey(),
            'campaign_generation_summary' => $generation instanceof Generation ? $this->campaignSummary($generation) : null,
            'platform' => 'tiktok',
            'target_duration_seconds' => MotionAiVideoCapabilities::defaultDurationSeconds(),
            'visual_style' => 'realistic_product',
            'motion_style' => 'slow_push_in',
            'aspect_ratio' => 'vertical_9_16',
            'script_source' => $sourceMode === MotionAiVideoCapabilities::SOURCE_MODE_CAMPAIGN_ASSISTED ? 'campaign_script' : 'project_brief',
            'creative_notes' => $prompt !== '' ? $prompt : null,
            'manual_script' => $sourceMode === MotionAiVideoCapabilities::SOURCE_MODE_TEXT_PROMPT && $prompt !== '' ? $prompt : null,
            'scene_description' => 'Clean vertical social-commerce product showcase.',
            'background_prompt' => 'Clean creator-friendly setting with stable lighting.',
            'negative_prompt' => 'warped product, unreadable text, fake logos, exaggerated claims',
            'include_voiceover_plan' => true,
            'include_caption_plan' => true,
            'primary_cta' => 'See product details',
        ]);
    }

    /** @return array<string, mixed> */
    private function textOnlyPayload(string $prompt): array
    {
        return [
            'instances' => [[
                'prompt' => $prompt,
            ]],
            'parameters' => [
                'aspectRatio' => MotionAiVideoCapabilities::defaultAspectRatio(),
                'durationSeconds' => MotionAiVideoCapabilities::defaultDurationSeconds(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function payloadForShape(string $shape, string $prompt, ?MediaAsset $asset): array
    {
        return $this->textOnlyPayload($prompt);
    }

    /** @return array<string, mixed> */
    private function summary(
        string $mode,
        string $sourceMode,
        string $requestMode,
        string $providerRequestShape,
        bool $imageToVideoEnabled,
        bool $imageReferencesEnabled,
        ?Generation $campaignGeneration,
        ?MediaAsset $asset,
        array $prompt,
        array $payload,
        array $networkSummary,
    ): array {
        $instance = data_get($payload, 'instances.0', []);
        $imageInlineData = data_get($instance, 'image.inlineData');
        $referenceInlineData = data_get($instance, 'referenceImages.0.image.inlineData');
        $base64 = is_array($imageInlineData)
            ? ($imageInlineData['data'] ?? null)
            : (is_array($referenceInlineData) ? ($referenceInlineData['data'] ?? null) : null);

        return [
            'provider' => 'google_veo',
            'model' => (string) config('motion_ai_video.google_veo.model'),
            'cli_mode' => $mode,
            'source_mode' => $sourceMode,
            'request_mode' => $requestMode,
            'provider_request_shape' => $providerRequestShape,
            'image_to_video_enabled' => $imageToVideoEnabled ? 'true' : 'false',
            'reference_images_enabled' => $imageReferencesEnabled ? 'true' : 'false',
            'campaign_generation_id' => $campaignGeneration?->getKey() ?? 'none',
            'campaign_generation_type' => $campaignGeneration?->job_type ?? 'none',
            'campaign_compatible' => $campaignGeneration instanceof Generation && in_array($campaignGeneration->job_type, $this->compatibleCampaignJobTypes(), true) ? 'true' : 'n/a',
            'image_asset_id' => $asset?->getKey() ?? 'none',
            'image_mime_type' => $asset?->mime_type ?? 'none',
            'image_original_byte_size' => $asset?->size_bytes ?? 'none',
            'image_field_present' => is_array(data_get($instance, 'image')) ? 'true' : 'false',
            'reference_images_count' => is_array(data_get($instance, 'referenceImages')) ? count((array) data_get($instance, 'referenceImages')) : 0,
            'image_bytes_sent' => in_array($providerRequestShape, ['image_first_frame', 'reference_images'], true) ? 'true' : 'false',
            'base64_included' => is_string($base64) ? 'true' : 'false',
            'base64_length' => is_string($base64) ? strlen($base64) : 0,
            'request_payload_size_bytes' => strlen(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: ''),
            'final_prompt_character_count' => mb_strlen($prompt['prompt']),
            'prompt_hash' => substr(hash('sha256', $prompt['prompt']), 0, 16),
            'duration_type' => gettype(data_get($payload, 'parameters.durationSeconds')),
            'duration_seconds' => data_get($payload, 'parameters.durationSeconds'),
            'aspect_ratio' => data_get($payload, 'parameters.aspectRatio'),
            'endpoint_host' => $networkSummary['endpoint_host'] ?? 'unknown',
            'endpoint_path' => $networkSummary['endpoint_path'] ?? 'unknown',
            'connect_timeout_seconds' => $networkSummary['connect_timeout_seconds'] ?? 'unknown',
            'timeout_seconds' => $networkSummary['timeout_seconds'] ?? 'unknown',
            'retry_times' => $networkSummary['retry_times'] ?? 'unknown',
            'retry_sleep_ms' => $networkSummary['retry_sleep_ms'] ?? 'unknown',
            'force_ipv4' => ($networkSummary['force_ipv4'] ?? false) ? 'true' : 'false',
            'force_http1' => ($networkSummary['force_http1'] ?? false) ? 'true' : 'false',
        ];
    }

    /** @return array<string, mixed> */
    private function campaignSummary(Generation $generation): array
    {
        $output = is_array($generation->output_payload) ? $generation->output_payload : [];

        return array_filter([
            'job_type' => $generation->job_type,
            'campaign_title' => $this->firstString([
                data_get($output, 'campaign_title'),
                data_get($output, 'concept_title'),
                data_get($output, 'ad_summary.creative_angle'),
            ]),
            'campaign_angle' => $this->firstString([
                data_get($output, 'campaign_angle'),
                data_get($output, 'ad_summary.creative_angle'),
                data_get($output, 'creative_angle'),
            ]),
            'product_angle' => $this->firstString([
                data_get($output, 'product_angle'),
                data_get($output, 'core_selling_proposition'),
            ]),
            'caption' => $this->firstString([
                data_get($output, 'caption'),
                data_get($output, 'caption_variants.short_caption'),
            ]),
            'cta' => $this->firstString([
                data_get($output, 'cta'),
                data_get($output, 'ad_script.CTA'),
                data_get($output, 'script.CTA'),
            ]),
            'hooks' => $this->stringList(data_get($output, 'hooks'), ['hook', 'text']),
            'benefits' => $this->stringList(data_get($output, 'benefits') ?? data_get($output, 'ad_script.product_benefit_sequence'), ['benefit', 'text']),
            'script' => array_filter([
                'voiceover_script' => $this->firstString([
                    data_get($output, 'voiceover_ready_script'),
                    data_get($output, 'main_script.voiceover_script'),
                    data_get($output, 'script.voiceover_script'),
                    data_get($output, 'ad_script.voiceover_ready_script'),
                ]),
            ]),
        ], fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    /** @param array<int, string> $keys @return array<int, string> */
    private function stringList(mixed $value, array $keys): array
    {
        if (! is_array($value)) {
            $string = $this->stringValue($value);

            return $string === null ? [] : [$string];
        }

        return collect($value)
            ->map(function (mixed $item) use ($keys): ?string {
                if (! is_array($item)) {
                    return $this->stringValue($item);
                }

                foreach ($keys as $key) {
                    $string = $this->stringValue($item[$key] ?? null);

                    if ($string !== null) {
                        return $string;
                    }
                }

                return $this->firstString(array_values($item));
            })
            ->filter()
            ->take(8)
            ->values()
            ->all();
    }

    /** @param array<int, mixed> $values */
    private function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            $string = $this->stringValue($value);

            if ($string !== null) {
                return $string;
            }
        }

        return null;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, 700, '');
    }

    /** @return array<int, string> */
    private function compatibleCampaignJobTypes(): array
    {
        return [
            'generate_affiliate_campaign',
            'generate_video_to_campaign',
            'generate_product_video_script',
            'generate_auto_video_ad',
        ];
    }

    /** @return array<string, mixed> */
    private function assetSummary(MediaAsset $asset): array
    {
        return [
            'id' => $asset->getKey(),
            'type' => $asset->type,
            'usage_role' => $asset->usage_role,
            'original_name' => $asset->original_name,
            'mime_type' => $asset->mime_type,
            'size_bytes' => $asset->size_bytes,
        ];
    }

    private function supportedImageMimeType(MediaAsset $asset): ?string
    {
        $mimeType = strtolower(trim((string) $asset->mime_type));
        $mimeType = $mimeType === 'image/jpg' ? 'image/jpeg' : $mimeType;

        return in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true) ? $mimeType : null;
    }

    private function storeDebugOutput(string $binary, ?Generation $generation): string
    {
        $user = is_numeric($this->option('user-id')) ? User::query()->find((int) $this->option('user-id')) : null;
        $project = is_numeric($this->option('project-id')) ? Project::query()->find((int) $this->option('project-id')) : null;

        if ($user instanceof User && $project instanceof Project && $generation instanceof Generation) {
            $asset = $this->videos->store(
                user: $user,
                project: $project,
                generationId: $generation->getKey(),
                videoBinary: $binary,
                metadata: [
                    'provider' => 'google_veo',
                    'workflow_key' => 'motion_ai_video',
                    'mode' => 'cli_test',
                ],
                workflow: 'motion-ai-video',
                usageRole: 'edited_video_result',
                originalNamePrefix: 'motion-ai-video-cli-test',
            );

            return 'private MediaAsset #'.$asset->getKey();
        }

        $relativePath = 'nusa-debug/veo-motion-'.now()->format('Ymd-His').'.mp4';
        Storage::disk('local')->put($relativePath, $binary);

        return 'local storage: '.$relativePath;
    }
}
