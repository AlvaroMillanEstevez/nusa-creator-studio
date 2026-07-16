<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use App\Services\Generation\GoogleVeoSdkProbeRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TestVeoMotionSdk extends Command
{
    protected $signature = 'nusa:test-veo-motion-sdk
        {--image-asset-id= : Image MediaAsset ID to use as the first frame}
        {--project-id= : Optional project scope for safer asset resolution}
        {--prompt= : Manual visual prompt for the SDK probe}
        {--yes : Skip confirmation before making a real provider call}
        {--dry-run : Print a safe summary without invoking Node or Google}';

    protected $description = 'CLI-only diagnostic probe for Google Veo image-first-frame requests through the official Google GenAI SDK';

    public function __construct(private readonly GoogleVeoSdkProbeRunner $runner)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This development helper is only available in local and testing environments.');

            return self::FAILURE;
        }

        try {
            $asset = $this->imageAssetOrFail();
            $binary = $this->imageBinaryOrFail($asset);
            $prompt = $this->promptOrFail();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $summary = $this->summary($asset, $binary, $prompt);
        $this->info('Safe Google Veo SDK probe summary:');
        $this->table(['key', 'value'], collect($summary)->map(fn (mixed $value, string $key): array => [$key, (string) $value])->all());

        if ($this->option('dry-run')) {
            $this->comment('Dry run only. Node was not invoked and no Google provider call was made.');

            return self::SUCCESS;
        }

        if (! (string) config('motion_ai_video.google_veo.api_key')) {
            $this->error('GOOGLE_VEO_API_KEY is not configured.');

            return self::FAILURE;
        }

        if (! $this->option('yes') && ! $this->confirm('Make a real Google Veo SDK provider call now?', false)) {
            $this->comment('Cancelled before provider call.');

            return self::SUCCESS;
        }

        $tempDirectory = $this->makeTempDirectory();
        $tempFiles = [];

        try {
            $imageFile = $tempDirectory.DIRECTORY_SEPARATOR.'input'.$this->extensionFor($asset);
            $promptFile = $tempDirectory.DIRECTORY_SEPARATOR.'prompt.txt';
            $outputFile = $tempDirectory.DIRECTORY_SEPARATOR.'output.mp4';
            $tempFiles = [$imageFile, $promptFile, $outputFile];

            file_put_contents($imageFile, $binary);
            file_put_contents($promptFile, $prompt);

            $result = $this->runner->run(
                imageFile: $imageFile,
                promptFile: $promptFile,
                outputFile: $outputFile,
                mimeType: $this->supportedImageMimeType($asset),
                model: (string) config('motion_ai_video.google_veo.model'),
                durationSeconds: (int) config('motion_ai_video.google_veo.default_duration_seconds', 8),
                aspectRatio: (string) config('motion_ai_video.google_veo.default_aspect_ratio', '9:16'),
            );

            if (($result['ok'] ?? false) !== true) {
                $this->error('Google Veo SDK probe failed safely: '.($result['errorCategory'] ?? 'provider_error'));
                $this->line('Safe message: '.($result['safeMessage'] ?? 'No safe provider message returned.'));

                return self::FAILURE;
            }

            if (! is_file($outputFile) || filesize($outputFile) <= 0) {
                $this->error('Google Veo SDK probe reported success but no output MP4 was created.');

                return self::FAILURE;
            }

            $debugPath = 'nusa-debug/veo-sdk-motion-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(8)).'.mp4';
            Storage::disk('local')->put($debugPath, (string) file_get_contents($outputFile));
            $this->info('Google Veo SDK probe completed.');
            $this->line('Stored output: local storage: '.$debugPath);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Google Veo SDK probe failed safely: '.$this->safeExceptionMessage($exception));

            return self::FAILURE;
        } finally {
            foreach ($tempFiles as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            if (is_dir($tempDirectory)) {
                @rmdir($tempDirectory);
            }
        }
    }

    private function imageAssetOrFail(): MediaAsset
    {
        $id = $this->option('image-asset-id');

        if (! is_numeric($id)) {
            throw new RuntimeException('The --image-asset-id option is required.');
        }

        $asset = MediaAsset::query()->find((int) $id);

        if (! $asset instanceof MediaAsset) {
            throw new RuntimeException('The selected MediaAsset does not exist.');
        }

        if ($asset->type !== 'image' || $this->supportedImageMimeType($asset) === '') {
            throw new RuntimeException('The selected MediaAsset is not a supported image.');
        }

        if (is_numeric($this->option('project-id')) && $asset->project_id !== null && (int) $this->option('project-id') !== (int) $asset->project_id) {
            throw new RuntimeException('The selected image asset does not belong to the requested project.');
        }

        return $asset;
    }

    private function imageBinaryOrFail(MediaAsset $asset): string
    {
        $disk = Storage::disk($asset->disk);

        if (! $disk->exists($asset->path)) {
            throw new RuntimeException('The selected image asset is missing from storage.');
        }

        $binary = $disk->get($asset->path);

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('The selected image asset is empty on storage.');
        }

        return $binary;
    }

    private function promptOrFail(): string
    {
        $prompt = trim((string) $this->option('prompt'));

        if ($prompt === '') {
            throw new RuntimeException('The --prompt option is required.');
        }

        return $prompt;
    }

    /**
     * @return array<string, string|int>
     */
    private function summary(MediaAsset $asset, string $binary, string $prompt): array
    {
        $model = (string) config('motion_ai_video.google_veo.model');
        $duration = (int) config('motion_ai_video.google_veo.default_duration_seconds', 8);
        $aspectRatio = (string) config('motion_ai_video.google_veo.default_aspect_ratio', '9:16');
        $apiKeyConfigured = (string) config('motion_ai_video.google_veo.api_key') !== '';

        return [
            'provider' => 'google_veo',
            'transport' => 'google_genai_sdk',
            'sdk_package_version' => $this->runner->sdkPackageVersion(),
            'model' => $model,
            'media_asset_id' => $asset->getKey(),
            'mime_type' => $this->supportedImageMimeType($asset),
            'byte_size' => strlen($binary),
            'imageBytes_included' => 'true',
            'prompt_character_count' => mb_strlen($prompt),
            'prompt_hash' => hash('sha256', $prompt),
            'duration_seconds' => $duration,
            'aspect_ratio' => $aspectRatio,
            'temporary_file_strategy' => 'private temp files; paths are not printed; deleted in finally',
            'real_call_allowed' => $apiKeyConfigured && (bool) $this->option('yes') ? 'true' : 'false',
        ];
    }

    private function supportedImageMimeType(MediaAsset $asset): string
    {
        $mimeType = strtolower(trim((string) $asset->mime_type));
        $mimeType = $mimeType === 'image/jpg' ? 'image/jpeg' : $mimeType;

        return in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true) ? $mimeType : '';
    }

    private function extensionFor(MediaAsset $asset): string
    {
        return match ($this->supportedImageMimeType($asset)) {
            'image/png' => '.png',
            'image/webp' => '.webp',
            default => '.jpg',
        };
    }

    private function makeTempDirectory(): string
    {
        $base = storage_path('app/nusa-debug/tmp');

        if (! is_dir($base)) {
            mkdir($base, 0700, true);
        }

        $directory = $base.DIRECTORY_SEPARATOR.'veo-sdk-'.Str::uuid();
        mkdir($directory, 0700, true);

        return $directory;
    }

    private function safeExceptionMessage(Throwable $exception): string
    {
        return Str::limit(preg_replace([
            '/https?:\/\/\S+/i',
            '/[A-Za-z0-9+\/]{80,}={0,2}/',
            '/AIza[\w-]+/',
        ], [
            '[redacted-url]',
            '[redacted-long-token]',
            '[redacted-api-key]',
        ], $exception->getMessage()) ?? 'SDK probe failed safely.', 500, '');
    }
}
