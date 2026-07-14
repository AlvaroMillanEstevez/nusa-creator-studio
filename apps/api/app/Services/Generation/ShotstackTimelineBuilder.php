<?php

namespace App\Services\Generation;

use App\Support\AutoVideoCaptionStyle;
use App\Support\AutoVideoEditSettings;
use Illuminate\Support\Str;
use InvalidArgumentException;

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
        $hasVoiceover = $voiceoverUrl !== null;
        $sourceAudioMode = AutoVideoEditSettings::sourceAudioMode($brief['source_audio_mode'] ?? $input['source_audio_mode'] ?? null, $hasVoiceover);
        $voiceoverMix = AutoVideoEditSettings::voiceoverMix($brief['voiceover_mix'] ?? $input['voiceover_mix'] ?? null);
        $editingStyle = $this->stringValue($brief['editing_style'] ?? $input['editing_style'] ?? null, 'ugc_natural');
        $transitionStyle = AutoVideoEditSettings::transitionStyle($brief['transition_style'] ?? $input['transition_style'] ?? null, $editingStyle);
        $motionPolish = AutoVideoEditSettings::motionPolish($brief['motion_polish'] ?? $input['motion_polish'] ?? null);
        $clipDiversity = AutoVideoEditSettings::clipDiversity($brief['clip_diversity'] ?? $input['clip_diversity'] ?? null);
        $endScreen = AutoVideoEditSettings::endScreen($brief['end_screen'] ?? $input['end_screen'] ?? null, $cta !== '');
        $editMode = $this->stringValue($brief['edit_mode'] ?? $input['edit_mode'] ?? null, 'manual_edit');
        $overlayStyle = AutoVideoEditSettings::overlayStyle($brief['overlay_style'] ?? $input['overlay_style'] ?? null, $editMode, $this->hasProductMoments($analysis, $brief));
        $aiVideoAnalysisUsed = (bool) ($brief['ai_video_analysis_used'] ?? $input['ai_video_analysis_used'] ?? $editMode === 'ai_video_analysis');
        $fallbackTimeline = (bool) ($brief['fallback_timeline'] ?? $input['fallback_timeline'] ?? $editMode === 'manual_edit');
        $audioMix = AutoVideoEditSettings::audioMix($sourceAudioMode, $voiceoverMix, $hasVoiceover);
        $editSettings = [
            'source_audio_mode' => $sourceAudioMode,
            'voiceover_mix' => $voiceoverMix,
            'transition_style' => $transitionStyle,
            'motion_polish' => $motionPolish,
            'clip_diversity' => $clipDiversity,
            'end_screen' => $endScreen,
            'overlay_style' => $overlayStyle,
            'edit_mode' => $editMode,
            'ai_video_analysis_used' => $aiVideoAnalysisUsed,
            'fallback_timeline' => $fallbackTimeline,
        ] + $audioMix;
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
            $editSettings,
        );
        $timelineItems = $this->timelineItems($clips, $analysis, $input, $brief, $cta, $editSettings);
        $warnings = $this->qualityWarnings($brief);
        $durationSyncStrategy = $voiceoverUrl === null ? 'no_voiceover_target_duration' : 'voiceover_matched';
        $voiceoverDuration = $this->numberValue($brief['voiceover_duration_seconds'] ?? null, 0.0);

        if ($voiceoverUrl !== null && $voiceoverDuration > 0) {
            $requiredDuration = max($targetDuration, $voiceoverDuration + 0.5);
            $timelineItems = $this->extendTimelineForDuration($timelineItems, $requiredDuration, $cta, $durationSyncStrategy, $warnings, $repeatedClipCount, $repeatedClipReason, $editSettings);
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
        $captionClips = $this->captionClips($captionSchedule, $captionStyle, $overlayStyle);
        $decorativeOverlayPlan = $this->buildOverlayClips($timelineItems, $analysis, $brief, $overlayStyle, $captionSchedule, $totalDuration);
        $endScreenPlan = $this->buildEndScreenClips($timelineItems, $cta, $overlayStyle, $endScreen, $totalDuration);
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
            'caption_visual_strategy' => $this->captionVisualStrategy($overlayStyle),
            'deduped_caption_count' => $captionDedupeStats['deduped_caption_count'],
            'repeated_captions_removed' => $captionDedupeStats['repeated_captions_removed'],
            'caption_safe_area_applied' => true,
            'caption_max_lines' => self::CAPTION_MAX_LINES,
            'caption_warnings' => $captionWarnings,
            'overlay_style' => $overlayStyle,
            'overlays_count' => $decorativeOverlayPlan['overlays_count'] + $endScreenPlan['overlays_count'],
            'decorative_overlays_count' => $decorativeOverlayPlan['overlays_count'],
            'overlays_used' => array_values(array_merge($decorativeOverlayPlan['overlays_used'], $endScreenPlan['overlays_used'])),
            'compliance_sanitized_claims' => $decorativeOverlayPlan['compliance_sanitized_claims'],
            'end_screen_type' => $endScreenPlan['end_screen_type'],
            'end_screen_duration' => $endScreenPlan['end_screen_duration'],
            'end_screen_cta' => $endScreenPlan['end_screen_cta'],
            'end_screen_source' => $endScreenPlan['end_screen_source'],
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
            'source_audio_mode' => $sourceAudioMode,
            'source_audio_volume' => $audioMix['source_audio_volume'],
            'voiceover_mix' => $voiceoverMix,
            'voiceover_volume' => $audioMix['voiceover_volume'],
            'audio_cleanup_strategy' => $audioMix['audio_cleanup_strategy'],
            'original_audio_muted' => $audioMix['original_audio_muted'],
            'transition_style' => $transitionStyle,
            'motion_polish' => $motionPolish,
            'clip_diversity' => $clipDiversity,
            'end_screen' => $endScreen,
            'overlay_style' => $overlayStyle,
            'edit_mode' => $editMode,
            'ai_video_analysis_used' => $aiVideoAnalysisUsed,
            'fallback_timeline' => $fallbackTimeline,
            'source' => $this->timelineSource($analysis),
            'timeline_quality_warnings' => array_values(array_unique(array_merge($warnings, $captionWarnings))),
        ] + $outputMetadata;

        $videoClips = $this->buildVideoClips($sourceVideoUrl, $timelineItems, $audioMix, $editSettings);
        $audioClips = $this->buildAudioClips($voiceoverUrl, $audioMix, $totalDuration);
        $tracks = $this->buildTracks(
            videoClips: $videoClips,
            audioClips: $audioClips,
            captionClips: $captionClips,
            overlayClips: $decorativeOverlayPlan['clips'],
            endScreenClips: $endScreenPlan['clips'],
        );
        $timelineValidation = $this->validateTimelineBeforeSubmit($tracks, $totalDuration, $captionSchedule);
        $editRecipe = $this->editRecipe($timelineSummary, $editSettings, $decorativeOverlayPlan, $endScreenPlan);
        $timelineSummary['clip_strategy'] = $this->clipStrategy($editSettings);
        $timelineSummary['unique_source_ranges'] = count($sourceClipRangesUsed);
        $timelineSummary['source_segments_used'] = count($sourceClipRangesUsed);
        $timelineSummary['transition_style_applied'] = $transitionStyle;
        $timelineSummary['motion_polish_applied'] = $motionPolish;
        $timelineSummary['transition_sfx_enabled'] = false;
        $timelineSummary['sfx_strategy'] = 'none';
        $timelineSummary['timeline_validation'] = $timelineValidation;
        $timelineSummary['timeline_validated'] = true;
        $timelineSummary['edit_recipe'] = $editRecipe;

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
                'caption_visual_strategy' => $this->captionVisualStrategy($overlayStyle),
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
                    'font_size_pixels' => $this->captionStyleForShotstack($captionStyle, $overlayStyle)['font_size'],
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
                'source_audio_mode' => $sourceAudioMode,
                'source_audio_volume' => $audioMix['source_audio_volume'],
                'voiceover_mix' => $voiceoverMix,
                'voiceover_volume' => $audioMix['voiceover_volume'],
                'audio_cleanup_strategy' => $audioMix['audio_cleanup_strategy'],
                'original_audio_muted' => $audioMix['original_audio_muted'],
                'music_direction' => $this->stringValue($input['music_direction'] ?? null, 'No music track added by Shotstack beta render.'),
                'mix_notes' => $this->audioMixNotes($sourceAudioMode, $voiceoverMix, $voiceoverUrl !== null),
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
                'source_audio_mode' => $sourceAudioMode,
                'source_audio_volume' => $audioMix['source_audio_volume'],
                'voiceover_mix' => $voiceoverMix,
                'voiceover_volume' => $audioMix['voiceover_volume'],
                'audio_cleanup_strategy' => $audioMix['audio_cleanup_strategy'],
                'original_audio_muted' => $audioMix['original_audio_muted'],
                'transition_style' => $transitionStyle,
                'motion_polish' => $motionPolish,
                'clip_diversity' => $clipDiversity,
                'end_screen' => $endScreen,
                'edit_mode' => $editMode,
                'ai_video_analysis_used' => $aiVideoAnalysisUsed,
                'fallback_timeline' => $fallbackTimeline,
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
                'caption_visual_strategy' => $this->captionVisualStrategy($overlayStyle),
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
                'overlay_style' => $overlayStyle,
                'overlays_count' => $decorativeOverlayPlan['overlays_count'] + $endScreenPlan['overlays_count'],
                'decorative_overlays_count' => $decorativeOverlayPlan['overlays_count'],
                'overlays_used' => array_values(array_merge($decorativeOverlayPlan['overlays_used'], $endScreenPlan['overlays_used'])),
                'compliance_sanitized_claims' => $decorativeOverlayPlan['compliance_sanitized_claims'],
                'end_screen_type' => $endScreenPlan['end_screen_type'],
                'end_screen_duration' => $endScreenPlan['end_screen_duration'],
                'end_screen_cta' => $endScreenPlan['end_screen_cta'],
                'end_screen_source' => $endScreenPlan['end_screen_source'],
                'clip_strategy' => $this->clipStrategy($editSettings),
                'unique_source_ranges' => count($sourceClipRangesUsed),
                'source_segments_used' => count($sourceClipRangesUsed),
                'transition_style_applied' => $transitionStyle,
                'motion_polish_applied' => $motionPolish,
                'transition_sfx_enabled' => false,
                'sfx_strategy' => 'none',
                'timeline_validation' => $timelineValidation,
                'timeline_validated' => true,
                'edit_recipe' => $editRecipe,
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
        array $editSettings,
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

        return $this->selectDiverseCuts($candidates, $targetDuration, $sourceDuration, $editSettings);
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
    private function selectDiverseCuts(array $candidates, int $targetDuration, ?float $sourceDuration, array $editSettings): array
    {
        usort($candidates, fn (array $a, array $b): int => $this->clipScore($b, $sourceDuration, $editSettings) <=> $this->clipScore($a, $sourceDuration, $editSettings));

        $selected = [];
        $total = 0.0;
        $maxClips = $this->maxClipCount($sourceDuration, $editSettings);

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

        return $this->applyClipPacing($selected, $editSettings);
    }

    /**
     * @param  array<int, array<string, mixed>>  $clips
     * @return array<int, array<string, mixed>>
     */
    private function applyClipPacing(array $clips, array $editSettings): array
    {
        $transitionStyle = (string) ($editSettings['transition_style'] ?? AutoVideoEditSettings::TRANSITION_CLEAN_CUTS);

        return array_map(function (array $clip) use ($transitionStyle): array {
            $duration = (float) ($clip['duration_seconds'] ?? 0.0);
            $maxDuration = match ($transitionStyle) {
                AutoVideoEditSettings::TRANSITION_SNAPPY_SOCIAL => 4.25,
                AutoVideoEditSettings::TRANSITION_PREMIUM_SMOOTH => 6.5,
                default => 8.0,
            };
            $duration = round(min($duration, $maxDuration), 2);
            $start = (float) ($clip['source_start_seconds'] ?? 0.0);

            $clip['duration_seconds'] = max(0.5, $duration);
            $clip['source_end_seconds'] = round($start + $clip['duration_seconds'], 2);

            return $clip;
        }, $clips);
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
    private function timelineItems(array $clips, array $analysis, array $input, array $brief, string $cta, array $editSettings): array
    {
        $start = 0.0;
        $lastIndex = max(0, count($clips) - 1);
        $alternates = $this->captionAlternates($analysis, $brief, $cta);

        $showEndScreen = ($editSettings['end_screen'] ?? AutoVideoEditSettings::END_SCREEN_PRODUCT_HOLD_CTA) !== AutoVideoEditSettings::END_SCREEN_NONE;

        return array_map(function (array $clip, int $index) use (&$start, $cta, $lastIndex, $alternates, $showEndScreen): array {
            $duration = (float) $clip['duration_seconds'];
            $text = $index === $lastIndex && $showEndScreen
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
                'layer' => $index === $lastIndex && $showEndScreen ? 'cta' : 'video',
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
    private function extendTimelineForDuration(array $items, float $requiredDuration, string $cta, string &$strategy, array &$warnings, int &$repeatedClipCount, ?string &$repeatedClipReason, array $editSettings): array
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
        $showEndScreen = ($editSettings['end_screen'] ?? AutoVideoEditSettings::END_SCREEN_PRODUCT_HOLD_CTA) !== AutoVideoEditSettings::END_SCREEN_NONE;

        while ($total < $requiredDuration - 0.1 && count($items) < 10) {
            $remaining = round($requiredDuration - $total, 2);
            $duration = min(max($remaining, 1.0), $remaining > 4.0 ? 4.0 : $remaining);
            $isFinal = $remaining <= 4.0 && $showEndScreen;
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
    private function buildVideoClips(string $sourceVideoUrl, array $items, array $audioMix, array $editSettings): array
    {
        return $this->videoClips($sourceVideoUrl, $items, $audioMix, $editSettings);
    }

    /** @return array<int, array<string, mixed>> */
    private function buildAudioClips(?string $voiceoverUrl, array $audioMix, float $totalDuration): array
    {
        if ($voiceoverUrl === null) {
            return [];
        }

        return [[
            'asset' => [
                'type' => 'audio',
                'src' => $voiceoverUrl,
                'volume' => $audioMix['voiceover_volume'],
            ],
            'start' => 0,
            'length' => $totalDuration,
        ]];
    }

    /**
     * Shotstack renders higher visual tracks above lower ones. Keep captions
     * first, then CTA/end-screen overlays, then decorative overlays, then the
     * base video track. Audio is separated from visual layering.
     *
     * @return array<int, array{clips: array<int, array<string, mixed>>}>
     */
    private function buildTracks(array $videoClips, array $audioClips, array $captionClips, array $overlayClips, array $endScreenClips): array
    {
        $tracks = [];

        if ($audioClips !== []) {
            $tracks[] = ['clips' => $audioClips];
        }

        if ($captionClips !== []) {
            $tracks[] = ['clips' => $captionClips];
        }

        if ($endScreenClips !== []) {
            $tracks[] = ['clips' => $endScreenClips];
        }

        if ($overlayClips !== []) {
            $tracks[] = ['clips' => $overlayClips];
        }

        $tracks[] = ['clips' => $videoClips];

        return $tracks;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function videoClips(string $sourceVideoUrl, array $items, array $audioMix, array $editSettings): array
    {
        return array_map(fn (array $item): array => [
            'asset' => [
                'type' => 'video',
                'src' => $sourceVideoUrl,
                'trim' => (float) $item['source_start_seconds'],
                'volume' => $audioMix['source_audio_volume'],
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
            'transition' => $this->transitionForShotstack((string) ($editSettings['transition_style'] ?? AutoVideoEditSettings::TRANSITION_CLEAN_CUTS)),
        ], $items);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $analysis
     * @param  array<string, mixed>  $brief
     * @param  array<int, array{start: float, length: float, text: string, type: string}>  $captionSchedule
     * @return array{clips: array<int, array<string, mixed>>, overlays_count: int, overlays_used: array<int, string>, compliance_sanitized_claims: array<int, string>}
     */
    private function buildOverlayClips(array $items, array $analysis, array $brief, string $overlayStyle, array $captionSchedule, float $finalDuration): array
    {
        $sanitizedClaims = [];
        $texts = $this->overlayTexts($analysis, $brief, $overlayStyle, $sanitizedClaims);
        $limit = match ($overlayStyle) {
            AutoVideoEditSettings::OVERLAY_UGC_NATIVE => 2,
            AutoVideoEditSettings::OVERLAY_PRODUCT_HIGHLIGHTS => 3,
            AutoVideoEditSettings::OVERLAY_PROMO_BADGES => 2,
            AutoVideoEditSettings::OVERLAY_PREMIUM_BRAND => 1,
            default => 0,
        };

        if ($limit <= 0 || $texts === []) {
            return [
                'clips' => [],
                'overlays_count' => 0,
                'overlays_used' => [],
                'compliance_sanitized_claims' => array_values(array_unique($sanitizedClaims)),
            ];
        }

        $windows = $this->overlayWindows($items, $captionSchedule, $finalDuration, min($limit, count($texts)));
        $clips = [];
        $used = [];

        foreach (array_slice($texts, 0, $limit) as $index => $text) {
            $window = $windows[$index] ?? null;

            if ($window === null) {
                continue;
            }

            $position = $this->overlayPosition($overlayStyle, $index);
            $clips[] = [
                'asset' => [
                    'type' => 'html',
                    'html' => $this->overlayHtml($text, $overlayStyle),
                    'css' => $this->overlayCss($overlayStyle),
                    'width' => 430,
                    'height' => 92,
                ],
                'start' => $window['start'],
                'length' => $window['length'],
                'position' => $position['position'],
                'offset' => $position['offset'],
            ];
            $used[] = $text;
        }

        return [
            'clips' => $clips,
            'overlays_count' => count($clips),
            'overlays_used' => $used,
            'compliance_sanitized_claims' => array_values(array_unique($sanitizedClaims)),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{clips: array<int, array<string, mixed>>, overlays_count: int, overlays_used: array<int, string>, end_screen_type: string, end_screen_duration: float|null, end_screen_cta: string|null, end_screen_source: string|null}
     */
    private function buildEndScreenClips(array $items, string $cta, string $overlayStyle, string $endScreen, float $finalDuration): array
    {
        if ($endScreen === AutoVideoEditSettings::END_SCREEN_NONE || trim($cta) === '' || $finalDuration <= 1.5) {
            return [
                'clips' => [],
                'overlays_count' => 0,
                'overlays_used' => [],
                'end_screen_type' => AutoVideoEditSettings::END_SCREEN_NONE,
                'end_screen_duration' => null,
                'end_screen_cta' => null,
                'end_screen_source' => null,
            ];
        }

        $warnings = [];
        $text = $this->truncateCaptionForOverlay($cta, $warnings);
        $duration = round(min(3.0, max(1.6, $finalDuration * 0.16)), 2);
        $start = round(max(0.0, $finalDuration - $duration - 0.15), 2);
        $source = $endScreen === AutoVideoEditSettings::END_SCREEN_PRODUCT_HOLD_CTA
            ? $this->bestFinalProductShotSource($items)
            : 'final_cta_overlay';
        $position = $overlayStyle === AutoVideoEditSettings::OVERLAY_PREMIUM_BRAND
            ? ['position' => 'center', 'offset' => ['x' => 0.0, 'y' => 0.04]]
            : ['position' => 'center', 'offset' => ['x' => 0.0, 'y' => 0.08]];

        return [
            'clips' => [[
                'asset' => [
                    'type' => 'html',
                    'html' => $this->endScreenHtml($text, $overlayStyle),
                    'css' => $this->endScreenCss($overlayStyle),
                    'width' => 560,
                    'height' => 170,
                ],
                'start' => $start,
                'length' => $duration,
                'position' => $position['position'],
                'offset' => $position['offset'],
            ]],
            'overlays_count' => 1,
            'overlays_used' => ['end screen: '.$text],
            'end_screen_type' => $endScreen,
            'end_screen_duration' => $duration,
            'end_screen_cta' => $text,
            'end_screen_source' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @param  array<string, mixed>  $brief
     * @param  array<int, string>  $sanitizedClaims
     * @return array<int, string>
     */
    private function overlayTexts(array $analysis, array $brief, string $overlayStyle, array &$sanitizedClaims): array
    {
        $texts = [];
        $push = function (mixed $value, bool $allowOffer = false) use (&$texts, &$sanitizedClaims): void {
            $text = $this->safeOverlayText($value, $sanitizedClaims, $allowOffer);

            if ($text === null || in_array(mb_strtolower($text), array_map('mb_strtolower', $texts), true)) {
                return;
            }

            $texts[] = $text;
        };

        if ($overlayStyle === AutoVideoEditSettings::OVERLAY_MINIMAL_CLEAN) {
            return [];
        }

        if ($overlayStyle === AutoVideoEditSettings::OVERLAY_UGC_NATIVE) {
            $push('Quick demo');
            $push('Worth checking');
        }

        if ($overlayStyle === AutoVideoEditSettings::OVERLAY_PRODUCT_HIGHLIGHTS) {
            foreach ($this->featureOverlayValues($analysis, $brief) as $value) {
                $push($value);
            }

            $push('Product detail');
            $push('Quick demo');
        }

        if ($overlayStyle === AutoVideoEditSettings::OVERLAY_PROMO_BADGES) {
            $cta = $this->stringValue($brief['selected_cta'] ?? null, $this->firstString($analysis['cta_options'] ?? null) ?? '');
            $push($cta, allowOffer: true);
            $push('Quick look');
            $push('Check details');
        }

        if ($overlayStyle === AutoVideoEditSettings::OVERLAY_PREMIUM_BRAND) {
            $push('Details first');
            $push('Clean product view');
        }

        return array_slice($texts, 0, 4);
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @param  array<string, mixed>  $brief
     * @return array<int, mixed>
     */
    private function featureOverlayValues(array $analysis, array $brief): array
    {
        $values = [];

        foreach ([$analysis['observed_product_features'] ?? null, $analysis['detected_product_moments'] ?? null, $analysis['recommended_cuts'] ?? null, $brief['preferred_cuts'] ?? null] as $group) {
            if (! is_array($group)) {
                continue;
            }

            foreach ($group as $item) {
                if (is_string($item)) {
                    $values[] = $item;
                } elseif (is_array($item)) {
                    $values[] = $item['feature']
                        ?? $item['caption']
                        ?? $item['caption_suggestion']
                        ?? $item['title']
                        ?? $item['moment']
                        ?? $item['campaign_use']
                        ?? $item['text_overlay']
                        ?? null;
                }
            }
        }

        return $values;
    }

    /** @param array<int, string> $sanitizedClaims */
    private function safeOverlayText(mixed $value, array &$sanitizedClaims, bool $allowOffer = false): ?string
    {
        $text = $this->normalizeCaptionText($value);

        if ($text === '') {
            return null;
        }

        $warnings = [];
        $singleLine = str_replace("\n", ' ', $this->truncateCaptionForOverlay($text, $warnings));
        $singleLine = trim((string) preg_replace('/\s+/u', ' ', $singleLine));

        if ($this->looksLikeRiskyClaim($singleLine, $allowOffer)) {
            $sanitizedClaims[] = $singleLine;

            return null;
        }

        return Str::limit($singleLine, 38, '');
    }

    private function looksLikeRiskyClaim(string $text, bool $allowOffer): bool
    {
        $lower = mb_strtolower($text);
        $risky = ['guaranteed', 'cure', 'medical', 'doctor', 'rated', '5-star', 'five-star', '#1', 'number one', 'best in the world'];

        if (! $allowOffer) {
            $risky = array_merge($risky, ['discount', 'limited', 'sale', 'free', 'lowest price', 'only today']);
        }

        foreach ($risky as $term) {
            if (str_contains($lower, $term)) {
                return true;
            }
        }

        return preg_match('/(?:\$|rp\s?\d|\d+\s?%\s?off)/iu', $lower) === 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array{start: float, length: float, text: string, type: string}>  $captionSchedule
     * @return array<int, array{start: float, length: float}>
     */
    private function overlayWindows(array $items, array $captionSchedule, float $finalDuration, int $limit): array
    {
        $windows = [];

        foreach ($items as $item) {
            if (count($windows) >= $limit) {
                break;
            }

            if (($item['source'] ?? null) === 'cta_end_card') {
                continue;
            }

            $itemStart = $this->numberValue($item['timeline_start_seconds'] ?? $item['start_seconds'] ?? null, 0.0);
            $itemEnd = $this->numberValue($item['timeline_end_seconds'] ?? null, $itemStart + $this->numberValue($item['duration_seconds'] ?? null, 0.0));
            $length = round(min(2.0, max(1.2, ($itemEnd - $itemStart) - 0.4)), 2);

            if ($length <= 0 || $itemEnd <= $itemStart) {
                continue;
            }

            $window = $this->nonOverlappingWindow($itemStart + 0.35, $length, $itemEnd, $captionSchedule, $finalDuration);

            if ($window !== null) {
                $windows[] = $window;
            }
        }

        return $windows;
    }

    /**
     * @param  array<int, array{start: float, length: float, text: string, type: string}>  $captionSchedule
     * @return array{start: float, length: float}|null
     */
    private function nonOverlappingWindow(float $preferredStart, float $length, float $maxEnd, array $captionSchedule, float $finalDuration): ?array
    {
        $latestStart = min($maxEnd, $finalDuration) - $length;
        $candidates = [max(0.2, min($preferredStart, $latestStart))];

        foreach ($captionSchedule as $caption) {
            $captionEnd = (float) $caption['start'] + (float) $caption['length'];
            $candidates[] = round($captionEnd + 0.15, 2);
        }

        foreach ($candidates as $candidateStart) {
            $start = round(max(0.2, min($candidateStart, $latestStart)), 2);
            $end = $start + $length;

            if ($end > $maxEnd + 0.01 || $end > $finalDuration + 0.01) {
                continue;
            }

            if (! $this->windowOverlapsCaptions($start, $end, $captionSchedule)) {
                return ['start' => $start, 'length' => $length];
            }
        }

        return null;
    }

    /** @param array<int, array{start: float, length: float, text: string, type: string}> $captionSchedule */
    private function windowOverlapsCaptions(float $start, float $end, array $captionSchedule): bool
    {
        foreach ($captionSchedule as $caption) {
            if (($caption['type'] ?? null) === 'cta') {
                continue;
            }

            $captionStart = (float) $caption['start'];
            $captionEnd = $captionStart + (float) $caption['length'];

            if ($start < $captionEnd + 0.12 && $end > $captionStart - 0.12) {
                return true;
            }
        }

        return false;
    }

    /** @return array{position: string, offset: array{x: float, y: float}} */
    private function overlayPosition(string $overlayStyle, int $index): array
    {
        return match ($overlayStyle) {
            AutoVideoEditSettings::OVERLAY_PROMO_BADGES => ['position' => 'top', 'offset' => ['x' => 0.18, 'y' => 0.12]],
            AutoVideoEditSettings::OVERLAY_PREMIUM_BRAND => ['position' => 'top', 'offset' => ['x' => 0.0, 'y' => 0.14]],
            default => ['position' => 'top', 'offset' => ['x' => $index % 2 === 0 ? -0.18 : 0.18, 'y' => 0.12]],
        };
    }

    private function overlayHtml(string $text, string $overlayStyle): string
    {
        $label = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $class = 'overlay overlay-'.$overlayStyle;

        return '<div class="'.$class.'">'.$label.'</div>';
    }

    private function overlayCss(string $overlayStyle): string
    {
        $accent = match ($overlayStyle) {
            AutoVideoEditSettings::OVERLAY_PROMO_BADGES => '#fbbf24',
            AutoVideoEditSettings::OVERLAY_PREMIUM_BRAND => '#f8fafc',
            AutoVideoEditSettings::OVERLAY_UGC_NATIVE => '#67e8f9',
            default => '#34d399',
        };
        $background = $overlayStyle === AutoVideoEditSettings::OVERLAY_PREMIUM_BRAND
            ? 'rgba(2,6,23,0.42)'
            : 'rgba(2,6,23,0.58)';

        return 'html,body{margin:0;width:100%;height:100%;overflow:hidden;}'
            .'body{display:flex;align-items:center;justify-content:center;font-family:Inter,Arial,sans-serif;}'
            .'.overlay{box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;max-width:360px;'
            .'min-height:44px;padding:10px 16px;border-radius:999px;border:1px solid rgba(255,255,255,0.18);'
            .'background:'.$background.';color:#fff;font-size:24px;font-weight:760;line-height:1.05;text-align:center;'
            .'text-shadow:0 3px 12px rgba(0,0,0,0.62);box-shadow:0 10px 28px rgba(0,0,0,0.20);}'
            .'.overlay-product_highlights{color:'.$accent.';}'
            .'.overlay-promo_badges{color:#111827;background:'.$accent.';text-shadow:none;border-color:rgba(255,255,255,0.28);}'
            .'.overlay-premium_brand{font-weight:660;color:'.$accent.';background:'.$background.';}';
    }

    private function endScreenHtml(string $text, string $overlayStyle): string
    {
        $label = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $eyebrow = $overlayStyle === AutoVideoEditSettings::OVERLAY_PREMIUM_BRAND ? 'Nusa edit' : 'Next step';

        return '<div class="end-card"><span>'.$eyebrow.'</span><strong>'.$label.'</strong></div>';
    }

    private function endScreenCss(string $overlayStyle): string
    {
        $border = $overlayStyle === AutoVideoEditSettings::OVERLAY_PREMIUM_BRAND ? 'rgba(255,255,255,0.28)' : 'rgba(52,211,153,0.32)';

        return 'html,body{margin:0;width:100%;height:100%;overflow:hidden;}'
            .'body{display:flex;align-items:center;justify-content:center;font-family:Inter,Arial,sans-serif;text-align:center;}'
            .'.end-card{box-sizing:border-box;display:flex;flex-direction:column;gap:10px;align-items:center;justify-content:center;'
            .'width:520px;min-height:142px;padding:20px 28px;border-radius:30px;border:1px solid '.$border.';'
            .'background:linear-gradient(135deg,rgba(2,6,23,0.72),rgba(15,23,42,0.58));box-shadow:0 18px 48px rgba(0,0,0,0.32);}'
            .'.end-card span{font-size:15px;font-weight:760;letter-spacing:0.18em;text-transform:uppercase;color:#34d399;}'
            .'.end-card strong{max-width:440px;color:#fff;font-size:30px;font-weight:780;line-height:1.05;text-shadow:0 4px 16px rgba(0,0,0,0.72);}';
    }

    /** @param array<int, array<string, mixed>> $items */
    private function bestFinalProductShotSource(array $items): ?string
    {
        $candidates = array_values(array_filter($items, fn (array $item): bool => ($item['source'] ?? null) !== 'cta_end_card'));

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (array $a, array $b): int {
            $scoreA = $this->qualityScore($a['product_visibility'] ?? null, 12, 6, -8)
                + $this->qualityScore($a['stability'] ?? null, 10, 5, -8)
                + (int) round((float) ($a['source_start_seconds'] ?? 0.0) / 3);
            $scoreB = $this->qualityScore($b['product_visibility'] ?? null, 12, 6, -8)
                + $this->qualityScore($b['stability'] ?? null, 10, 5, -8)
                + (int) round((float) ($b['source_start_seconds'] ?? 0.0) / 3);

            return $scoreB <=> $scoreA;
        });

        $best = $candidates[0];

        return $best['source_range'] ?? $this->timestamp((float) ($best['source_start_seconds'] ?? 0.0), (float) ($best['source_end_seconds'] ?? 0.0));
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
    private function captionClips(array $schedule, string $captionStyle, string $overlayStyle): array
    {
        $style = $this->captionStyleForShotstack($captionStyle, $overlayStyle);

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
    private function captionStyleForShotstack(string $style, ?string $overlayStyle = null): array
    {
        $base = match ($style) {
            'bold_hook' => ['color' => '#ffffff', 'background' => 'rgba(0, 0, 0, 0)', 'font_size' => 34, 'font_weight' => 760, 'shadow' => '0 3px 14px rgba(0,0,0,0.82)'],
            'minimal' => ['color' => '#ffffff', 'background' => 'rgba(0, 0, 0, 0)', 'font_size' => 30, 'font_weight' => 620, 'shadow' => '0 3px 14px rgba(0,0,0,0.74)'],
            'premium_clean' => ['color' => '#f8fafc', 'background' => 'rgba(0, 0, 0, 0)', 'font_size' => 31, 'font_weight' => 660, 'shadow' => '0 3px 14px rgba(0,0,0,0.76)'],
            'ugc_native' => ['color' => '#ffffff', 'background' => 'rgba(0, 0, 0, 0)', 'font_size' => 33, 'font_weight' => 720, 'shadow' => '0 3px 14px rgba(0,0,0,0.80)'],
            default => ['color' => '#ffffff', 'background' => 'rgba(0, 0, 0, 0)', 'font_size' => 31, 'font_weight' => 660, 'shadow' => '0 3px 14px rgba(0,0,0,0.76)'],
        };

        if ($overlayStyle === AutoVideoEditSettings::OVERLAY_PREMIUM_BRAND) {
            $base['font_size'] = min($base['font_size'], 30);
            $base['font_weight'] = min($base['font_weight'], 680);
            $base['color'] = '#f8fafc';
        } elseif ($overlayStyle === AutoVideoEditSettings::OVERLAY_PROMO_BADGES) {
            $base['font_weight'] = max($base['font_weight'], 720);
        }

        return $base;
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
    private function clipScore(array $clip, ?float $sourceDuration, array $editSettings): int
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

        $transitionStyle = (string) ($editSettings['transition_style'] ?? AutoVideoEditSettings::TRANSITION_CLEAN_CUTS);
        $clipDiversity = (string) ($editSettings['clip_diversity'] ?? AutoVideoEditSettings::CLIP_DIVERSITY_BALANCED);
        $motionPolish = (string) ($editSettings['motion_polish'] ?? AutoVideoEditSettings::MOTION_SUBTLE_ZOOM);
        $sourceAudioMode = (string) ($editSettings['source_audio_mode'] ?? AutoVideoEditSettings::SOURCE_AUDIO_LOW);

        if ($transitionStyle === AutoVideoEditSettings::TRANSITION_SNAPPY_SOCIAL) {
            $score += $duration <= 4.5 ? 8 : -6;
        }

        if ($clipDiversity === AutoVideoEditSettings::CLIP_DIVERSITY_CONCISE) {
            $score += strtolower((string) ($clip['priority'] ?? 'medium')) === 'high' ? 8 : -4;
            $score += $duration > 5.5 ? -8 : 0;
        }

        if ($motionPolish === AutoVideoEditSettings::MOTION_PRODUCT_FOCUS) {
            $score += $this->qualityScore($clip['product_visibility'] ?? null, 14, 6, -16);
            $score += $this->clipContainsAnyCue($clip, ['close', 'detail', 'texture', 'product', 'hero']) ? 8 : 0;
        }

        if ($sourceAudioMode === AutoVideoEditSettings::SOURCE_AUDIO_PRODUCT_SOUNDS) {
            $score += $this->clipContainsAnyCue($clip, ['click', 'button', 'press', 'hold', 'holding', 'handling', 'demo', 'use', 'interaction']) ? 20 : -6;
        }

        if ($sourceDuration !== null && $sourceDuration > 0) {
            $position = ((float) ($clip['source_start_seconds'] ?? 0.0)) / $sourceDuration;
            $score += (int) round(min($clipDiversity === AutoVideoEditSettings::CLIP_DIVERSITY_MORE_UNIQUE ? 22 : 12, max(0, $position * ($clipDiversity === AutoVideoEditSettings::CLIP_DIVERSITY_MORE_UNIQUE ? 22 : 12))));
        }

        return $score;
    }

    /** @param array<string, mixed> $editSettings */
    private function maxClipCount(?float $sourceDuration, array $editSettings): int
    {
        $base = $sourceDuration !== null && $sourceDuration > 30 ? 6 : 5;
        $transitionStyle = (string) ($editSettings['transition_style'] ?? AutoVideoEditSettings::TRANSITION_CLEAN_CUTS);

        return match ($editSettings['clip_diversity'] ?? AutoVideoEditSettings::CLIP_DIVERSITY_BALANCED) {
            AutoVideoEditSettings::CLIP_DIVERSITY_MORE_UNIQUE => min(8, $base + 2),
            AutoVideoEditSettings::CLIP_DIVERSITY_CONCISE => max(3, $base - 2),
            default => $transitionStyle === AutoVideoEditSettings::TRANSITION_SNAPPY_SOCIAL ? min(7, $base + 1) : $base,
        };
    }

    /** @return array{in: string, out: string} */
    private function transitionForShotstack(string $style): array
    {
        return match ($style) {
            AutoVideoEditSettings::TRANSITION_SOFT_FADES, AutoVideoEditSettings::TRANSITION_PREMIUM_SMOOTH => ['in' => 'fade', 'out' => 'fade'],
            default => ['in' => 'none', 'out' => 'none'],
        };
    }

    /** @return array<int, string> */
    private function audioMixNotes(string $sourceAudioMode, string $voiceoverMix, bool $hasVoiceover): array
    {
        if ($sourceAudioMode === AutoVideoEditSettings::SOURCE_AUDIO_MUTE) {
            return $hasVoiceover
                ? ['Original camera audio is muted for a clean voiceover-focused export.', 'No music track is added in this beta render.', 'Beta cleanup controls volume and mixing; advanced AI denoise is not applied yet.']
                : ['Original camera audio is muted.', 'No music track is added in this beta render.', 'Beta cleanup controls volume and mixing; advanced AI denoise is not applied yet.'];
        }

        $notes = match ($sourceAudioMode) {
            AutoVideoEditSettings::SOURCE_AUDIO_PRODUCT_SOUNDS => ['Original video audio is kept very subtle for useful product handling sounds.'],
            AutoVideoEditSettings::SOURCE_AUDIO_KEEP => ['Original video audio is kept, with volume reduced when voiceover is present.'],
            default => ['Original video audio is kept low so it does not compete with the main message.'],
        };

        if ($hasVoiceover) {
            $notes[] = match ($voiceoverMix) {
                AutoVideoEditSettings::VOICEOVER_MIX_BALANCED => 'Voiceover remains clear while allowing a little source ambience.',
                AutoVideoEditSettings::VOICEOVER_MIX_SOFT_BACKGROUND => 'Voiceover is slightly softer, with low source ambience underneath.',
                default => 'Voiceover is dominant in the mix.',
            };
        }

        $notes[] = 'Beta cleanup controls volume and mixing; advanced AI denoise is not applied yet.';

        return $notes;
    }

    /** @param array<string, mixed> $clip */
    private function clipContainsAnyCue(array $clip, array $terms): bool
    {
        $haystack = strtolower(implode(' ', array_filter([
            $clip['instruction'] ?? null,
            $clip['text_overlay'] ?? null,
            $clip['purpose'] ?? null,
            $clip['visual_reason'] ?? null,
            $clip['source'] ?? null,
        ], fn (mixed $value): bool => is_string($value) && trim($value) !== '')));

        foreach ($terms as $term) {
            if (str_contains($haystack, $term)) {
                return true;
            }
        }

        return false;
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

    /**
     * @param  array<int, array{clips: array<int, array<string, mixed>>}>  $tracks
     * @param  array<int, array{start: float, length: float, text: string, type: string}>  $captionSchedule
     * @return array{valid: bool, clips_checked: int, max_end_seconds: float, captions_overlap_prevented: bool}
     */
    private function validateTimelineBeforeSubmit(array $tracks, float $finalDuration, array $captionSchedule): array
    {
        if ($finalDuration <= 0 || is_nan($finalDuration) || is_infinite($finalDuration)) {
            throw new InvalidArgumentException('Shotstack timeline final duration must be positive.');
        }

        $clipsChecked = 0;
        $maxEnd = 0.0;

        foreach ($tracks as $trackIndex => $track) {
            foreach (($track['clips'] ?? []) as $clipIndex => $clip) {
                $start = $this->numberValue($clip['start'] ?? null, -1.0);
                $length = $this->numberValue($clip['length'] ?? null, -1.0);

                if ($start < 0 || $length <= 0 || is_nan($start) || is_nan($length) || is_infinite($start) || is_infinite($length)) {
                    throw new InvalidArgumentException("Invalid Shotstack clip timing at track {$trackIndex}, clip {$clipIndex}.");
                }

                if (($clip['asset']['type'] ?? null) === 'video') {
                    $trim = $this->numberValue($clip['asset']['trim'] ?? null, 0.0);

                    if ($trim < 0 || is_nan($trim) || is_infinite($trim)) {
                        throw new InvalidArgumentException("Invalid Shotstack video trim at track {$trackIndex}, clip {$clipIndex}.");
                    }
                }

                $end = $start + $length;

                if ($end > $finalDuration + 0.25) {
                    throw new InvalidArgumentException("Shotstack clip extends beyond final duration at track {$trackIndex}, clip {$clipIndex}.");
                }

                $clipsChecked++;
                $maxEnd = max($maxEnd, $end);
            }
        }

        if ($this->captionsOverlap($captionSchedule)) {
            throw new InvalidArgumentException('Shotstack caption schedule contains overlapping captions.');
        }

        return [
            'valid' => true,
            'clips_checked' => $clipsChecked,
            'max_end_seconds' => round($maxEnd, 2),
            'captions_overlap_prevented' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $editSettings
     * @param  array<string, mixed>  $decorativeOverlayPlan
     * @param  array<string, mixed>  $endScreenPlan
     * @return array<string, mixed>
     */
    private function editRecipe(array $summary, array $editSettings, array $decorativeOverlayPlan, array $endScreenPlan): array
    {
        return [
            'edit_mode' => $editSettings['edit_mode'] ?? null,
            'overlay_style' => $editSettings['overlay_style'] ?? null,
            'transition_style' => $editSettings['transition_style'] ?? null,
            'motion_polish' => $editSettings['motion_polish'] ?? null,
            'clip_diversity' => $editSettings['clip_diversity'] ?? null,
            'end_screen' => $editSettings['end_screen'] ?? null,
            'source_audio' => $editSettings['source_audio_mode'] ?? null,
            'voiceover_mix' => $editSettings['voiceover_mix'] ?? null,
            'captions_used' => $summary['final_caption_count'] ?? $summary['caption_count'] ?? null,
            'overlays_used' => ($decorativeOverlayPlan['overlays_count'] ?? 0) + ($endScreenPlan['overlays_count'] ?? 0),
            'unique_clips_used' => $summary['unique_source_ranges'] ?? count($summary['source_clip_ranges_used'] ?? []),
        ];
    }

    private function captionVisualStrategy(string $overlayStyle): string
    {
        return match ($overlayStyle) {
            AutoVideoEditSettings::OVERLAY_PREMIUM_BRAND => 'premium_shadow_text_clean_caption',
            AutoVideoEditSettings::OVERLAY_PROMO_BADGES => 'compact_caption_with_promo_badge_layer',
            AutoVideoEditSettings::OVERLAY_UGC_NATIVE => 'native_social_shadow_caption',
            default => 'shadow_text_light_caption',
        };
    }

    /** @param array<string, mixed> $editSettings */
    private function clipStrategy(array $editSettings): string
    {
        return implode('_', array_filter([
            $editSettings['edit_mode'] ?? 'manual_edit',
            $editSettings['clip_diversity'] ?? AutoVideoEditSettings::CLIP_DIVERSITY_BALANCED,
            $editSettings['transition_style'] ?? AutoVideoEditSettings::TRANSITION_CLEAN_CUTS,
        ], fn (mixed $value): bool => is_string($value) && trim($value) !== ''));
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @param  array<string, mixed>  $brief
     */
    private function hasProductMoments(array $analysis, array $brief): bool
    {
        foreach ([$analysis['detected_product_moments'] ?? null, $analysis['recommended_cuts'] ?? null, data_get($analysis, 'edit_plan.timeline'), $brief['preferred_cuts'] ?? null] as $group) {
            if (is_array($group) && $group !== []) {
                return true;
            }
        }

        return false;
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
        return $this->timestampSecond($start).'-'.$this->timestampSecond($end).'s';
    }

    private function timestampSecond(float $value): string
    {
        $rounded = round($value, 1);

        if (abs($rounded - round($rounded)) < 0.01) {
            return (string) (int) round($rounded);
        }

        return rtrim(rtrim(number_format($rounded, 1, '.', ''), '0'), '.');
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
