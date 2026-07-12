<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaAssetDeliveryController extends Controller
{
    public function stream(Request $request, MediaAsset $mediaAsset): StreamedResponse
    {
        $this->authorizeOwner($request, $mediaAsset);
        $this->ensurePreviewableAsset($mediaAsset, 'Only image, audio or video assets can be streamed.');
        $this->ensureFileExists($mediaAsset);

        return $this->streamFile($mediaAsset, 'inline');
    }

    public function download(Request $request, MediaAsset $mediaAsset): StreamedResponse
    {
        $this->authorizeOwner($request, $mediaAsset);
        $this->ensurePreviewableAsset($mediaAsset, 'Only image, audio or video assets can be downloaded.');
        $this->ensureFileExists($mediaAsset);

        return $this->streamFile($mediaAsset, 'attachment');
    }

    private function authorizeOwner(Request $request, MediaAsset $mediaAsset): void
    {
        abort_unless(
            $mediaAsset->user_id === $request->user()->getKey(),
            Response::HTTP_NOT_FOUND,
        );
    }

    private function ensurePreviewableAsset(MediaAsset $mediaAsset, string $message): void
    {
        abort_if(
            ! in_array($mediaAsset->type, ['image', 'audio', 'video'], true),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $message,
        );
    }

    private function ensureFileExists(MediaAsset $mediaAsset): void
    {
        abort_unless(
            Storage::disk($mediaAsset->disk)->exists($mediaAsset->path),
            Response::HTTP_NOT_FOUND,
        );
    }

    private function streamFile(MediaAsset $mediaAsset, string $disposition): StreamedResponse
    {
        $headers = [
            'Content-Type' => $mediaAsset->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => sprintf(
                '%s; filename="%s"',
                $disposition,
                addcslashes($this->downloadFilename($mediaAsset), '\\"'),
            ),
            'Accept-Ranges' => 'bytes',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($mediaAsset->size_bytes !== null) {
            $headers['Content-Length'] = (string) $mediaAsset->size_bytes;
        }

        return response()->stream(function () use ($mediaAsset): void {
            $stream = Storage::disk($mediaAsset->disk)->readStream($mediaAsset->path);

            if ($stream === false) {
                return;
            }

            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, Response::HTTP_OK, $headers);
    }

    private function downloadFilename(MediaAsset $mediaAsset): string
    {
        $fallback = match ($mediaAsset->type) {
            'image' => 'media-'.$mediaAsset->getKey().'.png',
            'video' => 'media-'.$mediaAsset->getKey().'.mp4',
            default => 'media-'.$mediaAsset->getKey().'.mp3',
        };
        $name = $mediaAsset->original_name ?: $fallback;
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\r\n\x00-\x1F\x7F"]+/u', '_', $name) ?: $fallback;
        $name = trim($name, ". \t\n\r\0\x0B");

        if ($name === '') {
            return $fallback;
        }

        if ($mediaAsset->mime_type === 'video/mp4' && ! str_ends_with(mb_strtolower($name), '.mp4')) {
            $name .= '.mp4';
        }

        if ($mediaAsset->mime_type === 'image/png' && ! str_ends_with(mb_strtolower($name), '.png')) {
            $name .= '.png';
        }

        if (in_array($mediaAsset->mime_type, ['image/jpeg', 'image/jpg'], true) && ! preg_match('/\.jpe?g$/i', $name)) {
            $name .= '.jpg';
        }

        return mb_substr($name, 0, 180);
    }
}
