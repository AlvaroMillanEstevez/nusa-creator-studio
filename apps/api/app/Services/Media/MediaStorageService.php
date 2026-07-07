<?php

namespace App\Services\Media;

use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MediaStorageService
{
    public function __construct(private readonly MediaFileInspector $inspector) {}

    public function store(
        User $user,
        UploadedFile $file,
        ?Project $project = null,
        ?string $usageRole = null,
    ): MediaAsset {
        $inspection = $this->inspector->inspect($file);
        $disk = (string) config('media.disk', 'local');

        if ($disk === '' || $disk === 'public') {
            throw new RuntimeException('Media storage must use a configured private disk.');
        }

        $directory = sprintf(
            'users/%d/media/%s/%s',
            $user->getKey(),
            now()->format('Y'),
            now()->format('m'),
        );
        $filename = Str::uuid().'.'.$inspection->safeExtension;
        $path = "{$directory}/{$filename}";

        $storedPath = Storage::disk($disk)->putFileAs(
            $directory,
            $file,
            $filename,
            ['visibility' => 'private'],
        );

        if ($storedPath === false) {
            throw new RuntimeException('The media file could not be stored.');
        }

        try {
            return $user->mediaAssets()->create([
                'project_id' => $project?->getKey(),
                'type' => $inspection->type,
                'usage_role' => $usageRole,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $inspection->sanitizedOriginalName,
                'mime_type' => $inspection->mimeType,
                'size_bytes' => $inspection->sizeBytes,
                'metadata' => [
                    'detected_extension' => $inspection->safeExtension,
                    'uploaded_extension' => $inspection->uploadedExtension,
                    'validation_source' => 'server',
                ],
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    public function delete(MediaAsset $asset): void
    {
        Storage::disk($asset->disk)->delete($asset->path);
        $asset->delete();
    }
}
