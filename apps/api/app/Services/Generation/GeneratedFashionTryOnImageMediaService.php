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
    public function storeOrReuse(
        User $user,
        Project $project,
        int $generationId,
        int $outputIndex,
        string $imageBinary,
        string $mimeType,
        array $metadata,
    ): MediaAsset {
        $existing = $this->existingOutputAsset($user, $project, $generationId, $outputIndex);

        if ($existing instanceof MediaAsset) {
            return $existing;
        }

        return $this->store(
            user: $user,
            project: $project,
            generationId: $generationId,
            imageBinary: $imageBinary,
            mimeType: $mimeType,
            metadata: array_merge($metadata, ['output_index' => $outputIndex]),
        );
    }

    /**
     * @param  iterable<MediaAsset>  $assets
     * @param  array<string, mixed>  $metadata
     */
    public function mergeMetadata(iterable $assets, array $metadata): void
    {
        foreach ($assets as $asset) {
            $asset->forceFill([
                'metadata' => array_filter(array_merge($asset->metadata ?? [], $metadata), fn (mixed $value): bool => $value !== null),
            ])->save();
        }
    }

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
        $outputIndex = is_numeric($metadata['output_index'] ?? null) ? (int) $metadata['output_index'] : 0;
        $filename = sprintf('fashion-try-on-%d-%d-%s.%s', $generationId, $outputIndex, Str::uuid(), $extension);
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
            'original_name' => 'fashion-try-on-'.$generationId.'-'.$outputIndex.'.'.$extension,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($imageBinary),
            'metadata' => array_filter(array_merge([
                'generated' => true,
                'workflow' => 'fashion_try_on',
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

    private function existingOutputAsset(User $user, Project $project, int $generationId, int $outputIndex): ?MediaAsset
    {
        return MediaAsset::query()
            ->where('user_id', $user->getKey())
            ->where('project_id', $project->getKey())
            ->where('type', 'image')
            ->where('usage_role', 'fashion_try_on_result')
            ->get()
            ->first(function (MediaAsset $asset) use ($generationId, $outputIndex): bool {
                $metadata = is_array($asset->metadata) ? $asset->metadata : [];

                return (int) ($metadata['generation_id'] ?? 0) === $generationId
                    && (int) ($metadata['output_index'] ?? -1) === $outputIndex
                    && ($metadata['workflow'] ?? null) === 'fashion_try_on';
            });
    }
}
