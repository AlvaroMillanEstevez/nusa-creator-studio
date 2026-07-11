<?php

namespace App\Services\Generation;

use App\Support\AutoVideoCaptionStyle;
use Illuminate\Support\Str;

class ShotstackTimelineBuilder
{
    private const OUTPUT_WIDTH = 720;

    private const OUTPUT_HEIGHT = 1280;

    private const CAPTION_SAFE_WIDTH = 520;

    private const CAPTION_SAFE_HEIGHT = 120;

    private const CAPTION_MAX_LINES = 2;

    private const CAPTION_MAX_CHARS_PER_LINE = 22;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function build(string $sourceVideoUrl, ?string $voiceoverUrl, array $input): array
    {
        $brief = $this->creativeBrief($input);
        $requestedTargetDuration = $this->requestedTargetDuration($input, $brief);
        $targetDuration = $this->targetDuration($input, $brief);
        $analysis = $this->analysisPayload($input);
        $cta = $this->stringValue($brief['selected_cta'] ?? null, $this->firstString($analysis['cta_options'] ?? null) ?? 'Review the product details before buying.');
        $clips = $this->clipPlan($analysis, $targetDuration, $brief);
        $timelineItems = $this->timelineItems($clips, $analysis, $input, $brief, $cta);
        $warnings = $this->qualityWarnings($brief);
        $durationSyncStrategy = $voiceoverUrl === null ? 'no_voiceover_target_duration' : 'voiceover_matched';
        $voiceoverDuration = $this->numberValue($brief['voiceover_duration_seconds'] ?? null, 0.0);

        if ($voiceoverUrl !== null && $voiceoverDuration > 0) {
            $requiredDuration = max($targetDuration, $voiceoverDuration + 0.5);
            $timelineItems = $this->extendTimelineForDuration($timelineItems, $requiredDuration, $cta, $durationSyncStrategy, $warnings);
        }

        $totalDuration = $this->totalDuration($timelineItems);
        $captionStyle = AutoVideoCaptionStyle::normalize($brief['caption_style'] ?? $input['caption_style'] ?? null);
        $captionWarnings = [];
        $captionCandidates = $this->buildCaptionCandidates($timelineItems, $analysis, $brief, $cta);
        $captionSchedule = $this->selectCaptionSchedule($captionCandidates, $totalDuration, $cta, $captionWarnings);
        $captionsOverlapping = $this->captionsOverlap($captionSchedule);

        if ($captionsOverlapping) {
            $captionWarnings[] = 'Caption schedule had overlapping items after clamping.';
        }

        $captionWarnings = array_values(array_unique($captionWarnings));
        $captionClips = $this->captionClips($captionSchedule, $captionStyle);
        $outputMetadata = $this->outputMetadata();
        $durationAdjustedForVoiceover = $voiceoverDuration > 0 && $targetDuration > $requestedTargetDuration;
        $timelineSummary = [
            'selected_clip_count' => count(array_filter($timelineItems, fn (array $item): bool => ($item['source'] ?? '') !== 'cta_end_card')),
            'final_duration_seconds' => $totalDuration,
            'duration_seconds' => $totalDuration,
            'requested_target_duration_seconds' => $requestedTargetDuration,
            'effective_target_duration_seconds' => $targetDuration,
            'voiceover_duration_seconds' => $voiceoverDuration > 0 ? round($voiceoverDuration, 2) : null,
            'duration_adjusted_for_voiceover' => $durationAdjustedForVoiceover,
            'duration_sync_strategy' => $durationSyncStrategy,
            'caption_schedule' => $captionSchedule,
            'caption_count' => count($captionClips),
            'captions_overlapping' => $captionsOverlapping,
            'caption_style' => $captionStyle,
            'caption_visual_strategy' => 'compact_timed_html_overlays',
            'caption_safe_area_applied' => true,
            'caption_max_lines' => self::CAPTION_MAX_LINES,
            'caption_warnings' => $captionWarnings,
            'cta_used' => $cta,
            'source' => $this->timelineSource($analysis),
            'timeline_quality_warnings' => array_values(array_unique(array_merge($warnings, $captionWarnings))),
        ] + $outputMetadata;

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
                'output' => $this->shotstackOutputConfig(),
            ],
            'timeline' => $timelineItems,
            'clip_sequence' => $this->clipSequence($timelineItems),
            'captions_plan' => [
                'style' => $captionStyle,
                'caption_count' => count($captionClips),
                'caption_schedule' => $captionSchedule,
                'captions_overlapping' => $captionsOverlapping,
                'caption_visual_strategy' => 'compact_timed_html_overlays',
                'caption_safe_area_applied' => true,
                'caption_max_lines' => self::CAPTION_MAX_LINES,
                'caption_warnings' => $captionWarnings,
                'safe_zone' => 'Bottom center, within a conservative 9:16 safe area and above shopping/app controls.',
                'safe_area' => [
                    'position' => 'bottom_center',
                    'width_percent' => 72,
                    'width_pixels' => self::CAPTION_SAFE_WIDTH,
                    'height_pixels' => self::CAPTION_SAFE_HEIGHT,
                    'bottom_margin_percent' => 18,
                    'max_lines' => self::CAPTION_MAX_LINES,
                    'max_characters_per_line' => self::CAPTION_MAX_CHARS_PER_LINE,
                    'font_size_pixels' => $this->captionStyleForShotstack($captionStyle)['font_size'],
                    'letter_spacing' => 0,
                ],
                'sample_captions' => array_values(array_map(
                    fn (array $item): string => $item['text'],
                    $captionSchedule,
                )),
            ],
            'transition_plan' => [
                'Use intentional clean cuts between product moments.',
                'Use safe bottom captions that do not cover the product or shopping controls.',
                'Hold the final product/CTA beat long enough for the voiceover to finish.',
            ],
            'audio_mix_plan' => [
                'include_voiceover' => $voiceoverUrl !== null,
                'music_direction' => $this->stringValue($input['music_direction'] ?? null, 'No music track added by Shotstack beta render.'),
                'mix_notes' => $voiceoverUrl !== null
                    ? ['Voiceover is placed on a dedicated audio track.', 'Source video audio is lowered to avoid competing with narration.', 'Video duration is matched to the selected voiceover when duration data is available.']
                    : ['Source video audio is kept at a moderate level.', 'Add licensed music manually if needed.'],
            ],
            'render_settings' => [
                'provider' => 'shotstack',
                'platform' => $this->stringValue($input['platform'] ?? null, 'tiktok'),
                'format' => $this->stringValue($input['format'] ?? null, 'vertical_9_16'),
                'target_duration_seconds' => $targetDuration,
                'requested_target_duration_seconds' => $requestedTargetDuration,
                'effective_target_duration_seconds' => $targetDuration,
                'actual_duration_seconds' => $totalDuration,
                'final_duration_seconds' => $totalDuration,
                'voiceover_duration_seconds' => $timelineSummary['voiceover_duration_seconds'],
                'duration_adjusted_for_voiceover' => $durationAdjustedForVoiceover,
                'duration_sync_strategy' => $durationSyncStrategy,
                'editing_style' => $this->stringValue($input['editing_style'] ?? null, 'ugc_natural'),
                'pacing' => $this->stringValue($input['pacing'] ?? null, 'medium'),
                'caption_style' => $captionStyle,
                'resolution' => '720x1280',
                'aspect_ratio' => '9:16',
                'output_aspect_ratio' => $outputMetadata['output_aspect_ratio'],
                'output_width' => $outputMetadata['output_width'],
                'output_height' => $outputMetadata['output_height'],
                'source_fit_strategy' => $outputMetadata['source_fit_strategy'],
                'has_letterboxing_expected' => $outputMetadata['has_letterboxing_expected'],
                'caption_schedule' => $captionSchedule,
                'caption_count' => count($captionClips),
                'captions_overlapping' => $captionsOverlapping,
                'caption_visual_strategy' => 'compact_timed_html_overlays',
                'caption_safe_area_applied' => true,
                'caption_max_lines' => self::CAPTION_MAX_LINES,
                'caption_warnings' => $captionWarnings,
            ],
            'timeline_summary' => $timelineSummary,
            'creative_brief' => $brief,
        ];
    }

    /** @return array<string, mixed> */
    private function shotstackOutputConfig(): array
    {
        return [
            'format' => 'mp4',
            'aspectRatio' => '9:16',
            'size' => [
                'width' => self::OUTPUT_WIDTH,
                'height' => self::OUTPUT_HEIGHT,
            ],
            'fps' => 25,
            'quality' => 'medium',
            'destinations' => [
                ['provider' => 'shotstack', 'exclude' => false],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function outputMetadata(): array
    {
        return [
            'output_aspect_ratio' => '9:16',
            'output_width' => self::OUTPUT_WIDTH,
            'output_height' => self::OUTPUT_HEIGHT,
            'source_fit_strategy' => 'cover_crop_to_vertical',
            'has_letterboxing_expected' => false,
        ];
    }

    /** @param array<string, mixed> $input */
    private function creativeBrief(array $input): array
    {
        $brief = $input['creative_brief'] ?? [];

        return is_array($brief) ? $brief : [];
    }

    /** @param array<string, mixed> $input */
    private function analysisPayload(array $input): array
    {
        $analysis = $input['source_video_to_campaign_summary'] ?? [];

        return is_array($analysis) ? $analysis : [];
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @param  array<string, mixed>  $brief
     * @return array<int, array<string, mixed>>
     */
    private function clipPlan(array $analysis, int $targetDuration, array $brief): array
    {
        $preferred = $brief['preferred_cuts'] ?? null;

        if (is_array($preferred) && $preferred !== []) {
            return $this->normalizeCuts($preferred, $targetDuration, 'creative_brief.preferred_cuts');
        }

        $timeline = data_get($analysis, 'edit_plan.timeline');

        if (is_array($timeline) && $timeline !== []) {
            return $this->normalizeCuts($timeline, $targetDuration, 'edit_plan.timeline');
        }

        $fallback = $brief['fallback_cuts'] ?? null;

        if (is_array($fallback) && $fallback !== []) {
            return $this->normalizeCuts($fallback, $targetDuration, 'creative_brief.fallback_cuts');
        }

        $cuts = $analysis['recommended_cuts'] ?? null;

        if (is_array($cuts) && $cuts !== []) {
            return $this->normalizeCuts($cuts, $targetDuration, 'recommended_cuts');
        }

        return [$this->fallbackClip($targetDuration, 'fallback_first_seconds')];
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

            if ($total + $duration > $targetDuration && $total >= min(6, $targetDuration)) {
                break;
            }

            $cuts[] = $normalized;
            $cursor = (float) $normalized['source_end_seconds'];
            $total += $duration;

            if (count($cuts) >= 5) {
                break;
            }
        }

        return $cuts === [] ? [$this->fallbackClip($targetDuration, 'fallback_first_seconds')] : $cuts;
    }

    /** @return array<string, mixed> */
    private function fallbackClip(int $targetDuration, string $source): array
    {
        $duration = (float) min(max(8, $targetDuration), 15);

        return [
            'source_start_seconds' => 0.0,
            'source_end_seconds' => $duration,
            'duration_seconds' => $duration,
            'text_overlay' => 'Start with the clearest product shot',
            'instruction' => 'Use the opening product footage as a simple beta render.',
            'source' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function cutFromArray(array $item, float $cursor, string $source): ?array
    {
        [$start, $end] = $this->timeRange($item, $cursor);
        $start = max(0.0, $start);
        $end = max($start + 0.5, $end);
        $duration = max(0.5, $end - $start);

        if ($duration > 8) {
            $duration = 8.0;
            $end = $start + $duration;
        }

        return [
            'source_start_seconds' => round($start, 2),
            'source_end_seconds' => round($end, 2),
            'duration_seconds' => round($duration, 2),
            'text_overlay' => $this->captionForItem($item),
            'instruction' => $this->instructionForItem($item),
            'source' => $source,
        ];
    }

    /** @return array<string, mixed>|null */
    private function cutFromString(string $item, float $cursor, string $source): ?array
    {
        $text = trim($item);

        if ($text === '') {
            return null;
        }

        [$start, $end] = $this->parseTimestamp($text, $cursor);
        $duration = min(8.0, max(0.5, $end - $start));

        return [
            'source_start_seconds' => round(max(0.0, $start), 2),
            'source_end_seconds' => round(max(0.5, $start + $duration), 2),
            'duration_seconds' => round($duration, 2),
            'text_overlay' => $this->safeCaption(preg_replace('/^\s*\d+(?:\.\d+)?\s*(?:-|to|–)\s*\d+(?:\.\d+)?s?\s*[:\-]?\s*/i', '', $text) ?: $text),
            'instruction' => $text,
            'source' => $source,
        ];
    }

    /** @return array{0: float, 1: float} */
    private function timeRange(array $item, float $cursor): array
    {
        $start = $this->numberValue($item['source_start_seconds'] ?? $item['start_seconds'] ?? $item['start'] ?? data_get($item, 'source.start_seconds'), -1);
        $end = $this->numberValue($item['source_end_seconds'] ?? $item['end_seconds'] ?? $item['end'] ?? data_get($item, 'source.end_seconds'), -1);

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

    /** @return array{0: float, 1: float} */
    private function parseTimestamp(string $value, float $fallbackStart): array
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*s?\s*(?:-|to|–)\s*(\d+(?:\.\d+)?)\s*s?/i', $value, $matches) === 1) {
            $start = (float) $matches[1];
            $end = (float) $matches[2];

            return [max(0.0, $start), max($start + 0.5, $end)];
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*s/i', $value, $matches) === 1) {
            $start = max(0.0, (float) $matches[1]);

            return [$start, $start + 3.0];
        }

        return [$fallbackStart, $fallbackStart + 3.0];
    }

    /**
     * @param  array<int, array<string, mixed>>  $clips
     * @param  array<string, mixed>  $analysis
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $brief
     * @return array<int, array<string, mixed>>
     */
    private function timelineItems(array $clips, array $analysis, array $input, array $brief, string $cta): array
    {
        $start = 0.0;
        $lastIndex = max(0, count($clips) - 1);
        $alternates = $this->captionAlternates($analysis, $brief, $cta);

        return array_map(function (array $clip, int $index) use (&$start, $cta, $lastIndex, $alternates): array {
            $duration = (float) $clip['duration_seconds'];
            $text = $index === $lastIndex
                ? $cta
                : ($clip['text_overlay'] ?: ($alternates[$index] ?? ($index === 0 ? 'Start with the product' : $cta)));
            $item = [
                'order' => $index + 1,
                'timestamp' => $this->timestamp($start, $start + $duration),
                'start_seconds' => round($start, 2),
                'duration_seconds' => round($duration, 2),
                'source_start_seconds' => $clip['source_start_seconds'],
                'source_end_seconds' => $clip['source_end_seconds'],
                'layer' => $index === $lastIndex ? 'cta' : 'video',
                'instruction' => $index === 0
                    ? 'Open with the clearest product-focused hook shot.'
                    : ($clip['instruction'] ?? 'Use this source video moment.'),
                'text_overlay' => $this->safeCaption($text),
                'source' => $clip['source'],
            ];
            $start += $duration;

            return $item;
        }, $clips, array_keys($clips));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, string>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function extendTimelineForDuration(array $items, float $requiredDuration, string $cta, string &$strategy, array &$warnings): array
    {
        $total = $this->totalDuration($items);

        if ($total >= $requiredDuration) {
            $strategy = 'voiceover_matched';

            return $items;
        }

        $best = $items[0] ?? [
            'source_start_seconds' => 0.0,
            'source_end_seconds' => 4.0,
            'text_overlay' => 'Product detail',
        ];
        $usedRepeat = false;

        while ($total < $requiredDuration - 0.1 && count($items) < 10) {
            $remaining = round($requiredDuration - $total, 2);
            $duration = min(max($remaining, 1.0), $remaining > 4.0 ? 4.0 : $remaining);
            $isFinal = $remaining <= 4.0;
            $items[] = [
                'order' => count($items) + 1,
                'timestamp' => $this->timestamp($total, $total + $duration),
                'start_seconds' => round($total, 2),
                'duration_seconds' => round($duration, 2),
                'source_start_seconds' => (float) ($best['source_start_seconds'] ?? 0.0),
                'source_end_seconds' => (float) ($best['source_end_seconds'] ?? 4.0),
                'layer' => $isFinal ? 'cta' : 'video',
                'instruction' => $isFinal
                    ? 'Hold a stable product frame and show the final call to action while the voiceover finishes.'
                    : 'Repeat the strongest product moment to keep video visible while the voiceover continues.',
                'text_overlay' => $this->safeCaption($isFinal ? $cta : ($best['text_overlay'] ?? 'Product detail')),
                'source' => $isFinal ? 'cta_end_card' : 'repeated_product_clip',
            ];
            $usedRepeat = $usedRepeat || ! $isFinal;
            $total = $this->totalDuration($items);
        }

        $strategy = $usedRepeat ? 'repeated_product_clip' : 'extended_with_end_card';
        $warnings[] = 'Timeline was extended so the selected voiceover is not cut off.';

        return $items;
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
                'volume' => 0.25,
                'transcode' => true,
            ],
            'start' => (float) $item['start_seconds'],
            'length' => (float) $item['duration_seconds'],
            'fit' => 'cover',
            'position' => 'center',
            'offset' => [
                'x' => 0,
                'y' => 0,
            ],
            'transition' => [
                'in' => 'none',
                'out' => 'none',
            ],
        ], $items);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $analysis
     * @param  array<string, mixed>  $brief
     * @return array<int, array{text: string, type: string, preferred_start_seconds: float|null}>
     */
    private function buildCaptionCandidates(array $items, array $analysis, array $brief, string $cta): array
    {
        $candidates = [];
        $seen = [];
        $push = function (mixed $value, string $type, ?float $preferredStart = null) use (&$candidates, &$seen): void {
            $text = $this->normalizeCaptionText($value);

            if ($text === '') {
                return;
            }

            $key = ($type === 'cta' ? 'cta:' : '').mb_strtolower($text);

            if (isset($seen[$key])) {
                return;
            }

            $seen[$key] = true;
            $candidates[] = [
                'text' => $text,
                'type' => $type,
                'preferred_start_seconds' => $preferredStart,
            ];
        };

        foreach ($items as $index => $item) {
            if (($item['source'] ?? null) === 'cta_end_card') {
                continue;
            }

            $push(
                $item['text_overlay'] ?? null,
                $index === 0 ? 'hook' : 'benefit',
                is_numeric($item['start_seconds'] ?? null) ? (float) $item['start_seconds'] + 0.25 : null,
            );
        }

        foreach ([$analysis['captions'] ?? null, data_get($analysis, 'edit_plan.captions')] as $captions) {
            if (! is_array($captions)) {
                continue;
            }

            foreach ($captions as $caption) {
                $push(is_array($caption) ? ($caption['caption'] ?? $caption['text'] ?? null) : $caption, 'benefit');
            }
        }

        if (is_array($analysis['hooks'] ?? null)) {
            foreach ($analysis['hooks'] as $hook) {
                $push(is_array($hook) ? ($hook['hook'] ?? $hook['text'] ?? null) : $hook, 'hook');
            }
        }

        $push(data_get($analysis, 'edit_plan.opening'), 'hook');
        $push(data_get($analysis, 'edit_plan.middle'), 'benefit');
        $push($analysis['campaign_angle'] ?? null, 'benefit');
        $push($cta, 'cta');

        if ($candidates === []) {
            $push('Product highlight', 'hook', 0.6);
            $push('Easy daily use', 'benefit');
            $push($cta, 'cta');
        }

        return $candidates;
    }

    /**
     * @param  array<int, array{text: string, type: string, preferred_start_seconds: float|null}>  $candidates
     * @param  array<int, string>  $warnings
     * @return array<int, array{start: float, length: float, text: string, type: string}>
     */
    private function selectCaptionSchedule(array $candidates, float $finalDuration, string $cta, array &$warnings): array
    {
        $finalDuration = max(1.0, $finalDuration);
        $normalCandidates = array_values(array_filter($candidates, fn (array $candidate): bool => $candidate['type'] !== 'cta'));
        $ctaCandidate = collect($candidates)->first(fn (array $candidate): bool => $candidate['type'] === 'cta')
            ?? ['text' => $cta, 'type' => 'cta', 'preferred_start_seconds' => null];

        if ($normalCandidates === []) {
            $normalCandidates[] = ['text' => 'Product highlight', 'type' => 'hook', 'preferred_start_seconds' => 0.6];
        }

        $normalLimit = $finalDuration >= 24 ? 4 : ($finalDuration >= 14 ? 3 : 2);
        $selected = array_slice($normalCandidates, 0, $normalLimit);
        $selected[] = $ctaCandidate;
        $slots = $this->captionTimingForFinalDuration($finalDuration, count($selected));
        $schedule = [];
        $previousEnd = 0.0;

        foreach ($selected as $index => $candidate) {
            $isCta = $candidate['type'] === 'cta';
            $length = $isCta
                ? min(2.8, max(2.1, $finalDuration * 0.14))
                : min(2.6, max(1.8, $finalDuration * 0.12));
            $length = round(min(3.2, $length), 2);
            $preferred = $isCta
                ? max(0.5, $finalDuration - $length - 0.6)
                : ($slots[$index] ?? ($candidate['preferred_start_seconds'] ?? 0.6));

            if (! $isCta && is_numeric($candidate['preferred_start_seconds']) && (float) $candidate['preferred_start_seconds'] > 0) {
                $preferred = min((float) $candidate['preferred_start_seconds'], $preferred + 1.0);
            }

            $start = max(0.4, min((float) $preferred, max(0.4, $finalDuration - $length)));

            if ($schedule !== [] && $start < $previousEnd + 0.2) {
                $start = $previousEnd + 0.2;
            }

            if ($start + $length > $finalDuration) {
                $start = max(0.4, $finalDuration - $length);
            }

            if ($schedule !== [] && $start < $previousEnd + 0.2) {
                $warnings[] = 'A caption was skipped because the schedule was too tight.';

                continue;
            }

            $text = $this->truncateCaptionForOverlay($candidate['text'], $warnings);

            if ($text === '') {
                continue;
            }

            $schedule[] = [
                'start' => round($start, 2),
                'length' => $length,
                'text' => $text,
                'type' => $isCta ? 'cta' : (string) $candidate['type'],
            ];
            $previousEnd = $start + $length;
        }

        return $schedule;
    }

    /** @return array<int, float> */
    private function captionTimingForFinalDuration(float $finalDuration, int $captionCount): array
    {
        $normalCount = max(0, $captionCount - 1);

        return match ($normalCount) {
            0 => [],
            1 => [0.7],
            2 => [0.7, max(3.0, $finalDuration * 0.45)],
            3 => [0.7, max(3.0, $finalDuration * 0.34), max(5.4, $finalDuration * 0.62)],
            default => [0.7, max(3.0, $finalDuration * 0.28), max(5.4, $finalDuration * 0.50), max(7.2, $finalDuration * 0.70)],
        };
    }

    /** @param array<int, array{start: float, length: float, text: string, type: string}> $schedule */
    private function captionsOverlap(array $schedule): bool
    {
        usort($schedule, fn (array $a, array $b): int => $a['start'] <=> $b['start']);
        $previousEnd = null;

        foreach ($schedule as $item) {
            if ($previousEnd !== null && $item['start'] < $previousEnd + 0.19) {
                return true;
            }

            $previousEnd = $item['start'] + $item['length'];
        }

        return false;
    }

    /**
     * @param  array<int, array{start: float, length: float, text: string, type: string}>  $schedule
     * @return array<int, array<string, mixed>>
     */
    private function captionClips(array $schedule, string $captionStyle): array
    {
        $style = $this->captionStyleForShotstack($captionStyle);

        return array_values(array_map(function (array $item) use ($style): array {
            $position = $this->safeCaptionPosition($item);

            return [
                'asset' => [
                    'type' => 'html',
                    'html' => $this->captionHtml($item['text'], $item['type']),
                    'css' => $this->captionCss($style),
                    'width' => self::CAPTION_SAFE_WIDTH,
                    'height' => self::CAPTION_SAFE_HEIGHT,
                ],
                'start' => (float) $item['start'],
                'length' => (float) $item['length'],
                'position' => $position['position'],
                'offset' => $position['offset'],
            ];
        }, $schedule));
    }

    /** @return array{color: string, background: string, font_size: int, font_weight: int, shadow: string} */
    private function captionStyleForShotstack(string $style): array
    {
        return match ($style) {
            'bold_hook' => ['color' => '#ffffff', 'background' => 'rgba(15, 23, 42, 0.54)', 'font_size' => 34, 'font_weight' => 750, 'shadow' => '0 3px 10px rgba(0,0,0,0.38)'],
            'minimal' => ['color' => '#ffffff', 'background' => 'rgba(0, 0, 0, 0)', 'font_size' => 30, 'font_weight' => 650, 'shadow' => '0 3px 12px rgba(0,0,0,0.68)'],
            'premium_clean' => ['color' => '#f8fafc', 'background' => 'rgba(15, 23, 42, 0.46)', 'font_size' => 31, 'font_weight' => 650, 'shadow' => '0 3px 10px rgba(0,0,0,0.36)'],
            'ugc_native' => ['color' => '#ffffff', 'background' => 'rgba(17, 24, 39, 0.52)', 'font_size' => 33, 'font_weight' => 720, 'shadow' => '0 3px 10px rgba(0,0,0,0.40)'],
            default => ['color' => '#ffffff', 'background' => 'rgba(17, 24, 39, 0.50)', 'font_size' => 31, 'font_weight' => 650, 'shadow' => '0 3px 10px rgba(0,0,0,0.38)'],
        };
    }

    /** @param array{color: string, background: string, font_size: int, font_weight: int, shadow: string} $style */
    private function captionCss(array $style): string
    {
        return 'html,body{margin:0;width:100%;height:100%;overflow:hidden;}'
            .'body{display:flex;align-items:center;justify-content:center;font-family:Inter,Arial,sans-serif;text-align:center;}'
            .'.caption{box-sizing:border-box;display:inline-block;max-width:460px;max-height:100%;padding:10px 16px;border-radius:16px;'
            .'text-align:center;white-space:normal;overflow:hidden;letter-spacing:0;text-transform:none;'
            .'line-height:1.12;color:'.$style['color'].';background:'.$style['background'].';'
            .'font-size:'.$style['font_size'].'px;font-weight:'.$style['font_weight'].';'
            .'text-shadow:'.$style['shadow'].';box-shadow:0 8px 24px rgba(0,0,0,0.16);}';
    }

    private function captionHtml(string $text, string $type): string
    {
        $lines = array_map(
            fn (string $line): string => htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            explode("\n", $text),
        );
        $class = $type === 'cta' ? 'caption caption-cta' : 'caption';

        return '<div class="'.$class.'">'.implode('<br>', $lines).'</div>';
    }

    /** @return array{position: string, offset: array{x: float, y: float}} */
    private function safeCaptionPosition(array $item): array
    {
        return [
            'position' => 'bottom',
            'offset' => [
                'x' => 0.0,
                'y' => ($item['type'] ?? null) === 'cta' ? 0.18 : 0.22,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function clipSequence(array $items): array
    {
        return array_map(fn (array $item): array => [
            'clip' => 'Clip '.$item['order'],
            'source' => $item['source'] === 'cta_end_card' ? 'held product frame' : 'source video',
            'timestamp' => $item['timestamp'],
            'use' => $item['instruction'],
            'text_overlay' => $item['text_overlay'],
        ], $items);
    }

    /** @param array<string, mixed> $brief */
    private function requestedTargetDuration(array $input, array $brief): int
    {
        $value = $brief['target_duration_seconds']
            ?? $input['requested_target_duration_seconds']
            ?? $input['target_duration_seconds']
            ?? 30;
        $duration = is_numeric($value) ? (int) ceil((float) $value) : 30;

        return max(8, min(60, $duration));
    }

    /** @param array<string, mixed> $brief */
    private function targetDuration(array $input, array $brief): int
    {
        $value = $brief['effective_target_duration_seconds']
            ?? $input['effective_target_duration_seconds']
            ?? $input['target_duration_seconds']
            ?? 30;
        $duration = is_numeric($value) ? (int) ceil((float) $value) : 30;

        return max(8, min(60, $duration));
    }

    /** @param array<int, array<string, mixed>> $items */
    private function totalDuration(array $items): float
    {
        return round(array_reduce(
            $items,
            fn (float $total, array $item): float => $total + (float) $item['duration_seconds'],
            0.0,
        ), 2);
    }

    private function safeCaption(mixed $value): string
    {
        $warnings = [];

        return $this->truncateCaptionForOverlay($value, $warnings);
    }

    /** @param array<int, string> $warnings */
    private function truncateCaptionForOverlay(mixed $value, array &$warnings): string
    {
        $text = $this->normalizeCaptionText($value);

        if ($text === '') {
            return '';
        }

        $lines = $this->splitCaptionIntoSafeLines($text);
        $caption = implode("\n", $lines);

        if (str_contains($caption, "\n")) {
            $warnings[] = 'Caption text was split into safe lines for the vertical safe area.';
        }

        if (mb_strlen(str_replace("\n", ' ', $caption)) < mb_strlen($text)) {
            $warnings[] = 'Long caption text was shortened to stay inside the vertical safe area.';
        }

        return $caption;
    }

    private function normalizeCaptionText(mixed $value): string
    {
        $text = $this->stringValue($value, '');
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?: '';
        $text = trim($text, " \t\n\r\0\x0B\"'.,;:|/-");

        return $text === '' ? '' : Str::limit($text, 120, '');
    }

    /** @return array<int, string> */
    private function splitCaptionIntoSafeLines(string $text): array
    {
        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if (count($lines) >= self::CAPTION_MAX_LINES) {
                break;
            }

            if (mb_strlen($word) > self::CAPTION_MAX_CHARS_PER_LINE) {
                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }

                if (count($lines) < self::CAPTION_MAX_LINES) {
                    $lines[] = mb_substr($word, 0, self::CAPTION_MAX_CHARS_PER_LINE);
                }

                continue;
            }

            $candidate = trim($current.' '.$word);

            if (mb_strlen($candidate) > self::CAPTION_MAX_CHARS_PER_LINE && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '' && count($lines) < self::CAPTION_MAX_LINES) {
            $lines[] = $current;
        }

        return array_slice($lines, 0, self::CAPTION_MAX_LINES);
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @param  array<string, mixed>  $brief
     * @return array<int, string>
     */
    private function captionAlternates(array $analysis, array $brief, string $cta): array
    {
        $values = [];

        foreach ([$analysis['captions'] ?? null, $analysis['hooks'] ?? null, data_get($analysis, 'edit_plan.captions')] as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            foreach ($candidate as $item) {
                if (is_string($item)) {
                    $values[] = $item;
                } elseif (is_array($item)) {
                    $values[] = $item['caption'] ?? $item['text'] ?? $item['on_screen_text'] ?? null;
                }
            }
        }

        $values[] = data_get($analysis, 'edit_plan.opening');
        $values[] = $brief['selected_cta'] ?? $cta;
        $values[] = $cta;
        $values[] = $this->stringValue($analysis['campaign_angle'] ?? null, 'Product highlight');

        return array_values(array_filter(array_map(
            fn (mixed $value): string => $this->safeCaption($value),
            $values,
        )));
    }

    private function captionForItem(array $item): string
    {
        return $this->safeCaption(
            $item['caption']
                ?? $item['caption_suggestion']
                ?? $item['text_overlay']
                ?? $item['on_screen_text']
                ?? $item['hook']
                ?? $item['label']
                ?? 'Product highlight',
        );
    }

    private function instructionForItem(array $item): string
    {
        return $this->stringValue(
            $item['instruction']
                ?? $item['visual_direction']
                ?? $item['visual_reason']
                ?? $item['purpose']
                ?? $item['reason']
                ?? $item['description']
                ?? $item['note']
                ?? null,
            'Use this source video moment in the beta edit.',
        );
    }

    /** @param array<string, mixed> $brief */
    private function qualityWarnings(array $brief): array
    {
        $warnings = $brief['warnings'] ?? [];

        return is_array($warnings)
            ? array_values(array_filter($warnings, fn (mixed $warning): bool => is_string($warning) && trim($warning) !== ''))
            : [];
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

    /** @param array<string, mixed> $analysis */
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
