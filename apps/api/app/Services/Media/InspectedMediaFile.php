<?php

namespace App\Services\Media;

final readonly class InspectedMediaFile
{
    public function __construct(
        public string $type,
        public string $mimeType,
        public string $safeExtension,
        public string $uploadedExtension,
        public string $sanitizedOriginalName,
        public int $sizeBytes,
    ) {}
}
