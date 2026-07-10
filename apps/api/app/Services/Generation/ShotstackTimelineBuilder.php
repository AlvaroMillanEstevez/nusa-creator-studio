<?php

namespace App\Services\Generation;

use Illuminate\Support\Str;

class ShotstackTimelineBuilder
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function build(string $sourceVideoUrl, ?string $voiceoverUrl, array $input): array
    {
        $targetDuration = $this->targetDuration($input);
        $analysis = $this->analysisPayload($input);
        $clips = $this->clipPlan($analysis, $targetDuration);
        $timelineItems = $this->timelineItems($clips, $analysis, $input);
        $totalDuration = $this->totalDuration($timelineItems);
        $captionClips = $this->captionClips($timelineItems);

        $tracks = [
            ['clips' => $captionClips],
            ['clips' => $this->videoClips($sourceVideoUrl, $timelineItems)],
        ];

        if ($voiceoverUrl !== null) {
            array_unshift($tracks, [
                'clips' => [[
                    'asset' => [
                        'type' => 'audio',
                        'src' => $voiceoverUrl,
                        'volume' => 1,
                    ],
                    'start' => 0,
                    'length' => $totalDuration,
                ]],
            ]);
        }

        return [
            'edit' => [
                'timeline' => [
                    'background' => '#050816',
                    'tracks' => $tracks,
                    'cache' => false,
                ],
                'output' => [
                    'format' => 'mp4',
                    'aspectRatio' => '9:16',
                    'size' => [
                        'width' => 720,
                        'height' => 1280,
                    ],
                    'fps' => 25,
                    'quality' => 'medium',
                    'destinations' => [
                        ['provider' => 'shotstack', 'exclude' => false],
                    ],
                ],
            ],
            'timeline' => $timelineItems,
            'clip_sequence' => $this->clipSequence($timelineItems),
            'captions_plan' => [
                'style' => $this->stringValue($input['caption_style'] ?? null, 'clean_subtitles'),
                'safe_zone' => 'Keep captions inside the mobile-safe center area and away from shopping controls.',
                'sample_captions' => array_values(array_filter(array_map(
                    fn (array $item): ?string => $item['text_overlay'] ?? null,
                    $timelineItems,
                ))),
            ],
            'transition_plan' => [
                'Use clean cuts between selected source moments.',
                'Keep overlays short and readable for vertical social feeds.',
                'Avoid heavy effects in the beta render to preserve product clarity.',
            ],
            'audio_mix_plan' => [
                'include_voiceover' => $voiceoverUrl !== null,
                'music_direction' => $this->stringValue($input['music_direction'] ?? null, 'No music track added by Shotstack beta render.'),
                'mix_notes' => $voiceoverUrl !== null
                    ? ['Voiceover is placed on a dedicated audio track.', 'Source video audio is lowered to avoid competing with narration.']
                    : ['Source video audio is kept at a moderate level.', 'Add licensed music manually if needed.'],
            ],
            'render_settings' => [
                'provider' => 'shotstack',
                'platform' => $this->stringValue($input['platform'] ?? null, 'tiktok'),
                'format' => $this->stringValue($input['format'] ?? null, 'vertical_9_16'),
                'target_duration_seconds' => $targetDuration,
                'actual_duration_seconds' => $totalDuration,
                'editing_style' => $this->stringValue($input['editing_style'] ?? null, 'ugc_natural'),
                'pacing' => $this->stringValue($input['pacing'] ?? null, 'medium'),
                'caption_style' => $this->stringValue($input['caption_style'] ?? null, 'clean_subtitles'),
                'resolution' => '720x1280',
                'aspect_ratio' => '9:16',
            ],
            'timeline_summary' => [
                'selected_clip_count' => count($timelineItems),
                'duration_seconds' => $totalDuration,
                'source' => $this->timelineSource($analysis),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function analysisPayload(array $input): array
    {
        $analysis = $input['source_video_to_campaign_summary'] ?? [];

        return is_array($analysis) ? $analysis : [];
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<int, array<string, mixed>>
     */
    private function clipPlan(array $analysis, int $targetDuration): array
    {
        $timeline = data_get($analysis, 'edit_plan.timeline');

        if (is_array($timeline) && $timeline !== []) {
            return $this->normalizeCuts($timeline, $targetDuration, 'edit_plan.timeline');
        }

        $cuts = $analysis['recommended_cuts'] ?? null;

        if (is_array($cuts) && $cuts !== []) {
            return $this->normalizeCuts($cuts, $targetDuration, 'recommended_cuts');
        }

        return [[
            'source_start_seconds' => 0.0,
            'source_end_seconds' => (float) min(max(8, $targetDuration), 15),
            'duration_seconds' => (float) min(max(8, $targetDuration), 15),
            'text_overlay' => 'Start with the clearest product shot',
            'instruction' => 'Use the opening product footage as a simple beta render.',
            'source' => 'fallback_first_seconds',
        ]];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCuts(array $items, int $targetDuration, string $source): array
    {
        $cuts = [];
        $cursor = 0.0;
        $total = 0.0;

        foreach ($items as $item) {
            if (! is_array($item) && ! is_string($item)) {
                continue;
            }

            $normalized = is_array($item)
                ? $this->cutFromArray($item, $cursor, $source)
                : $this->cutFromString($item, $cursor, $source);

            if ($normalized === null) {
                continue;
            }

            $duration = (float) $normalized['duration_seconds'];

            if ($duration < 0.5) {
                continue;
            }

            if ($total + $duration > $targetDuration && $total >= 5) {
                break;
            }

            $cuts[] = $normalized;
            $cursor = (float) $normalized['source_end_seconds'];
            $total += $duration;

            if (count($cuts) >= 5) {
                break;
            }
        }

        return $cuts === []
            ? [[
                'source_start_seconds' => 0.0,
                'source_end_seconds' => (float) min(max(8, $targetDuration), 15),
                'duration_seconds' => (float) min(max(8, $targetDuration), 15),
                'text_overlay' => 'Product demo highlight',
                'instruction' => 'Use the first usable seconds of source video.',
                'source' => 'fallback_first_seconds',
            ]]
            : $cuts;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function cutFromArray(array $item, float $cursor, string $source): ?array
    {
        [$start, $end] = $this->timeRange($item, $cursor);
        $duration = max(0.0, $end - $start);

        if ($duration <= 0) {
            $duration = $this->numberValue($item['duration_seconds'] ?? $item['length'] ?? null, 3.0);
            $end = $start + $duration;
        }

        return [
            'source_start_seconds' => round(max(0.0, $start), 2),
            'source_end_seconds' => round(max($start + 0.5, $end), 2),
            'duration_seconds' => round(max(0.5, $duration), 2),
            'text_overlay' => $this->captionForItem($item),
            'instruction' => $this->instructionForItem($item),
            'source' => $source,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cutFromString(string $item, float $cursor, string $source): ?array
    {
        $text = trim($item);

        if ($text === '') {
            return null;
        }

        [$start, $end] = $this->parseTimestamp($text, $cursor);
        $duration = max(0.5, $end - $start);

        return [
            'source_start_seconds' => round($start, 2),
            'source_end_seconds' => round($start + $duration, 2),
            'duration_seconds' => round($duration, 2),
            'text_overlay' => Str::limit(preg_replace('/^\s*\d+(?:\.\d+)?\s*(?:-|to|–)\s*\d+(?:\.\d+)?s?\s*[:\-]?\s*/i', '', $text) ?: $text, 64, ''),
            'instruction' => $text,
            'source' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{0: float, 1: float}
     */
    private function timeRange(array $item, float $cursor): array
    {
        $start = $this->numberValue(
            $item['source_start_seconds']
                ?? $item['start_seconds']
                ?? $item['start']
                ?? data_get($item, 'source.start_seconds'),
            -1,
        );
        $end = $this->numberValue(
            $item['source_end_seconds']
                ?? $item['end_seconds']
                ?? $item['end']
                ?? data_get($item, 'source.end_seconds'),
            -1,
        );

        if ($start >= 0 && $end > $start) {
            return [$start, $end];
        }

        $timestamp = $item['timestamp'] ?? $item['time'] ?? $item['range'] ?? null;

        if (is_string($timestamp)) {
            return $this->parseTimestamp($timestamp, $cursor);
        }

        $duration = $this->numberValue($item['duration_seconds'] ?? $item['length'] ?? null, 3.0);

        return [$cursor, $cursor + $duration];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function parseTimestamp(string $value, float $fallbackStart): array
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*s?\s*(?:-|to|–)\s*(\d+(?:\.\d+)?)\s*s?/i', $value, $matches) === 1) {
            $start = (float) $matches[1];
            $end = (float) $matches[2];

            return [$start, max($start + 0.5, $end)];
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*s/i', $value, $matches) === 1) {
            $start = (float) $matches[1];

            return [$start, $start + 3.0];
        }

        return [$fallbackStart, $fallbackStart + 3.0];
    }

    /**
     * @param  array<int, array<string, mixed>>  $clips
     * @param  array<string, mixed>  $analysis
     * @param  array<string, mixed>  $input
     * @return array<int, array<string, mixed>>
     */
    private function timelineItems(array $clips, array $analysis, array $input): array
    {
        $cta = $this->stringValue($input['cta'] ?? null, $this->firstString($analysis['cta_options'] ?? null) ?? 'Review the product details before buying.');
        $start = 0.0;
        $lastIndex = max(0, count($clips) - 1);

        return array_map(function (array $clip, int $index) use (&$start, $cta, $lastIndex): array {
            $duration = (float) $clip['duration_seconds'];
            $text = $clip['text_overlay'] ?: ($index === 0 ? 'Start with the product' : $cta);
            $item = [
                'order' => $index + 1,
                'timestamp' => $this->timestamp($start, $start + $duration),
                'start_seconds' => round($start, 2),
                'duration_seconds' => round($duration, 2),
                'source_start_seconds' => $clip['source_start_seconds'],
                'source_end_seconds' => $clip['source_end_seconds'],
                'layer' => 'video',
                'instruction' => $clip['instruction'],
                'text_overlay' => $index === $lastIndex ? $cta : $text,
                'source' => $clip['source'],
            ];
            $start += $duration;

            return $item;
        }, $clips, array_keys($clips));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function videoClips(string $sourceVideoUrl, array $items): array
    {
        return array_map(fn (array $item): array => [
            'asset' => [
                'type' => 'video',
                'src' => $sourceVideoUrl,
                'trim' => (float) $item['source_start_seconds'],
                'volume' => 0.35,
                'transcode' => true,
            ],
            'start' => (float) $item['start_seconds'],
            'length' => (float) $item['duration_seconds'],
            'fit' => 'cover',
            'transition' => [
                'in' => 'none',
                'out' => 'none',
            ],
        ], $items);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function captionClips(array $items): array
    {
        return array_values(array_filter(array_map(function (array $item): ?array {
            $text = $this->stringValue($item['text_overlay'] ?? null, '');

            if ($text === '') {
                return null;
            }

            return [
                'asset' => [
                    'type' => 'title',
                    'text' => Str::limit($text, 80, ''),
                    'style' => 'minimal',
                    'color' => '#ffffff',
                    'background' => '#111827',
                ],
                'start' => (float) $item['start_seconds'],
                'length' => min((float) $item['duration_seconds'], 4.0),
                'position' => 'bottom',
                'offset' => [
                    'x' => 0,
                    'y' => 0.12,
                ],
            ];
        }, $items)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function clipSequence(array $items): array
    {
        return array_map(fn (array $item): array => [
            'clip' => 'Clip '.$item['order'],
            'source' => 'source video',
            'timestamp' => $item['timestamp'],
            'use' => $item['instruction'],
            'text_overlay' => $item['text_overlay'],
        ], $items);
    }

    private function targetDuration(array $input): int
    {
        $value = $input['target_duration_seconds'] ?? 30;
        $duration = is_numeric($value) ? (int) $value : 30;

        return max(8, min(30, $duration));
    }

    private function totalDuration(array $items): float
    {
        return round(array_reduce(
            $items,
            fn (float $total, array $item): float => $total + (float) $item['duration_seconds'],
            0.0,
        ), 2);
    }

    private function captionForItem(array $item): string
    {
        return $this->stringValue(
            $item['text_overlay']
                ?? $item['on_screen_text']
                ?? $item['caption']
                ?? $item['hook']
                ?? $item['label']
                ?? null,
            'Product highlight',
        );
    }

    private function instructionForItem(array $item): string
    {
        return $this->stringValue(
            $item['instruction']
                ?? $item['visual_direction']
                ?? $item['reason']
                ?? $item['description']
                ?? $item['note']
                ?? null,
            'Use this source video moment in the beta edit.',
        );
    }

    private function numberValue(mixed $value, float $fallback): float
    {
        return is_numeric($value) ? (float) $value : $fallback;
    }

    private function stringValue(mixed $value, string $fallback): string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }

    private function firstString(mixed $value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if (! is_array($value)) {
            return null;
        }

        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                return trim($item);
            }
        }

        return null;
    }

    private function timestamp(float $start, float $end): string
    {
        return rtrim(rtrim((string) round($start, 1), '0'), '.')
            .'-'
            .rtrim(rtrim((string) round($end, 1), '0'), '.')
            .'s';
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function timelineSource(array $analysis): string
    {
        if (is_array(data_get($analysis, 'edit_plan.timeline')) && data_get($analysis, 'edit_plan.timeline') !== []) {
            return 'edit_plan.timeline';
        }

        if (is_array($analysis['recommended_cuts'] ?? null) && $analysis['recommended_cuts'] !== []) {
            return 'recommended_cuts';
        }

        return 'fallback_first_seconds';
    }
}
