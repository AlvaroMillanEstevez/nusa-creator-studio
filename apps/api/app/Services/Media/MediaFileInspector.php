<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaFileInspector
{
    public const TYPES = ['image', 'video', 'audio', 'document', 'other'];

    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp',
        'mp4', 'mov', 'webm',
        'mp3', 'wav', 'm4a',
        'pdf', 'txt', 'csv',
    ];

    public const DISALLOWED_EXTENSIONS = [
        'php', 'phtml', 'phar', 'js', 'mjs', 'html', 'htm', 'svg', 'xml',
        'sh', 'bash', 'bat', 'cmd', 'exe', 'dll', 'com', 'msi', 'jar',
        'zip', 'rar', '7z', 'tar', 'gz',
    ];

    private const MIME_RULES = [
        'image/jpeg' => ['type' => 'image', 'extensions' => ['jpg', 'jpeg']],
        'image/png' => ['type' => 'image', 'extensions' => ['png']],
        'image/webp' => ['type' => 'image', 'extensions' => ['webp']],
        'video/mp4' => ['type' => 'video', 'extensions' => ['mp4']],
        'video/quicktime' => ['type' => 'video', 'extensions' => ['mov']],
        'video/webm' => ['type' => 'video', 'extensions' => ['webm']],
        'audio/mpeg' => ['type' => 'audio', 'extensions' => ['mp3']],
        'audio/wav' => ['type' => 'audio', 'extensions' => ['wav']],
        'audio/x-wav' => ['type' => 'audio', 'extensions' => ['wav']],
        'audio/mp4' => ['type' => 'audio', 'extensions' => ['m4a']],
        'audio/x-m4a' => ['type' => 'audio', 'extensions' => ['m4a']],
        'application/pdf' => ['type' => 'document', 'extensions' => ['pdf']],
        'text/plain' => ['type' => 'document', 'extensions' => ['txt', 'csv']],
        'text/csv' => ['type' => 'document', 'extensions' => ['csv']],
    ];

    private const SCRIPT_OR_EXECUTABLE_MIMES = [
        'application/javascript',
        'application/x-dosexec',
        'application/x-executable',
        'application/x-httpd-php',
        'application/x-javascript',
        'application/x-sh',
        'application/xhtml+xml',
        'application/xml',
        'image/svg+xml',
        'text/html',
        'text/javascript',
        'text/xml',
    ];

    public function inspect(UploadedFile $file): InspectedMediaFile
    {
        if (! $file->isValid()) {
            $this->reject('The uploaded file is invalid.');
        }

        $size = $file->getSize();

        if ($size === false || $size <= 0) {
            $this->reject('Empty files are not supported.');
        }

        $maxBytes = (int) config('media.max_size_kb', 51200) * 1024;

        if ($size > $maxBytes) {
            $this->reject('The uploaded file is too large.');
        }

        $originalName = $file->getClientOriginalName();
        $this->validateOriginalName($originalName);

        $uploadedExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (! in_array($uploadedExtension, self::ALLOWED_EXTENSIONS, true)) {
            $this->reject();
        }

        $mimeType = strtolower((string) $file->getMimeType());

        if (
            $mimeType === ''
            || in_array($mimeType, self::SCRIPT_OR_EXECUTABLE_MIMES, true)
            || ! array_key_exists($mimeType, self::MIME_RULES)
        ) {
            $this->reject();
        }

        $rule = self::MIME_RULES[$mimeType];

        if (! in_array($uploadedExtension, $rule['extensions'], true)) {
            $this->reject('The file extension does not match its detected content type.');
        }

        $guessedExtension = strtolower((string) $file->guessExtension());
        $detectedExtension = in_array($guessedExtension, $rule['extensions'], true)
            ? $guessedExtension
            : $rule['extensions'][0];

        return new InspectedMediaFile(
            type: $rule['type'],
            mimeType: $mimeType,
            safeExtension: $detectedExtension,
            uploadedExtension: $uploadedExtension,
            sanitizedOriginalName: $this->sanitizeOriginalName($originalName, $uploadedExtension),
            sizeBytes: (int) $size,
        );
    }

    private function validateOriginalName(string $originalName): void
    {
        if (
            $originalName === ''
            || str_starts_with($originalName, '.')
            || str_contains($originalName, chr(0))
            || str_contains(strtolower($originalName), '%00')
            || str_contains($originalName, '/')
            || str_contains($originalName, '\\')
            || preg_match('/[\x00-\x1F\x7F]/u', $originalName) === 1
        ) {
            $this->reject('The file name is not allowed.');
        }

        $segments = explode('.', strtolower($originalName));

        if (count($segments) < 2) {
            $this->reject();
        }

        $extensionSegments = array_slice($segments, 1);

        foreach ($extensionSegments as $index => $segment) {
            if (in_array($segment, self::DISALLOWED_EXTENSIONS, true)) {
                $this->reject('Executable, script and archive file names are not allowed.');
            }

            $isFinalSegment = $index === array_key_last($extensionSegments);

            if (! $isFinalSegment && in_array($segment, self::ALLOWED_EXTENSIONS, true)) {
                $this->reject('Double-extension file names are not allowed.');
            }
        }
    }

    private function sanitizeOriginalName(string $originalName, string $extension): string
    {
        $sanitized = preg_replace('/[^\p{L}\p{N}._() -]+/u', '_', $originalName) ?? '';
        $sanitized = preg_replace('/\s+/u', ' ', $sanitized) ?? '';
        $sanitized = trim($sanitized, " ._-\t\n\r\0\x0B");

        if ($sanitized === '') {
            $sanitized = "upload.{$extension}";
        }

        return Str::limit($sanitized, 180, '');
    }

    private function reject(string $message = 'This file type is not supported.'): never
    {
        throw ValidationException::withMessages([
            'file' => [$message],
        ]);
    }
}
