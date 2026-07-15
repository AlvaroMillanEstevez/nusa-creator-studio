<?php

namespace App\Services\Generation;

use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class GeneratedEditedVideoMediaService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function store(
        User $user,
        Project $project,
        int $generationId,
        string $videoBinary,
        array $metadata,
        string $workflow = 'auto-video-editor',
        string $usageRole = 'edited_video_result',
        string $originalNamePrefix = 'auto-video-editor',
    ): MediaAsset {
        if ($videoBinary === '') {
            throw new RuntimeException('Generated video content is empty.');
        }

        $disk = (string) config('media.disk', 'local');

        if ($disk === '' || $disk === 'public') {
            throw new RuntimeException('Generated media storage must use a configured private disk.');
        }

        $directory = sprintf(
            'users/%d/generated/%s/%s/%s',
            $user->getKey(),
            $workflow,
            now()->format('Y'),
            now()->format('m'),
        );
        $filename = sprintf(
            '%s-%d-%s.mp4',
            $originalNamePrefix,
            $generationId,
            Str::uuid(),
        );
        $path = "{$directory}/{$filename}";

        $stored = Storage::disk($disk)->put($path, $videoBinary, [
            'visibility' => 'private',
        ]);

        if ($stored === false) {
            throw new RuntimeException('Generated video could not be stored.');
        }

        return $user->mediaAssets()->create([
            'project_id' => $project->getKey(),
            'type' => 'video',
            'usage_role' => $usageRole,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $originalNamePrefix.'-'.$generationId.'.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => strlen($videoBinary),
            'metadata' => array_filter(array_merge([
                'generated' => true,
                'workflow' => $workflow,
                'provider' => 'shotstack',
                'generation_id' => $generationId,
                'provider_url_was_downloaded' => true,
            ], $metadata), fn (mixed $value): bool => $value !== null),
        ]);
    }
}
