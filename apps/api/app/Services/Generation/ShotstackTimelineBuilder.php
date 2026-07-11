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
        $sourceDuration = $this->sourceDuration($input, $brief);
        $invalidClipRangesDiscarded = 0;
        $candidateClipCount = 0;
        $uniqueCandidateClipCount = 0;
        $fallbackEvenSegmentsAdded = 0;
        $repeatedClipCount = 0;
        $repeatedClipReason = null;
        $clips = $this->clipPlan(
            $analysis,
            $targetDuration,
            $brief,
            $sourceDuration,
            $invalidClipRangesDiscarded,
            $candidateClipCount,
            $uniqueCandidateClipCount,
            $fallbackEvenSegmentsAdded,
        );
        $timelineItems = $this->timelineItems($clips, $analysis, $input, $brief, $cta);
        $warnings = $this->qualityWarnings($brief);
        $durationSyncStrategy = $voiceoverUrl === null ? 'no_voiceover_target_duration' : 'voiceover_matched';
        $voiceoverDuration = $this->numberValue($brief['voiceover_duration_seconds'] ?? null, 0.0);

        if ($voiceoverUrl !== null && $voiceoverDuration > 0) {
            $requiredDuration = max($targetDuration, $voiceoverDuration + 0.5);
            $timelineItems = $this->extendTimelineForDuration($timelineItems, $requiredDuration, $cta, $durationSyncStrategy, $warnings, $repeatedClipCount, $repeatedClipReason);
        }

        $totalDuration = $this->totalDuration($timelineItems);
        $captionStyle = AutoVideoCaptionStyle::normalize($brief['caption_style'] ?? $input['caption_style'] ?? null);
        $captionWarnings = [];
        $captionDedupeStats = [
            'deduped_caption_count' => 0,
            'repeated_captions_removed' => 0,
        ];
        $captionCandidates = $this->buildCaptionCandidates($timelineItems, $analysis, $brief, $cta, $captionDedupeStats);
        $captionSchedule = $this->selectCaptionSchedule($captionCandidates, $totalDuration, $cta, $captionWarnings);
        $captionValidationStats = [
            'captions_dropped_for_overlap' => 0,
        ];
        $captionSchedule = $this->validateCaptionSchedule($captionSchedule, $totalDuration, $captionValidationStats);
        $captionsOverlapping = $this->captionsOverlap($captionSchedule);

        if ($captionValidationStats['captions_dropped_for_overlap'] > 0) {
            $captionWarnings[] = 'One or more captions were dropped to prevent overlap.';
        }

        if ($captionsOverlapping) {
            $captionWarnings[] = 'Caption schedule had overlapping items after final validation.';
        }

        $captionWarnings = array_values(array_unique($captionWarnings));
        $captionClips = $this->captionClips($captionSchedule, $captionStyle);
        $outputMetadata = $this->outputMetadata();
        $sourceClipRangesUsed = $this->sourceClipRangesUsed($timelineItems);
        $diverseSourceSegmentsUsed = $this->diverseSourceSegmentsUsed($sourceClipRangesUsed, $repeatedClipCount);
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
            'caption_schedule_validated' => true,
            'captions_dropped_for_overlap' => $captionValidationStats['captions_dropped_for_overlap'],
            'final_caption_count' => count($captionSchedule),
            'cta_caption_start_seconds' => $this->ctaCaptionStartSeconds($captionSchedule),
            'caption_style' => $captionStyle,
            'caption_visual_strategy' => 'shadow_text_light_caption',
            'deduped_caption_count' => $captionDedupeStats['deduped_caption_count'],
            'repeated_captions_removed' => $captionDedupeStats['repeated_captions_removed'],
            'caption_safe_area_applied' => true,
            'caption_max_lines' => self::CAPTION_MAX_LINES,
            'caption_warnings' => $captionWarnings,
            'cta_used' => $cta,
            'source_clip_ranges_used' => $sourceClipRangesUsed,
            'candidate_clip_count' => $candidateClipCount,
            'unique_candidate_clip_count' => $uniqueCandidateClipCount,
            'fallback_even_segments_added' => $fallbackEvenSegmentsAdded,
            'repeated_clip_count' => $repeatedClipCount,
            'repeated_clip_reason' => $repeatedClipReason,
            'post_voiceover_visual_strategy' => $this->postVoiceoverVisualStrategy($voiceoverDuration, $totalDuration, $repeatedClipCount),
            'source_segments_used_after_voiceover' => $this->sourceSegmentsUsedAfterVoiceover($timelineItems, $voiceoverDuration),
            'diverse_source_segments_used' => $diverseSourceSegmentsUsed,
            'invalid_clip_ranges_discarded' => $invalidClipRangesDiscarded,
            'source_duration_seconds' => $sourceDuration,
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
                'caption_schedule_validated' => true,
                'captions_dropped_for_overlap' => $captionValidationStats['captions_dropped_for_overlap'],
                'final_caption_count' => count($captionSchedule),
                'cta_caption_start_seconds' => $this->ctaCaptionStartSeconds($captionSchedule),
                'caption_visual_strategy' => 'shadow_text_light_caption',
                'deduped_caption_count' => $captionDedupeStats['deduped_caption_count'],
                'repeated_captions_removed' => $captionDedupeStats['repeated_captions_removed'],
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
                'caption_schedule_validated' => true,
                'captions_dropped_for_overlap' => $captionValidationStats['captions_dropped_for_overlap'],
                'final_caption_count' => count($captionSchedule),
                'cta_caption_start_seconds' => $this->ctaCaptionStartSeconds($captionSchedule),
                'caption_visual_strategy' => 'shadow_text_light_caption',
                'deduped_caption_count' => $captionDedupeStats['deduped_caption_count'],
                'repeated_captions_removed' => $captionDedupeStats['repeated_captions_removed'],
                'source_clip_ranges_used' => $sourceClipRangesUsed,
                'candidate_clip_count' => $candidateClipCount,
                'unique_candidate_clip_count' => $uniqueCandidateClipCount,
                'fallback_even_segments_added' => $fallbackEvenSegmentsAdded,
                'repeated_clip_count' => $repeatedClipCount,
                'repeated_clip_reason' => $repeatedClipReason,
                'post_voiceover_visual_strategy' => $this->postVoiceoverVisualStrategy($voiceoverDuration, $totalDuration, $repeatedClipCount),
                'source_segments_used_after_voiceover' => $this->sourceSegmentsUsedAfterVoiceover($timelineItems, $voiceoverDuration),
                'diverse_source_segments_used' => $diverseSourceSegmentsUsed,
                'invalid_clip_ranges_discarded' => $invalidClipRangesDiscarded,
                'source_duration_seconds' => $sourceDuration,
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
    private function clipPlan(
        array $analysis,
        int $targetDuration,
        array $brief,
        ?float $sourceDuration,
        int &$invalidDiscarded,
        int &$candidateClipCount,
        int &$uniqueCandidateClipCount,
        int &$fallbackEvenSegmentsAdded,
    ): array {
        $groups = [
            ['items' => $brief['preferred_cuts'] ?? null, 'source' => 'creative_brief.preferred_cuts'],
            ['items' => data_get($analysis, 'edit_plan.timeline'), 'source' => 'edit_plan.timeline'],
            ['items' => $brief['fallback_cuts'] ?? null, 'source' => 'creative_brief.fallback_cuts'],
            ['items' => $analysis['recommended_cuts'] ?? null, 'source' => 'recommended_cuts'],
            ['items' => $analysis['detected_product_moments'] ?? null, 'source' => 'detected_product_moments'],
            ['items' => $analysis['scene_observations'] ?? null, 'source' => 'scene_observations'],
        ];
        $candidates = [];

        foreach ($groups as $group) {
            if (! is_array($group['items']) || $group['items'] === []) {
                continue;
            }

            $candidates = array_merge(
                $candidates,
                $this->normalizeCuts($group['items'], (string) $group['source'], $sourceDuration, $invalidDiscarded),
            );
        }

        if ($sourceDuration !== null && $sourceDuration >= 12) {
            $needsMoreCoverage = count($this->uniqueCandidateRanges($candidates)) < ($sourceDuration > 30 ? 5 : 3)
                || $this->candidateDuration($candidates) < min($targetDuration, $sourceDuration);

            if ($needsMoreCoverage) {
                $fallbackSegments = $this->fallbackEvenSegments($sourceDuration, $targetDuration, $candidates);
                $fallbackEvenSegmentsAdded = count($fallbackSegments);
                $candidates = array_merge($candidates, $fallbackSegments);
            }
        }

        $candidateClipCount = count($candidates);
        $uniqueCandidateClipCount = count($this->uniqueCandidateRanges($candidates));

        if ($candidates === []) {
            $fallback = $this->fallbackClip($targetDuration, 'fallback_first_seconds', $sourceDuration);
            $candidateClipCount = 1;
            $uniqueCandidateClipCount = 1;

            return [$fallback];
        }

        return $this->selectDiverseCuts($candidates, $targetDuration, $sourceDuration);
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCuts(array $items, string $source, ?float $sourceDuration, int &$invalidDiscarded): array
    {
        $cuts = [];
        $cursor = 0.0;

        foreach ($items as $item) {
            if (! is_array($item) && ! is_string($item)) {
                continue;
            }

            $normalized = is_array($item)
                ? $this->cutFromArray($item, $cursor, $source, $sourceDuration, $invalidDiscarded)
                : $this->cutFromString($item, $cursor, $source, $sourceDuration, $invalidDiscarded);

            if ($normalized === null) {
                continue;
            }

            $cuts[] = $normalized;
            $cursor = (float) $normalized['source_end_seconds'];
        }

        return $cuts;
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    private function selectDiverseCuts(array $candidates, int $targetDuration, ?float $sourceDuration): array
    {
        usort($candidates, fn (array $a, array $b): int => $this->clipScore($b, $sourceDuration) <=> $this->clipScore($a, $sourceDuration));

        $selected = [];
        $total = 0.0;
        $maxClips = $sourceDuration !== null && $sourceDuration > 30 ? 6 : 5;

        foreach ($candidates as $candidate) {
            if (count($selected) >= $maxClips) {
                break;
            }

            if ($this->isNearDuplicateSourceRange($candidate, $selected)) {
                continue;
            }

            $selected[] = $candidate;
            $total += (float) $candidate['duration_seconds'];

            if ($total >= $targetDuration) {
                break;
            }
        }

        if ($total < min($targetDuration, 12)) {
            foreach ($candidates as $candidate) {
                if (count($selected) >= $maxClips) {
                    break;
                }

                if (in_array($candidate, $selected, true)) {
                    continue;
                }

                $selected[] = $candidate;
                $total += (float) $candidate['duration_seconds'];

                if ($total >= $targetDuration) {
                    break;
                }
            }
        }

        if ($selected === []) {
            return [$this->fallbackClip($targetDuration, 'fallback_first_seconds', $sourceDuration)];
        }

        usort($selected, fn (array $a, array $b): int => ((float) $a['source_start_seconds']) <=> ((float) $b['source_start_seconds']));

        return $selected;
    }

    /** @return array<string, mixed> */
    private function fallbackClip(int $targetDuration, string $source, ?float $sourceDuration = null): array
    {
        $duration = (float) min(max(8, $targetDuration), 15);

        if ($sourceDuration !== null) {
            $duration = min($duration, max(0.5, $sourceDuration));
        }

        return [
            'source_start_seconds' => 0.0,
            'source_end_seconds' => round($duration, 2),
            'duration_seconds' => round($duration, 2),
            'text_overlay' => 'Start with the clearest product shot',
            'instruction' => 'Use the opening product footage as a simple beta render.',
            'source' => $source,
            'priority' => 'medium',
            'visual_quality' => null,
            'stability' => null,
            'product_visibility' => null,
            'is_transition_moment' => false,
            'use_for_final_edit' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function cutFromArray(array $item, float $cursor, string $source, ?float $sourceDuration, int &$invalidDiscarded): ?array
    {
        $range = $this->timeRange($item, $cursor);

        if ($range === null) {
            $invalidDiscarded++;

            return null;
        }

        [$start, $end] = $range;
        $start = max(0.0, $start);

        if ($sourceDuration !== null) {
            if ($start >= $sourceDuration) {
                $invalidDiscarded++;

                return null;
            }

            $end = min($end, $sourceDuration);
        }

        if ($end <= $start) {
            $invalidDiscarded++;

            return null;
        }

        $duration = min(8.0, $end - $start);
        $end = $start + $duration;

        if ($duration < 0.5 || $end <= $start) {
            $invalidDiscarded++;

            return null;
        }

        return [
            'source_start_seconds' => round($start, 2),
            'source_end_seconds' => round($end, 2),
            'duration_seconds' => round($duration, 2),
            'text_overlay' => $this->captionForItem($item),
            'instruction' => $this->instructionForItem($item),
            'source' => $source,
            'priority' => $this->stringValue($item['priority'] ?? null, 'medium'),
            'visual_quality' => $this->nullableString($item['visual_quality'] ?? null),
            'stability' => $this->nullableString($item['stability'] ?? null),
            'product_visibility' => $this->nullableString($item['product_visibility'] ?? null),
            'is_transition_moment' => $this->booleanValue($item['is_transition_moment'] ?? null),
            'use_for_final_edit' => $this->booleanValue($item['use_for_final_edit'] ?? $item['usable_for_edit'] ?? null),
        ];
    }

    /** @return array<string, mixed>|null */
    private function cutFromString(string $item, float $cursor, string $source, ?float $sourceDuration, int &$invalidDiscarded): ?array
    {
        $text = trim($item);

        if ($text === '') {
            return null;
        }

        $range = $this->parseTimestamp($text, $cursor);

        if ($range === null) {
            $invalidDiscarded++;

            return null;
        }

        [$start, $end] = $range;
        $start = max(0.0, $start);

        if ($sourceDuration !== null) {
            if ($start >= $sourceDuration) {
                $invalidDiscarded++;

                return null;
            }

            $end = min($end, $sourceDuration);
        }

        if ($end <= $start) {
            $invalidDiscarded++;

            return null;
        }

        $duration = min(8.0, $end - $start);
        $end = $start + $duration;

        if ($duration < 0.5) {
            $invalidDiscarded++;

            return null;
        }

        return [
            'source_start_seconds' => round($start, 2),
            'source_end_seconds' => round($end, 2),
            'duration_seconds' => round($duration, 2),
            'text_overlay' => $this->safeCaption(preg_replace('/^\s*-?\d+(?:\.\d+)?\s*(?:-|to|–)\s*-?\d+(?:\.\d+)?s?\s*[:\-]?\s*/i', '', $text) ?: $text),
            'instruction' => $text,
            'source' => $source,
            'priority' => 'medium',
            'visual_quality' => null,
            'stability' => null,
            'product_visibility' => null,
            'is_transition_moment' => false,
            'use_for_final_edit' => true,
        ];
    }

    /** @return array{0: float, 1: float}|null */
    private function timeRange(array $item, float $cursor): ?array
    {
        $startValue = $item['source_start_seconds'] ?? $item['start_seconds'] ?? $item['start'] ?? data_get($item, 'source.start_seconds');
        $endValue = $item['source_end_seconds'] ?? $item['end_seconds'] ?? $item['end'] ?? data_get($item, 'source.end_seconds');
        $hasExplicitRange = is_numeric($startValue) || is_numeric($endValue);
        $start = $this->numberValue($startValue, -1);
        $end = $this->numberValue($endValue, -1);

        if ($hasExplicitRange) {
            return $start >= 0 && $end > $start ? [$start, $end] : null;
        }

        $timestamp = $item['timestamp'] ?? $item['time'] ?? $item['range'] ?? null;

        if (is_string($timestamp)) {
            return $this->parseTimestamp($timestamp, $cursor);
        }

        $duration = $this->numberValue($item['duration_seconds'] ?? $item['length'] ?? null, 3.0);

        if ($duration <= 0) {
            return null;
        }

        return [$cursor, $cursor + $duration];
    }

    /** @return array{0: float, 1: float}|null */
    private function parseTimestamp(string $value, float $fallbackStart): ?array
    {
        if (preg_match('/(-?\d+(?:\.\d+)?)\s*s?\s*(?:-|to|–)\s*(-?\d+(?:\.\d+)?)\s*s?/i', $value, $matches) === 1) {
            $start = (float) $matches[1];
            $end = (float) $matches[2];

            return $end > $start ? [max(0.0, $start), $end] : null;
        }

        if (preg_match('/(-?\d+(?:\.\d+)?)\s*s/i', $value, $matches) === 1) {
            $start = (float) $matches[1];

            return $start >= 0 ? [$start, $start + 3.0] : null;
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
                'timeline_range' => $this->timestamp($start, $start + $duration),
                'source_range' => $this->timestamp((float) $clip['source_start_seconds'], (float) $clip['source_end_seconds']),
                'start_seconds' => round($start, 2),
                'timeline_start_seconds' => round($start, 2),
                'timeline_end_seconds' => round($start + $duration, 2),
                'duration_seconds' => round($duration, 2),
                'source_start_seconds' => $clip['source_start_seconds'],
                'source_end_seconds' => $clip['source_end_seconds'],
                'layer' => $index === $lastIndex ? 'cta' : 'video',
                'instruction' => $index === 0
                    ? 'Open with the clearest product-focused hook shot.'
                    : ($clip['instruction'] ?? 'Use this source video moment.'),
                'text_overlay' => $this->safeCaption($text),
                'source' => $clip['source'],
                'priority' => $clip['priority'] ?? null,
                'visual_quality' => $clip['visual_quality'] ?? null,
                'stability' => $clip['stability'] ?? null,
                'product_visibility' => $clip['product_visibility'] ?? null,
                'is_transition_moment' => $clip['is_transition_moment'] ?? null,
                'use_for_final_edit' => $clip['use_for_final_edit'] ?? null,
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
    private function extendTimelineForDuration(array $items, float $requiredDuration, string $cta, string &$strategy, array &$warnings, int &$repeatedClipCount, ?string &$repeatedClipReason): array
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
            $sourceStart = (float) ($best['source_start_seconds'] ?? 0.0);
            $sourceEnd = (float) ($best['source_end_seconds'] ?? ($sourceStart + max(0.5, $duration)));
            $items[] = [
                'order' => count($items) + 1,
                'timestamp' => $this->timestamp($total, $total + $duration),
                'timeline_range' => $this->timestamp($total, $total + $duration),
                'source_range' => $this->timestamp($sourceStart, $sourceEnd),
                'start_seconds' => round($total, 2),
                'timeline_start_seconds' => round($total, 2),
                'timeline_end_seconds' => round($total + $duration, 2),
                'duration_seconds' => round($duration, 2),
                'source_start_seconds' => $sourceStart,
                'source_end_seconds' => $sourceEnd,
                'layer' => $isFinal ? 'cta' : 'video',
                'instruction' => $isFinal
                    ? 'Hold a stable product frame and show the final call to action while the voiceover finishes.'
                    : 'Repeat the strongest product moment to keep video visible while the voiceover continues.',
                'text_overlay' => $this->safeCaption($isFinal ? $cta : ($best['text_overlay'] ?? 'Product detail')),
                'source' => $isFinal ? 'cta_end_card' : 'repeated_product_clip',
            ];
            if (! $isFinal) {
                $repeatedClipCount++;
                $repeatedClipReason ??= 'Voiceover or target duration was longer than the distinct usable source clips.';
            }
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
    private function buildCaptionCandidates(array $items, array $analysis, array $brief, string $cta, array &$stats): array
    {
        $candidates = [];
        $seen = [];
        $ctaKey = mb_strtolower($this->normalizeCaptionText($cta));
        $push = function (mixed $value, string $type, ?float $preferredStart = null) use (&$candidates, &$seen, &$stats, $ctaKey): void {
            $text = $this->normalizeCaptionText($value);

            if ($text === '') {
                return;
            }

            $key = mb_strtolower($text);

            if ($type !== 'cta' && $ctaKey !== '' && $key === $ctaKey) {
                $stats['repeated_captions_removed']++;

                return;
            }

            if (isset($seen[$key])) {
                $stats['repeated_captions_removed']++;

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

        $stats['deduped_caption_count'] = count($candidates);

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
            'bold_hook' => ['color' => '#ffffff', 'background' => 'rgba(0, 0, 0, 0)', 'font_size' => 34, 'font_weight' => 760, 'shadow' => '0 3px 14px rgba(0,0,0,0.82)'],
            'minimal' => ['color' => '#ffffff', 'background' => 'rgba(0, 0, 0, 0)', 'font_size' => 30, 'font_weight' => 620, 'shadow' => '0 3px 14px rgba(0,0,0,0.74)'],
            'premium_clean' => ['color' => '#f8fafc', 'background' => 'rgba(0, 0, 0, 0)', 'font_size' => 31, 'font_weight' => 660, 'shadow' => '0 3px 14px rgba(0,0,0,0.76)'],
            'ugc_native' => ['color' => '#ffffff', 'background' => 'rgba(0, 0, 0, 0)', 'font_size' => 33, 'font_weight' => 720, 'shadow' => '0 3px 14px rgba(0,0,0,0.80)'],
            default => ['color' => '#ffffff', 'background' => 'rgba(0, 0, 0, 0)', 'font_size' => 31, 'font_weight' => 660, 'shadow' => '0 3px 14px rgba(0,0,0,0.76)'],
        };
    }

    /** @param array{color: string, background: string, font_size: int, font_weight: int, shadow: string} $style */
    private function captionCss(array $style): string
    {
        return 'html,body{margin:0;width:100%;height:100%;overflow:hidden;}'
            .'body{display:flex;align-items:center;justify-content:center;font-family:Inter,Arial,sans-serif;text-align:center;}'
            .'.caption{box-sizing:border-box;display:inline-block;max-width:430px;max-height:100%;padding:6px 10px;border-radius:12px;'
            .'text-align:center;white-space:normal;overflow:hidden;letter-spacing:0;text-transform:none;'
            .'line-height:1.12;color:'.$style['color'].';background:'.$style['background'].';'
            .'font-size:'.$style['font_size'].'px;font-weight:'.$style['font_weight'].';'
            .'text-shadow:'.$style['shadow'].';box-shadow:none;}'
            .'.caption-cta{font-weight:760;text-shadow:0 4px 16px rgba(0,0,0,0.84);}';
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
            'timeline_range' => $item['timeline_range'] ?? $item['timestamp'],
            'source_range' => $item['source_range'] ?? null,
            'timeline_start_seconds' => $item['timeline_start_seconds'] ?? $item['start_seconds'] ?? null,
            'timeline_end_seconds' => $item['timeline_end_seconds'] ?? null,
            'source_start_seconds' => $item['source_start_seconds'] ?? null,
            'source_end_seconds' => $item['source_end_seconds'] ?? null,
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

    /** @param array<string, mixed> $brief */
    private function sourceDuration(array $input, array $brief): ?float
    {
        foreach ([
            $brief['source_duration_seconds'] ?? null,
            $input['source_video_duration_seconds'] ?? null,
            data_get($input, 'source_video_asset.metadata.duration_seconds'),
            data_get($input, 'source_video_asset.metadata.duration'),
            data_get($input, 'source_video_asset.metadata.video.duration_seconds'),
            data_get($input, 'source_video_asset.metadata.video.duration'),
        ] as $value) {
            if (is_numeric($value) && (float) $value > 0) {
                return round((float) $value, 2);
            }
        }

        return null;
    }

    /** @param array<string, mixed> $clip */
    private function clipScore(array $clip, ?float $sourceDuration): int
    {
        $score = 0;

        if (($clip['use_for_final_edit'] ?? null) === false) {
            $score -= 80;
        } else {
            $score += 20;
        }

        $score += match (strtolower((string) ($clip['priority'] ?? 'medium'))) {
            'high' => 30,
            'low' => -5,
            default => 15,
        };
        $score += $this->qualityScore($clip['product_visibility'] ?? null, 30, 14, -24);
        $score += $this->qualityScore($clip['stability'] ?? null, 22, 10, -18);
        $score += $this->qualityScore($clip['visual_quality'] ?? null, 16, 8, -10);

        if (($clip['is_transition_moment'] ?? null) === true) {
            $score -= 35;
        }

        $duration = (float) ($clip['duration_seconds'] ?? 0.0);
        if ($duration < 2.0) {
            $score -= 20;
        }

        if ($sourceDuration !== null && $sourceDuration > 0) {
            $position = ((float) ($clip['source_start_seconds'] ?? 0.0)) / $sourceDuration;
            $score += (int) round(min(12, max(0, $position * 12)));
        }

        return $score;
    }

    private function qualityScore(mixed $value, int $high, int $medium, int $low): int
    {
        return match (strtolower((string) $value)) {
            'high' => $high,
            'medium' => $medium,
            'low' => $low,
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<int, array<string, mixed>>  $selected
     */
    private function isNearDuplicateSourceRange(array $candidate, array $selected): bool
    {
        foreach ($selected as $existing) {
            $startA = (float) ($candidate['source_start_seconds'] ?? 0.0);
            $endA = (float) ($candidate['source_end_seconds'] ?? $startA);
            $startB = (float) ($existing['source_start_seconds'] ?? 0.0);
            $endB = (float) ($existing['source_end_seconds'] ?? $startB);
            $durationA = max(0.1, $endA - $startA);
            $durationB = max(0.1, $endB - $startB);
            $overlap = max(0.0, min($endA, $endB) - max($startA, $startB));

            if (abs($startA - $startB) < 1.0 || ($overlap / min($durationA, $durationB)) > 0.6) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, string>
     */
    private function sourceClipRangesUsed(array $items): array
    {
        $ranges = [];

        foreach ($items as $item) {
            if (($item['source'] ?? null) === 'cta_end_card') {
                continue;
            }

            $start = $this->numberValue($item['source_start_seconds'] ?? null, -1);
            $end = $this->numberValue($item['source_end_seconds'] ?? null, -1);

            if ($start >= 0 && $end > $start) {
                $ranges[] = $this->timestamp($start, $end);
            }
        }

        return array_values(array_unique($ranges));
    }

    /** @param array<int, string> $ranges */
    private function diverseSourceSegmentsUsed(array $ranges, int $repeatedClipCount): bool
    {
        return count($ranges) >= 3 && $repeatedClipCount === 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function candidateDuration(array $candidates): float
    {
        return array_reduce(
            $candidates,
            fn (float $total, array $candidate): float => $total + max(0.0, (float) ($candidate['duration_seconds'] ?? 0.0)),
            0.0,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, string>
     */
    private function uniqueCandidateRanges(array $candidates): array
    {
        $unique = [];
        $selected = [];

        foreach ($candidates as $candidate) {
            if ($this->isNearDuplicateSourceRange($candidate, $selected)) {
                continue;
            }

            $selected[] = $candidate;
            $start = $this->numberValue($candidate['source_start_seconds'] ?? null, -1);
            $end = $this->numberValue($candidate['source_end_seconds'] ?? null, -1);

            if ($start >= 0 && $end > $start) {
                $unique[] = $this->timestamp($start, $end);
            }
        }

        return array_values(array_unique($unique));
    }

    /**
     * @param  array<int, array<string, mixed>>  $existing
     * @return array<int, array<string, mixed>>
     */
    private function fallbackEvenSegments(float $sourceDuration, int $targetDuration, array $existing): array
    {
        $segments = [];
        $segmentCount = $sourceDuration > 30 ? 6 : 4;
        $segmentLength = $sourceDuration > 30
            ? min(6.0, max(5.0, $targetDuration / max(4, $segmentCount - 1)))
            : min(6.0, max(3.0, $targetDuration / max(4, $segmentCount)));
        $lastStart = max(0.0, $sourceDuration - $segmentLength);

        for ($index = 0; $index < $segmentCount; $index++) {
            $ratio = $segmentCount === 1 ? 0.0 : $index / ($segmentCount - 1);
            $start = round($lastStart * $ratio, 2);
            $end = round(min($sourceDuration, $start + $segmentLength), 2);

            if ($end - $start < 2.0) {
                continue;
            }

            $candidate = [
                'source_start_seconds' => $start,
                'source_end_seconds' => $end,
                'duration_seconds' => round($end - $start, 2),
                'text_overlay' => $index === $segmentCount - 1 ? 'Final product showcase' : 'Product detail',
                'instruction' => $index === $segmentCount - 1
                    ? 'Use a later stable product shot as a final visual showcase.'
                    : 'Use an evenly sampled product-focused source segment when Gemini did not provide enough precise cuts.',
                'source' => 'fallback_even_source_segment',
                'priority' => $index === $segmentCount - 1 ? 'high' : 'medium',
                'visual_quality' => 'medium',
                'stability' => 'medium',
                'product_visibility' => 'medium',
                'is_transition_moment' => false,
                'use_for_final_edit' => true,
            ];

            if (! $this->isNearDuplicateSourceRange($candidate, array_merge($existing, $segments))) {
                $segments[] = $candidate;
            }
        }

        return $segments;
    }

    /**
     * @param  array<int, array{start: float, length: float, text: string, type: string}>  $schedule
     * @param  array{captions_dropped_for_overlap: int}  $stats
     * @return array<int, array{start: float, length: float, text: string, type: string}>
     */
    private function validateCaptionSchedule(array $schedule, float $finalDuration, array &$stats): array
    {
        $finalDuration = max(1.0, $finalDuration);
        $gap = 0.25;
        $ctaItems = array_values(array_filter($schedule, fn (array $item): bool => ($item['type'] ?? null) === 'cta'));
        $cta = $ctaItems !== [] ? $ctaItems[count($ctaItems) - 1] : null;
        $normal = array_values(array_filter($schedule, fn (array $item): bool => ($item['type'] ?? null) !== 'cta'));
        usort($normal, fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        $ctaLength = $cta !== null
            ? round(min(3.0, max(2.0, (float) ($cta['length'] ?? 2.4))), 2)
            : 0.0;
        $ctaStart = $cta !== null
            ? round(max(0.4, $finalDuration - $ctaLength - 0.4), 2)
            : $finalDuration;
        $validated = [];
        $previousEnd = 0.0;

        foreach ($normal as $item) {
            $length = round(min(3.0, max(1.5, (float) ($item['length'] ?? 2.2))), 2);
            $latestStart = max(0.4, $ctaStart - $gap - $length);
            $start = round(max(0.4, min((float) ($item['start'] ?? 0.4), $latestStart)), 2);

            if ($validated !== [] && $start < $previousEnd + $gap) {
                $start = round($previousEnd + $gap, 2);
            }

            if ($start + $length > $ctaStart - $gap) {
                $stats['captions_dropped_for_overlap']++;

                continue;
            }

            $validated[] = [
                'start' => $start,
                'length' => $length,
                'text' => (string) $item['text'],
                'type' => (string) $item['type'],
            ];
            $previousEnd = $start + $length;
        }

        if ($cta !== null) {
            while ($validated !== [] && (($validated[count($validated) - 1]['start'] + $validated[count($validated) - 1]['length']) > $ctaStart - $gap)) {
                array_pop($validated);
                $stats['captions_dropped_for_overlap']++;
            }

            $validated[] = [
                'start' => $ctaStart,
                'length' => $ctaLength,
                'text' => (string) $cta['text'],
                'type' => 'cta',
            ];
        }

        return array_values($validated);
    }

    /** @param array<int, array{start: float, length: float, text: string, type: string}> $schedule */
    private function ctaCaptionStartSeconds(array $schedule): ?float
    {
        foreach ($schedule as $item) {
            if (($item['type'] ?? null) === 'cta') {
                return (float) $item['start'];
            }
        }

        return null;
    }

    private function postVoiceoverVisualStrategy(float $voiceoverDuration, float $totalDuration, int $repeatedClipCount): ?string
    {
        if ($voiceoverDuration <= 0 || $totalDuration <= $voiceoverDuration + 0.5) {
            return null;
        }

        return $repeatedClipCount > 0
            ? 'showcase_with_repeated_fallback'
            : 'diverse_product_showcase_and_cta';
    }

    /** @param array<int, array<string, mixed>> $items */
    private function sourceSegmentsUsedAfterVoiceover(array $items, float $voiceoverDuration): int
    {
        if ($voiceoverDuration <= 0) {
            return 0;
        }

        $ranges = [];

        foreach ($items as $item) {
            if (($item['source'] ?? null) === 'cta_end_card') {
                continue;
            }

            $timelineEnd = $this->numberValue($item['timeline_end_seconds'] ?? null, $this->numberValue($item['start_seconds'] ?? null, 0.0) + $this->numberValue($item['duration_seconds'] ?? null, 0.0));

            if ($timelineEnd <= $voiceoverDuration) {
                continue;
            }

            $start = $this->numberValue($item['source_start_seconds'] ?? null, -1);
            $end = $this->numberValue($item['source_end_seconds'] ?? null, -1);

            if ($start >= 0 && $end > $start) {
                $ranges[] = $this->timestamp($start, $end);
            }
        }

        return count(array_unique($ranges));
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function booleanValue(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
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
