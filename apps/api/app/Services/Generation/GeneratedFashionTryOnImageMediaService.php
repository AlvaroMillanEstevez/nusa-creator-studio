<?php

namespace App\Services\Generation;

use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class GeneratedFashionTryOnImageMediaService
{
    private const EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function store(
        User $user,
        Project $project,
        int $generationId,
        string $imageBinary,
        string $mimeType,
        array $metadata,
    ): MediaAsset {
        $this->assertValidImage($imageBinary, $mimeType);

        $disk = (string) config('media.disk', 'local');

        if ($disk === '' || $disk === 'public') {
            throw new RuntimeException('Generated media storage must use a configured private disk.');
        }

        $extension = self::EXTENSIONS[$mimeType];
        $directory = sprintf(
            'users/%d/generated/fashion-try-on/%s/%s',
            $user->getKey(),
            now()->format('Y'),
            now()->format('m'),
        );
        $filename = sprintf('fashion-try-on-%d-%s.%s', $generationId, Str::uuid(), $extension);
        $path = "{$directory}/{$filename}";

        $stored = Storage::disk($disk)->put($path, $imageBinary, [
            'visibility' => 'private',
        ]);

        if ($stored === false) {
            throw new RuntimeException('Generated fashion try-on image could not be stored.');
        }

        return $user->mediaAssets()->create([
            'project_id' => $project->getKey(),
            'type' => 'image',
            'usage_role' => 'fashion_try_on_result',
            'disk' => $disk,
            'path' => $path,
            'original_name' => 'fashion-try-on-'.$generationId.'.'.$extension,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($imageBinary),
            'metadata' => array_filter(array_merge([
                'generated' => true,
                'workflow' => 'fashion-try-on',
                'workflow_key' => 'fashion_try_on',
                'provider' => 'fashn',
                'generation_id' => $generationId,
                'provider_url_was_downloaded' => false,
            ], $metadata), fn (mixed $value): bool => $value !== null),
        ]);
    }

    public function assertValidImage(string $binary, string $mimeType): void
    {
        if ($binary === '') {
            throw new RuntimeException('Generated image content is empty.');
        }

        if (! isset(self::EXTENSIONS[$mimeType])) {
            throw new RuntimeException('Generated image MIME type is not supported.');
        }

        $prefix = mb_substr(ltrim($binary), 0, 120);

        if (str_starts_with(strtolower($prefix), '<!doctype') || str_starts_with(strtolower($prefix), '<html')) {
            throw new RuntimeException('Generated image response was not an image.');
        }

        $detectedMime = function_exists('finfo_buffer')
            ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary)
            : null;

        if (is_string($detectedMime) && isset(self::EXTENSIONS[$detectedMime]) && $detectedMime !== $mimeType) {
            throw new RuntimeException('Generated image MIME type did not match its content.');
        }
    }
}
