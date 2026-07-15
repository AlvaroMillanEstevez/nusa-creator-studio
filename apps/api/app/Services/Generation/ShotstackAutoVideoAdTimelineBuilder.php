<?php

namespace App\Services\Generation;

use Illuminate\Support\Str;
use InvalidArgumentException;

class ShotstackAutoVideoAdTimelineBuilder
{
    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function build(?string $productImageUrl, ?string $sourceVideoUrl, ?string $voiceoverUrl, array $plan, array $input): array
    {
        if ($productImageUrl === null && $sourceVideoUrl === null) {
            throw new InvalidArgumentException('Auto Video Ads Shotstack timeline requires an image or video asset.');
        }

        $requestedDuration = $this->targetDuration($input);
        $voiceoverDuration = $this->voiceoverDuration($input);
        $finalDuration = round(max($requestedDuration, $voiceoverDuration ?? 0), 2);
        $visualSourceType = $this->visualSourceType($input, $productImageUrl, $sourceVideoUrl);
        $format = $this->format((string) ($input['ad_format'] ?? 'vertical_9_16'));
        $sourceDuration = $this->assetDuration(data_get($input, 'source_video_asset.metadata.duration_seconds'));
        $scenes = $this->scenes($plan, $finalDuration);
        $videoRanges = $sourceVideoUrl !== null
            ? $this->videoRanges($input, $scenes, $sourceDuration)
            : [];
        $items = $this->timelineItems($scenes, $visualSourceType, $videoRanges, $sourceVideoUrl !== null);
        $visualClips = $this->visualClips($items, $productImageUrl, $sourceVideoUrl, $visualSourceType);
        $captionSchedule = $this->captionSchedule($items, $plan, $finalDuration);
        $overlayClips = $this->overlayClips($items);
        $captionClips = $this->captionClips($captionSchedule);
        $audioClips = $this->audioClips($voiceoverUrl, $finalDuration);
        $tracks = $this->tracks($audioClips, $captionClips, $overlayClips, $visualClips);
        $validation = $this->validateTimeline($tracks, $finalDuration);

        return [
            'edit' => [
                'timeline' => [
                    'background' => '#050712',
                    'tracks' => $tracks,
                ],
                'output' => $this->outputConfig($format),
            ],
            'timeline' => $items,
            'clip_sequence' => $this->clipSequence($items),
            'captions_plan' => $captionSchedule,
            'render_settings' => [
                'requested_target_duration_seconds' => $requestedDuration,
                'effective_target_duration_seconds' => $finalDuration,
                'actual_duration_seconds' => $finalDuration,
                'duration_adjusted_for_voiceover' => $voiceoverDuration !== null && $voiceoverDuration > $requestedDuration,
                'voiceover_duration_seconds' => $voiceoverDuration,
                'visual_source_type' => $visualSourceType,
                'ad_format' => $format['key'],
                'output_aspect_ratio' => $format['aspectRatio'],
                'output_width' => $format['width'],
                'output_height' => $format['height'],
                'caption_schedule' => $captionSchedule,
                'captions_overlapping' => false,
                'caption_visual_strategy' => 'compact_html_caption_with_text_shadow',
                'source_duration_seconds' => $sourceDuration,
                'timeline_validation' => $validation,
                'music_enabled' => false,
            ],
            'timeline_summary' => [
                'selected_clip_count' => count($items),
                'caption_count' => count($captionSchedule),
                'cta_used' => $this->cta($plan, $input),
                'visual_source_type' => $visualSourceType,
                'source_clip_ranges_used' => $this->sourceClipRangesUsed($items),
                'repeated_clip_count' => $this->repeatedClipCount($items),
                'repeated_clip_reason' => $this->repeatedClipCount($items) > 0
                    ? 'Not enough distinct valid source ranges were available for the requested duration.'
                    : null,
                'diverse_source_segments_used' => $this->diverseSourceSegmentsUsed($items),
                'invalid_clip_ranges_discarded' => 0,
                'source_duration_seconds' => $sourceDuration,
                'final_duration_seconds' => $finalDuration,
                'caption_schedule' => $captionSchedule,
                'deduped_caption_count' => count($captionSchedule),
                'repeated_captions_removed' => 0,
                'captions_overlapping' => false,
                'caption_visual_strategy' => 'compact_html_caption_with_text_shadow',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<int, array<string, mixed>>
     */
    private function scenes(array $plan, float $duration): array
    {
        $storyboard = is_array($plan['storyboard'] ?? null) ? $plan['storyboard'] : [];
        $storyboard = array_values(array_filter($storyboard, fn (mixed $scene): bool => is_array($scene)));

        if ($storyboard === []) {
            $storyboard = [
                ['visual_direction' => 'Open with a clear product shot.', 'overlay_text' => 'Worth a quick look?'],
                ['visual_direction' => 'Show one practical product benefit.', 'overlay_text' => 'Clear benefit'],
                ['visual_direction' => 'Show a product detail or usage moment.', 'overlay_text' => 'See the detail'],
                ['visual_direction' => 'End with a stable product shot and CTA.', 'overlay_text' => 'See product details'],
            ];
        }

        $count = min(5, max(3, count($storyboard)));
        $cuts = $this->sceneCuts($duration, $count);

        return collect(array_slice($storyboard, 0, $count))
            ->values()
            ->map(function (array $scene, int $index) use ($cuts): array {
                return [
                    'order' => $index + 1,
                    'timeline_start_seconds' => $cuts[$index][0],
                    'timeline_end_seconds' => $cuts[$index][1],
                    'duration_seconds' => round($cuts[$index][1] - $cuts[$index][0], 2),
                    'instruction' => $this->text($scene['visual_direction'] ?? null, 'Use a clear product-focused visual.'),
                    'text_overlay' => $this->text($scene['overlay_text'] ?? null, 'Product detail'),
                    'narration_line' => $this->text($scene['narration_line'] ?? null, ''),
                    'asset_role' => $this->text($scene['asset_role'] ?? null, 'product_visual'),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{0: float, 1: float}>
     */
    private function sceneCuts(float $duration, int $count): array
    {
        $cuts = [];
        $cursor = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $remaining = $count - $i;
            $length = round(($duration - $cursor) / $remaining, 2);

            if ($i === 0) {
                $length = min($length, 3.2);
            }

            if ($i === $count - 1) {
                $end = $duration;
            } else {
                $end = round(min($duration - (($remaining - 1) * 2), $cursor + max(2.0, $length)), 2);
            }

            $cuts[] = [$cursor, max($cursor + 0.5, $end)];
            $cursor = $cuts[$i][1];
        }

        return $cuts;
    }

    /**
     * @param  array<int, array<string, mixed>>  $scenes
     * @return array<int, array<string, mixed>>
     */
    private function videoRanges(array $input, array $scenes, ?float $sourceDuration): array
    {
        $candidates = collect($this->candidateCuts($input))
            ->filter(fn (array $cut): bool => ($cut['end_seconds'] ?? 0) > ($cut['start_seconds'] ?? 0))
            ->values();

        if ($candidates->isEmpty()) {
            $candidates = collect($this->fallbackCuts($scenes, $sourceDuration));
        }

        return $scenes === []
            ? []
            : collect($scenes)
                ->values()
                ->map(function (array $scene, int $index) use ($candidates, $sourceDuration): array {
                    $candidate = $candidates[$index] ?? $candidates[$index % max(1, $candidates->count())];
                    $start = max(0.0, (float) ($candidate['start_seconds'] ?? 0));
                    $end = max($start + 0.5, (float) ($candidate['end_seconds'] ?? ($start + $scene['duration_seconds'])));

                    if ($sourceDuration !== null) {
                        $start = min($start, max(0.0, $sourceDuration - 0.5));
                        $end = min(max($start + 0.5, $end), $sourceDuration);
                    }

                    return [
                        'source_start_seconds' => round($start, 2),
                        'source_end_seconds' => round($end, 2),
                        'repeated' => $index >= $candidates->count(),
                    ];
                })
                ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function candidateCuts(array $input): array
    {
        $paths = [
            'campaign_generation_summary.recommended_cuts',
            'campaign_generation_summary.edit_plan.timeline',
            'campaign_generation_summary.result_payload.recommended_cuts',
            'campaign_generation_summary.output_payload.recommended_cuts',
        ];

        foreach ($paths as $path) {
            $value = data_get($input, $path);

            if (is_array($value) && $value !== []) {
                return array_values(array_filter(array_map(function (mixed $item): ?array {
                    if (! is_array($item)) {
                        return null;
                    }

                    $start = $this->number($item['start_seconds'] ?? $item['source_start_seconds'] ?? null);
                    $end = $this->number($item['end_seconds'] ?? $item['source_end_seconds'] ?? null);

                    return $start !== null && $end !== null && $end > $start
                        ? ['start_seconds' => $start, 'end_seconds' => $end]
                        : null;
                }, $value)));
            }
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $scenes
     * @return array<int, array<string, mixed>>
     */
    private function fallbackCuts(array $scenes, ?float $sourceDuration): array
    {
        if ($sourceDuration === null || $sourceDuration <= 0) {
            return collect($scenes)
                ->map(fn (array $scene): array => [
                    'start_seconds' => 0,
                    'end_seconds' => max(0.5, (float) $scene['duration_seconds']),
                ])
                ->all();
        }

        $count = max(1, count($scenes));
        $usableWindow = max(0.5, $sourceDuration - 0.5);

        return collect($scenes)
            ->values()
            ->map(function (array $scene, int $index) use ($count, $usableWindow, $sourceDuration): array {
                $start = $count === 1 ? 0.0 : round(($usableWindow / ($count - 1)) * $index, 2);
                $length = min((float) $scene['duration_seconds'], max(0.5, $sourceDuration - $start));

                return [
                    'start_seconds' => $start,
                    'end_seconds' => round(min($sourceDuration, $start + $length), 2),
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $scenes
     * @param  array<int, array<string, mixed>>  $videoRanges
     * @return array<int, array<string, mixed>>
     */
    private function timelineItems(array $scenes, string $visualSourceType, array $videoRanges, bool $hasVideo): array
    {
        return collect($scenes)
            ->values()
            ->map(function (array $scene, int $index) use ($visualSourceType, $videoRanges, $hasVideo): array {
                $useImage = $visualSourceType === 'image'
                    || ($visualSourceType === 'hybrid' && ($index === 0 || $index === count($videoRanges)));
                $source = $hasVideo && ! $useImage ? 'source_video' : 'product_image';
                $range = $source === 'source_video' ? ($videoRanges[$index] ?? null) : null;
                $timelineStart = (float) $scene['timeline_start_seconds'];
                $timelineEnd = (float) $scene['timeline_end_seconds'];

                return array_filter([
                    'order' => $index + 1,
                    'timestamp' => $this->rangeLabel($timelineStart, $timelineEnd),
                    'timeline_range' => $this->rangeLabel($timelineStart, $timelineEnd),
                    'timeline_start_seconds' => $timelineStart,
                    'timeline_end_seconds' => $timelineEnd,
                    'duration_seconds' => (float) $scene['duration_seconds'],
                    'source' => $source,
                    'source_range' => $range ? $this->rangeLabel((float) $range['source_start_seconds'], (float) $range['source_end_seconds']) : null,
                    'source_start_seconds' => $range['source_start_seconds'] ?? null,
                    'source_end_seconds' => $range['source_end_seconds'] ?? null,
                    'instruction' => $scene['instruction'],
                    'text_overlay' => $scene['text_overlay'],
                    'narration_line' => $scene['narration_line'],
                    'repeated_source_range' => (bool) ($range['repeated'] ?? false),
                ], fn (mixed $value): bool => $value !== null);
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function visualClips(array $items, ?string $productImageUrl, ?string $sourceVideoUrl, string $visualSourceType): array
    {
        return collect($items)
            ->map(function (array $item) use ($productImageUrl, $sourceVideoUrl, $visualSourceType): array {
                $usesVideo = $item['source'] === 'source_video' && $sourceVideoUrl !== null;
                $asset = $usesVideo
                    ? [
                        'type' => 'video',
                        'src' => $sourceVideoUrl,
                        'trim' => (float) ($item['source_start_seconds'] ?? 0),
                        'volume' => 0,
                        'transcode' => true,
                    ]
                    : [
                        'type' => 'image',
                        'src' => $productImageUrl ?? $sourceVideoUrl,
                    ];

                return [
                    'asset' => $asset,
                    'start' => (float) $item['timeline_start_seconds'],
                    'length' => (float) $item['duration_seconds'],
                    'fit' => 'cover',
                    'position' => 'center',
                    'offset' => ['x' => 0, 'y' => 0],
                    'transition' => $visualSourceType === 'image' ? ['in' => 'fade', 'out' => 'fade'] : ['in' => 'fade'],
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function overlayClips(array $items): array
    {
        return collect($items)
            ->take(max(0, count($items) - 1))
            ->map(fn (array $item): array => [
                'asset' => [
                    'type' => 'html',
                    'html' => $this->html($this->limitText((string) $item['text_overlay'], 42), 'overlay'),
                    'css' => $this->overlayCss(),
                    'width' => 500,
                    'height' => 110,
                ],
                'start' => round((float) $item['timeline_start_seconds'] + 0.2, 2),
                'length' => min(2.8, max(1.4, (float) $item['duration_seconds'] - 0.4)),
                'position' => 'top',
                'offset' => ['x' => 0, 'y' => 0.14],
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $plan
     * @return array<int, array{start: float, length: float, text: string, type: string}>
     */
    private function captionSchedule(array $items, array $plan, float $finalDuration): array
    {
        $captions = [];

        foreach ($items as $item) {
            $text = $this->limitText((string) ($item['narration_line'] ?: $item['text_overlay']), 58);

            if ($text !== '') {
                $captions[] = [
                    'start' => round((float) $item['timeline_start_seconds'] + 0.45, 2),
                    'length' => min(2.6, max(1.8, (float) $item['duration_seconds'] - 0.7)),
                    'text' => $text,
                    'type' => 'caption',
                ];
            }
        }

        $cta = $this->cta($plan, []);
        $ctaStart = round(max(0.0, $finalDuration - 2.7), 2);
        $captions[] = [
            'start' => $ctaStart,
            'length' => round($finalDuration - $ctaStart, 2),
            'text' => $this->limitText($cta, 42),
            'type' => 'cta',
        ];

        $seen = [];
        $previousEnd = -1.0;
        $deduped = [];

        foreach ($captions as $caption) {
            $key = Str::lower(trim($caption['text']));

            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $caption['start'] = max($caption['start'], round($previousEnd + 0.2, 2));

            if ($caption['start'] >= $finalDuration - 0.3) {
                continue;
            }

            $caption['length'] = min($caption['length'], round($finalDuration - $caption['start'], 2));
            $previousEnd = $caption['start'] + $caption['length'];
            $seen[$key] = true;
            $deduped[] = $caption;
        }

        return array_slice($deduped, 0, 5);
    }

    /**
     * @param  array<int, array{start: float, length: float, text: string, type: string}>  $schedule
     * @return array<int, array<string, mixed>>
     */
    private function captionClips(array $schedule): array
    {
        return collect($schedule)
            ->map(fn (array $caption): array => [
                'asset' => [
                    'type' => 'html',
                    'html' => $this->html($caption['text'], $caption['type'] === 'cta' ? 'caption cta' : 'caption'),
                    'css' => $this->captionCss(),
                    'width' => 540,
                    'height' => 120,
                ],
                'start' => $caption['start'],
                'length' => $caption['length'],
                'position' => 'bottom',
                'offset' => ['x' => 0, 'y' => $caption['type'] === 'cta' ? 0.16 : 0.22],
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function audioClips(?string $voiceoverUrl, float $duration): array
    {
        if ($voiceoverUrl === null) {
            return [];
        }

        return [[
            'asset' => [
                'type' => 'audio',
                'src' => $voiceoverUrl,
                'volume' => 1,
            ],
            'start' => 0,
            'length' => $duration,
        ]];
    }

    /**
     * @return array<int, array{clips: array<int, array<string, mixed>>}>
     */
    private function tracks(array $audioClips, array $captionClips, array $overlayClips, array $visualClips): array
    {
        return array_values(array_filter([
            $audioClips === [] ? null : ['clips' => $audioClips],
            $captionClips === [] ? null : ['clips' => $captionClips],
            $overlayClips === [] ? null : ['clips' => $overlayClips],
            ['clips' => $visualClips],
        ]));
    }

    /**
     * @param  array<int, array{clips: array<int, array<string, mixed>>}>  $tracks
     * @return array<string, mixed>
     */
    private function validateTimeline(array $tracks, float $duration): array
    {
        $checked = 0;

        foreach ($tracks as $trackIndex => $track) {
            foreach ($track['clips'] as $clipIndex => $clip) {
                $start = (float) ($clip['start'] ?? -1);
                $length = (float) ($clip['length'] ?? -1);

                if ($start < 0 || $length <= 0) {
                    throw new InvalidArgumentException("Invalid Auto Video Ads clip timing at {$trackIndex}:{$clipIndex}.");
                }

                if ($start + $length > $duration + 0.25) {
                    throw new InvalidArgumentException("Auto Video Ads clip extends beyond final duration at {$trackIndex}:{$clipIndex}.");
                }

                if (($clip['asset']['type'] ?? null) === 'video' && (float) ($clip['asset']['trim'] ?? 0) < 0) {
                    throw new InvalidArgumentException("Invalid Auto Video Ads video trim at {$trackIndex}:{$clipIndex}.");
                }

                $checked++;
            }
        }

        return [
            'valid' => true,
            'clips_checked' => $checked,
            'max_end_seconds' => $duration,
        ];
    }

    /** @return array<string, mixed> */
    private function outputConfig(array $format): array
    {
        return [
            'format' => 'mp4',
            'aspectRatio' => $format['aspectRatio'],
            'size' => [
                'width' => $format['width'],
                'height' => $format['height'],
            ],
            'fps' => 25,
            'quality' => 'medium',
            'destinations' => [
                ['provider' => 'shotstack', 'exclude' => false],
            ],
        ];
    }

    /** @return array{key: string, aspectRatio: string, width: int, height: int} */
    private function format(string $format): array
    {
        return match ($format) {
            'square_1_1' => ['key' => 'square_1_1', 'aspectRatio' => '1:1', 'width' => 1080, 'height' => 1080],
            'portrait_4_5' => ['key' => 'portrait_4_5', 'aspectRatio' => '4:5', 'width' => 1080, 'height' => 1350],
            default => ['key' => 'vertical_9_16', 'aspectRatio' => '9:16', 'width' => 720, 'height' => 1280],
        };
    }

    private function visualSourceType(array $input, ?string $productImageUrl, ?string $sourceVideoUrl): string
    {
        $value = (string) ($input['visual_source_type'] ?? '');

        if (in_array($value, ['image', 'video', 'hybrid'], true)) {
            return $value;
        }

        return match (true) {
            $productImageUrl !== null && $sourceVideoUrl !== null => 'hybrid',
            $sourceVideoUrl !== null => 'video',
            default => 'image',
        };
    }

    private function targetDuration(array $input): float
    {
        $duration = $this->number($input['effective_target_duration_seconds'] ?? null)
            ?? $this->number($input['target_duration_seconds'] ?? null)
            ?? 15.0;

        return max(8.0, min(60.0, $duration));
    }

    private function voiceoverDuration(array $input): ?float
    {
        return $this->assetDuration($input['voiceover_duration_seconds'] ?? null)
            ?? $this->assetDuration(data_get($input, 'voiceover_generation_summary.audio.duration_seconds'))
            ?? $this->assetDuration(data_get($input, 'voiceover_generation_summary.duration_seconds'))
            ?? $this->assetDuration(data_get($input, 'voiceover_generation_summary.metadata.duration_seconds'));
    }

    private function assetDuration(mixed $value): ?float
    {
        $number = $this->number($value);

        return $number !== null && $number > 0 ? round($number, 2) : null;
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function cta(array $plan, array $input): string
    {
        $candidate = $input['primary_cta'] ?? data_get($plan, 'ad_summary.main_cta') ?? data_get($plan, 'ad_script.CTA');

        return $this->text($candidate, 'See product details');
    }

    private function text(mixed $value, string $fallback): string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return $fallback;
    }

    private function limitText(string $text, int $limit): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return Str::limit($text, $limit, '');
    }

    private function html(string $text, string $class): string
    {
        return '<div class="'.$class.'">'.htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</div>';
    }

    private function overlayCss(): string
    {
        return 'html,body{margin:0;width:100%;height:100%;overflow:hidden;}'
            .'body{display:flex;align-items:center;justify-content:center;font-family:Inter,Arial,sans-serif;text-align:center;}'
            .'.overlay{display:inline-block;max-width:460px;padding:10px 14px;border-radius:18px;color:#f8fafc;'
            .'background:rgba(6,10,24,.48);border:1px solid rgba(255,255,255,.16);font-size:32px;font-weight:740;'
            .'line-height:1.08;text-shadow:0 3px 14px rgba(0,0,0,.76);box-shadow:0 16px 40px rgba(0,0,0,.22);}';
    }

    private function captionCss(): string
    {
        return 'html,body{margin:0;width:100%;height:100%;overflow:hidden;}'
            .'body{display:flex;align-items:center;justify-content:center;font-family:Inter,Arial,sans-serif;text-align:center;}'
            .'.caption{display:inline-block;max-width:470px;padding:6px 10px;border-radius:12px;color:#fff;'
            .'font-size:31px;font-weight:680;line-height:1.12;text-shadow:0 3px 14px rgba(0,0,0,.8);background:rgba(0,0,0,0);}'
            .'.cta{font-size:34px;font-weight:780;text-shadow:0 4px 16px rgba(0,0,0,.86);}';
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function clipSequence(array $items): array
    {
        return collect($items)
            ->map(fn (array $item): array => [
                'clip' => 'Clip '.$item['order'],
                'timeline_range' => $item['timeline_range'],
                'source_range' => $item['source_range'] ?? null,
                'source' => $item['source'],
                'use' => $item['instruction'],
                'text_overlay' => $item['text_overlay'],
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{start_seconds: float, end_seconds: float}>
     */
    private function sourceClipRangesUsed(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item): bool => isset($item['source_start_seconds'], $item['source_end_seconds']))
            ->map(fn (array $item): array => [
                'start_seconds' => (float) $item['source_start_seconds'],
                'end_seconds' => (float) $item['source_end_seconds'],
            ])
            ->values()
            ->all();
    }

    /** @param array<int, array<string, mixed>> $items */
    private function repeatedClipCount(array $items): int
    {
        return collect($items)->filter(fn (array $item): bool => (bool) ($item['repeated_source_range'] ?? false))->count();
    }

    /** @param array<int, array<string, mixed>> $items */
    private function diverseSourceSegmentsUsed(array $items): bool
    {
        $ranges = collect($items)
            ->filter(fn (array $item): bool => isset($item['source_start_seconds']))
            ->pluck('source_start_seconds')
            ->map(fn (mixed $value): int => (int) floor((float) $value / 4))
            ->unique()
            ->count();

        return $ranges >= 2;
    }

    private function rangeLabel(float $start, float $end): string
    {
        return $this->seconds($start).'-'.$this->seconds($end).'s';
    }

    private function seconds(float $value): string
    {
        $rounded = round(max(0.0, $value), 1);

        return rtrim(rtrim(number_format($rounded, 1, '.', ''), '0'), '.');
    }
}
