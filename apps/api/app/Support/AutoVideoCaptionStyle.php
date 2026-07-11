<?php

namespace App\Support;

class AutoVideoCaptionStyle
{
    public const DEFAULT = 'clean_subtitles';

    /** @return array<int, string> */
    public static function canonicalValues(): array
    {
        return [
            'clean_subtitles',
            'bold_hook',
            'minimal',
            'premium_clean',
            'ugc_native',
        ];
    }

    public static function normalize(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return self::DEFAULT;
        }

        $value = trim($value);

        return match ($value) {
            'clean', 'default', 'subtitles' => 'clean_subtitles',
            'bold', 'bold_keywords' => 'bold_hook',
            'premium' => 'premium_clean',
            'ugc', 'native', 'karaoke' => 'ugc_native',
            default => $value,
        };
    }
}
