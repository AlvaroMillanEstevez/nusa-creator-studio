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
        $this->ensureAudioAsset($mediaAsset, 'Only audio assets can be streamed.');
        $this->ensureFileExists($mediaAsset);

        return $this->streamFile($mediaAsset, 'inline');
    }

    public function download(Request $request, MediaAsset $mediaAsset): StreamedResponse
    {
        $this->authorizeOwner($request, $mediaAsset);
        $this->ensureAudioAsset($mediaAsset, 'Only audio assets can be downloaded right now.');
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

    private function ensureAudioAsset(MediaAsset $mediaAsset, string $message): void
    {
        abort_if(
            $mediaAsset->type !== 'audio',
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
        $fallback = 'media-'.$mediaAsset->getKey().'.mp3';
        $name = $mediaAsset->original_name ?: $fallback;
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\r\n\x00-\x1F\x7F"]+/u', '_', $name) ?: $fallback;
        $name = trim($name, ". \t\n\r\0\x0B");

        if ($name === '') {
            return $fallback;
        }

        return mb_substr($name, 0, 180);
    }
}
