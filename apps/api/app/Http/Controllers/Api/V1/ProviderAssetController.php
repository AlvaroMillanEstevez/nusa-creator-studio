<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\Media\TemporaryProviderAssetUrlService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProviderAssetController extends Controller
{
    public function __construct(private readonly TemporaryProviderAssetUrlService $providerAssets) {}

    public function __invoke(string $token): StreamedResponse
    {
        $payload = $this->providerAssets->resolve($token, 'shotstack');

        abort_if($payload === null, Response::HTTP_NOT_FOUND);

        $asset = MediaAsset::query()->find($payload['media_asset_id'] ?? null);

        abort_unless($asset instanceof MediaAsset, Response::HTTP_NOT_FOUND);
        abort_unless(in_array($asset->type, ['video', 'audio'], true), Response::HTTP_NOT_FOUND);
        abort_unless(Storage::disk($asset->disk)->exists($asset->path), Response::HTTP_NOT_FOUND);

        Log::info('Provider asset URL accessed.', [
            'provider' => 'shotstack',
            'media_asset_id' => $asset->getKey(),
            'project_id' => $asset->project_id,
            'asset_type' => $asset->type,
            'purpose' => $payload['purpose'] ?? null,
        ]);

        $headers = [
            'Content-Type' => $asset->mime_type ?: $this->fallbackMimeType($asset),
            'Content-Disposition' => sprintf(
                'inline; filename="%s"',
                addcslashes($this->safeFilename($asset), '\\"'),
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, no-store',
        ];

        if ($asset->size_bytes !== null) {
            $headers['Content-Length'] = (string) $asset->size_bytes;
        }

        return response()->stream(function () use ($asset): void {
            $stream = Storage::disk($asset->disk)->readStream($asset->path);

            if ($stream === false) {
                return;
            }

            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, Response::HTTP_OK, $headers);
    }

    private function fallbackMimeType(MediaAsset $asset): string
    {
        return match ($asset->type) {
            'video' => 'video/mp4',
            'audio' => 'audio/mpeg',
            default => 'application/octet-stream',
        };
    }

    private function safeFilename(MediaAsset $asset): string
    {
        $fallback = 'provider-asset-'.$asset->getKey().($asset->type === 'video' ? '.mp4' : '.mp3');
        $name = $asset->original_name ?: $fallback;
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\r\n\x00-\x1F\x7F"]+/u', '_', $name) ?: $fallback;
        $name = trim($name, ". \t\n\r\0\x0B");

        return $name === '' ? $fallback : mb_substr($name, 0, 180);
    }
}
