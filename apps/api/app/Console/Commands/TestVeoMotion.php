<?php

namespace App\Console\Commands;

use App\Exceptions\MotionAiVideo\MotionAiVideoProviderException;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\Generation\GeneratedEditedVideoMediaService;
use App\Services\Generation\GoogleVeoMotionAiVideoClient;
use App\Support\MotionAiVideoCapabilities;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class TestVeoMotion extends Command
{
    protected $signature = 'nusa:test-veo-motion
        {--mode=text-only : text-only, campaign, or reference-image}
        {--prompt= : Prompt for text-only/reference-image tests}
        {--generation-id= : Completed campaign generation ID for campaign mode or optional output context}
        {--asset-id= : Product image MediaAsset ID for reference-image mode}
        {--project-id= : Optional project context for saving output as MediaAsset}
        {--user-id= : Optional user context for saving output as MediaAsset}
        {--yes : Skip confirmation before making a real provider call}
        {--dry-run : Only print a safe request summary}';

    protected $description = 'Safely dry-run or test the Google Veo Motion AI Video request contract locally';

    public function __construct(
        private readonly GoogleVeoMotionAiVideoClient $client,
        private readonly GeneratedEditedVideoMediaService $videos,
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
        $requestMode = MotionAiVideoCapabilities::normalizeRequestMode();
        $imageReferencesEnabled = $requestMode === MotionAiVideoCapabilities::REQUEST_MODE_REFERENCE_IMAGES_EXPERIMENTAL
            && (bool) config('motion_ai_video.google_veo.image_references_enabled', false)
            && (bool) config('motion_ai_video.google_veo.allow_experimental_image_input', false);
        $asset = $this->asset();
        $generation = $this->campaignGeneration();
        $prompt = $this->prompt($mode, $generation, $asset);
        $providerRequestShape = $mode === 'reference-image' && $imageReferencesEnabled ? 'reference_images' : 'text_only';

        if ($mode === 'reference-image' && ! $imageReferencesEnabled) {
            $this->warn('Reference-image mode is not enabled. The safe request shape will remain text_only.');
        }

        $networkSummary = $this->client->safeNetworkSummary('submit');
        $summary = [
            'provider' => 'google_veo',
            'model' => (string) config('motion_ai_video.google_veo.model'),
            'cli_mode' => $mode,
            'request_mode' => $requestMode,
            'provider_request_shape' => $providerRequestShape,
            'image_references_enabled' => $imageReferencesEnabled ? 'true' : 'false',
            'has_prompt' => $prompt !== '' ? 'true' : 'false',
            'campaign_generation_id' => $generation?->getKey() ?? 'none',
            'product_image_asset_id' => $asset?->getKey() ?? 'none',
            'product_image_sent_to_provider' => $providerRequestShape === 'reference_images' && $asset instanceof MediaAsset ? 'true' : 'false',
            'duration_seconds' => MotionAiVideoCapabilities::defaultDurationSeconds(),
            'aspect_ratio' => MotionAiVideoCapabilities::defaultAspectRatio(),
            'endpoint_host' => $networkSummary['endpoint_host'] ?? 'unknown',
            'endpoint_path' => $networkSummary['endpoint_path'] ?? 'unknown',
            'connect_timeout_seconds' => $networkSummary['connect_timeout_seconds'] ?? 'unknown',
            'timeout_seconds' => $networkSummary['timeout_seconds'] ?? 'unknown',
            'retry_times' => $networkSummary['retry_times'] ?? 'unknown',
            'retry_sleep_ms' => $networkSummary['retry_sleep_ms'] ?? 'unknown',
            'force_ipv4' => ($networkSummary['force_ipv4'] ?? false) ? 'true' : 'false',
            'force_http1' => ($networkSummary['force_http1'] ?? false) ? 'true' : 'false',
        ];

        $this->info('Safe Google Veo request summary:');
        $this->table(['key', 'value'], collect($summary)->map(fn (mixed $value, string $key): array => [$key, (string) $value])->all());

        if ($this->option('dry-run')) {
            $this->comment('Dry run only. No provider call was made.');

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm('Make a real Google Veo provider call now?', false)) {
            $this->comment('Cancelled before provider call.');

            return self::SUCCESS;
        }

        $payload = $providerRequestShape === 'reference_images'
            ? $this->referenceImagesPayload($prompt, $asset)
            : $this->textOnlyPayload($prompt);
        $context = [
            'provider' => 'google_veo',
            'workflow' => 'motion_ai_video',
            'stage' => 'cli_test',
            'request_mode' => $requestMode,
            'provider_request_shape' => $providerRequestShape,
            'has_product_image' => $asset instanceof MediaAsset,
            'product_image_asset_id' => $asset?->getKey(),
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

        $stored = $this->storeDebugOutput($binary, $generation);
        $this->info('Google Veo test completed.');
        $this->line('Stored output: '.$stored);

        return self::SUCCESS;
    }

    private function mode(): string
    {
        $mode = Str::of((string) $this->option('mode'))->lower()->replace('_', '-')->toString();

        return in_array($mode, ['text-only', 'campaign', 'reference-image'], true) ? $mode : 'text-only';
    }

    private function campaignGeneration(): ?Generation
    {
        $id = $this->option('generation-id');

        return is_numeric($id) ? Generation::query()->find((int) $id) : null;
    }

    private function asset(): ?MediaAsset
    {
        $id = $this->option('asset-id');

        return is_numeric($id) ? MediaAsset::query()->find((int) $id) : null;
    }

    private function prompt(string $mode, ?Generation $generation, ?MediaAsset $asset): string
    {
        $prompt = trim((string) $this->option('prompt'));

        if ($prompt !== '') {
            return $prompt;
        }

        if ($mode === 'campaign' && $generation instanceof Generation) {
            return Str::limit(json_encode($generation->output_payload ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 2200, '');
        }

        if ($mode === 'reference-image' && $asset instanceof MediaAsset) {
            return 'Create a clean social commerce product showcase video using the selected product asset as the product context.';
        }

        return 'Create a clean social commerce product showcase video with a clear reveal, useful close-up, gentle camera movement, and stable hero frame. Keep all claims honest and generic.';
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
    private function referenceImagesPayload(string $prompt, ?MediaAsset $asset): array
    {
        if (! $asset instanceof MediaAsset) {
            throw new MotionAiVideoProviderException('Reference-image mode requires --asset-id.', 'google_veo_missing_reference_asset');
        }

        $binary = Storage::disk($asset->disk)->get($asset->path);

        return [
            'instances' => [[
                'prompt' => $prompt,
                'referenceImages' => [[
                    'image' => [
                        'inlineData' => [
                            'mimeType' => $asset->mime_type ?: 'image/jpeg',
                            'data' => base64_encode($binary),
                        ],
                    ],
                    'referenceType' => 'asset',
                ]],
            ]],
            'parameters' => [
                'aspectRatio' => MotionAiVideoCapabilities::defaultAspectRatio(),
                'durationSeconds' => MotionAiVideoCapabilities::defaultDurationSeconds(),
            ],
        ];
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
