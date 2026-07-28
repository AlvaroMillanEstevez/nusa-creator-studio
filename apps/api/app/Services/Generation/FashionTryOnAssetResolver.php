<?php

namespace App\Services\Generation;

use App\Exceptions\FashionTryOn\FashionTryOnProviderException;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Support\FashionTryOnCapabilities;
use Illuminate\Support\Facades\Storage;

class FashionTryOnAssetResolver
{
    public function resolveProjectImage(User $user, Project $project, int $assetId, string $field): MediaAsset
    {
        $asset = $user->mediaAssets()
            ->whereKey($assetId)
            ->where('project_id', $project->getKey())
            ->where('type', 'image')
            ->first();

        if (! $asset instanceof MediaAsset) {
            throw FashionTryOnProviderException::invalidInput('fashn_input_validation', [
                'provider' => 'fashn',
                'stage' => 'resolve_'.$field,
                'media_asset_id' => $assetId,
            ]);
        }

        $this->assertUsableImage($asset, $field);

        return $asset;
    }

    public function assertUsableImage(MediaAsset $asset, string $field): void
    {
        if ($asset->type !== 'image' || ! in_array($asset->mime_type, FashionTryOnCapabilities::SUPPORTED_IMAGE_MIME_TYPES, true)) {
            throw FashionTryOnProviderException::invalidInput('fashn_input_validation', [
                'provider' => 'fashn',
                'stage' => 'validate_'.$field,
                'media_asset_id' => $asset->getKey(),
                'mime_type' => $asset->mime_type,
            ]);
        }

        $disk = Storage::disk($asset->disk);

        if (! $disk->exists($asset->path)) {
            throw FashionTryOnProviderException::invalidInput('fashn_image_load_failed', [
                'provider' => 'fashn',
                'stage' => 'read_'.$field,
                'media_asset_id' => $asset->getKey(),
            ]);
        }

        $size = $disk->size($asset->path);
        $maxBytes = (int) config('media.max_size_kb', 51200) * 1024;

        if ($size <= 0 || $size > $maxBytes) {
            throw FashionTryOnProviderException::invalidInput('fashn_image_load_failed', [
                'provider' => 'fashn',
                'stage' => 'validate_size_'.$field,
                'media_asset_id' => $asset->getKey(),
                'size_bytes' => $size,
            ]);
        }

        $this->assertStoredMimeMatches($asset, $field);
    }

    public function dataUri(MediaAsset $asset, string $field): string
    {
        $this->assertUsableImage($asset, $field);
        $contents = Storage::disk($asset->disk)->get($asset->path);

        if (! is_string($contents) || $contents === '') {
            throw FashionTryOnProviderException::invalidInput('fashn_image_load_failed', [
                'provider' => 'fashn',
                'stage' => 'read_'.$field,
                'media_asset_id' => $asset->getKey(),
            ]);
        }

        return 'data:'.$asset->mime_type.';base64,'.base64_encode($contents);
    }

    private function assertStoredMimeMatches(MediaAsset $asset, string $field): void
    {
        if (! function_exists('finfo_buffer')) {
            return;
        }

        $contents = Storage::disk($asset->disk)->get($asset->path);

        if (! is_string($contents) || $contents === '') {
            throw FashionTryOnProviderException::invalidInput('fashn_image_load_failed', [
                'provider' => 'fashn',
                'stage' => 'read_'.$field,
                'media_asset_id' => $asset->getKey(),
            ]);
        }

        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);

        if (! in_array($detectedMime, FashionTryOnCapabilities::SUPPORTED_IMAGE_MIME_TYPES, true)) {
            throw FashionTryOnProviderException::invalidInput('fashn_input_validation', [
                'provider' => 'fashn',
                'stage' => 'detect_mime_'.$field,
                'media_asset_id' => $asset->getKey(),
                'detected_mime_type' => $detectedMime,
            ]);
        }
    }
}
