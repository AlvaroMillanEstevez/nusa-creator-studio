<?php

namespace App\Services\Generation;

use App\Support\AutoVideoCaptionStyle;
use App\Support\AutoVideoEditSettings;
use Illuminate\Support\Str;

class AutoVideoEditorCreativeBriefBuilder
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function build(array $input): array
    {
        $analysis = $this->analysis($input);
        $requestedTarget = $this->duration(
            $input['requested_target_duration_seconds'] ?? $input['target_duration_seconds'] ?? null,
            30,
            8,
            60,
        );
        $clientEffectiveTarget = $this->duration(
            $input['effective_target_duration_seconds'] ?? null,
            $requestedTarget,
            8,
            60,
        );
        $voiceoverDuration = $this->voiceoverDuration($input);
        $cta = $this->selectedCta($input, $analysis);
        $hasVoiceover = (bool) ($input['include_voiceover'] ?? false);
        $sourceAudioMode = AutoVideoEditSettings::sourceAudioMode($input['source_audio_mode'] ?? null, $hasVoiceover);
        $voiceoverMix = AutoVideoEditSettings::voiceoverMix($input['voiceover_mix'] ?? null);
        $editingStyle = $this->string($input['editing_style'] ?? null, 'ugc_natural');
        $transitionStyle = AutoVideoEditSettings::transitionStyle($input['transition_style'] ?? null, $editingStyle);
        $motionPolish = AutoVideoEditSettings::motionPolish($input['motion_polish'] ?? null);
        $clipDiversity = AutoVideoEditSettings::clipDiversity($input['clip_diversity'] ?? null);
        $endScreen = AutoVideoEditSettings::endScreen($input['end_screen'] ?? null, $cta !== '');
        $editMode = $this->string($input['edit_mode'] ?? null, 'manual_edit');
        $cutCandidates = $this->cutCandidates($analysis);
        $overlayStyle = AutoVideoEditSettings::overlayStyle($input['overlay_style'] ?? null, $editMode, $cutCandidates !== []);
        $warnings = [];

        if (($input['include_voiceover'] ?? false) && $voiceoverDuration === null) {
            $warnings[] = 'Voiceover duration was estimated from the selected script because exact audio duration was unavailable.';
        }

        $effectiveTarget = $voiceoverDuration !== null
            ? max($requestedTarget, $clientEffectiveTarget, (int) ceil($voiceoverDuration + 0.5))
            : max($requestedTarget, $clientEffectiveTarget);

        return [
            'edit_mode' => $editMode,
            'ai_video_analysis_used' => (bool) ($input['ai_video_analysis_used'] ?? false),
            'fallback_timeline' => (bool) ($input['fallback_timeline'] ?? true),
            'platform' => $this->string($input['platform'] ?? null, 'tiktok'),
            'aspect_ratio' => '9:16',
            'target_duration_seconds' => $requestedTarget,
            'voiceover_duration_seconds' => $voiceoverDuration,
            'effective_target_duration_seconds' => max(8, min(60, $effectiveTarget)),
            'selected_cta' => $cta,
            'caption_style' => AutoVideoCaptionStyle::normalize($input['caption_style'] ?? null),
            'source_audio_mode' => $sourceAudioMode,
            'voiceover_mix' => $voiceoverMix,
            'transition_style' => $transitionStyle,
            'motion_polish' => $motionPolish,
            'clip_diversity' => $clipDiversity,
            'end_screen' => $endScreen,
            'overlay_style' => $overlayStyle,
            'editing_style' => $editingStyle,
            'pacing' => $this->string($input['pacing'] ?? null, 'medium'),
            'music_direction' => $this->nullableString($input['music_direction'] ?? null),
            'notes' => $this->nullableString($input['notes'] ?? null),
            'source_duration_seconds' => $this->sourceDuration($input),
            'safe_area_rules' => [
                'position' => 'bottom_center',
                'width_percent' => 80,
                'bottom_margin_percent' => 18,
                'max_lines' => 2,
                'max_characters_per_line' => 28,
            ],
            'preferred_cuts' => $this->cuts($cutCandidates),
            'fallback_cuts' => $this->cuts($analysis['recommended_cuts'] ?? null),
            'final_screen' => [
                'type' => $endScreen === AutoVideoEditSettings::END_SCREEN_NONE ? 'none' : 'cta_overlay',
                'text' => $cta,
                'hold_seconds' => 2.5,
            ],
            'warnings' => $warnings,
        ];
    }

    /** @param array<string, mixed> $input */
    private function analysis(array $input): array
    {
        $analysis = $input['source_video_to_campaign_summary'] ?? [];

        return is_array($analysis) ? $analysis : [];
    }

    /** @param array<string, mixed> $analysis */
    private function selectedCta(array $input, array $analysis): string
    {
        return $this->nullableString($input['cta'] ?? null)
            ?? $this->firstString($analysis['cta_options'] ?? null)
            ?? $this->nullableString(data_get($analysis, 'edit_plan.closing'))
            ?? 'Review the product details before buying.';
    }

    /** @param array<string, mixed> $input */
    private function voiceoverDuration(array $input): ?float
    {
        if (! (bool) ($input['include_voiceover'] ?? false)) {
            return null;
        }

        foreach ([
            'voiceover_generation_summary.audio.duration_seconds',
            'voiceover_generation_summary.estimated_duration_seconds',
            'voiceover_audio_asset.metadata.duration_seconds',
            'voiceover_audio_asset.metadata.estimated_duration_seconds',
        ] as $key) {
            $duration = data_get($input, $key);

            if (is_numeric($duration) && (float) $duration > 0) {
                return round((float) $duration, 2);
            }
        }

        $script = data_get($input, 'voiceover_generation_summary.narration_script')
            ?? data_get($input, 'voiceover_generation_summary.source_generation_excerpt');

        if (! is_string($script) || trim($script) === '') {
            return null;
        }

        $wordCount = str_word_count(strip_tags($script));

        if ($wordCount <= 0) {
            return null;
        }

        return round(max(3.0, ($wordCount / 145) * 60), 2);
    }

    /** @param array<string, mixed> $analysis */
    private function cutCandidates(array $analysis): array
    {
        $groups = [
            data_get($analysis, 'edit_plan.timeline'),
            $analysis['recommended_cuts'] ?? null,
            $analysis['detected_product_moments'] ?? null,
            $analysis['scene_observations'] ?? null,
        ];
        $candidates = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            foreach ($group as $item) {
                if (is_array($item)) {
                    $candidates[] = $item;
                }
            }
        }

        return $candidates;
    }

    /** @return array<int, array<string, mixed>> */
    private function cuts(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $item): ?array {
            if (! is_array($item)) {
                return null;
            }

            return [
                'start_seconds' => $this->numeric($item['start_seconds'] ?? null),
                'end_seconds' => $this->numeric($item['end_seconds'] ?? null),
                'timestamp' => $this->nullableString($item['timestamp'] ?? null),
                'purpose' => $this->nullableString($item['purpose'] ?? $item['reason'] ?? $item['instruction'] ?? $item['observation'] ?? null),
                'visual_reason' => $this->nullableString($item['visual_reason'] ?? $item['campaign_use'] ?? $item['description'] ?? null),
                'caption' => $this->nullableString($item['caption'] ?? $item['caption_suggestion'] ?? $item['text_overlay'] ?? $item['on_screen_text'] ?? null),
                'priority' => $this->nullableString($item['priority'] ?? null) ?? 'medium',
                'visual_quality' => $this->nullableString($item['visual_quality'] ?? null),
                'stability' => $this->nullableString($item['stability'] ?? null),
                'product_visibility' => $this->nullableString($item['product_visibility'] ?? null),
                'is_transition_moment' => is_bool($item['is_transition_moment'] ?? null) ? $item['is_transition_moment'] : null,
                'use_for_final_edit' => is_bool($item['use_for_final_edit'] ?? null) ? $item['use_for_final_edit'] : (is_bool($item['usable_for_edit'] ?? null) ? $item['usable_for_edit'] : null),
            ];
        }, $value)));
    }

    /** @param array<string, mixed> $input */
    private function sourceDuration(array $input): ?float
    {
        foreach ([
            'source_video_duration_seconds',
            'source_video_asset.metadata.duration_seconds',
            'source_video_asset.metadata.duration',
            'source_video_asset.metadata.video.duration_seconds',
            'source_video_asset.metadata.video.duration',
        ] as $key) {
            $duration = data_get($input, $key);

            if (is_numeric($duration) && (float) $duration > 0) {
                return round((float) $duration, 2);
            }
        }

        return null;
    }

    private function duration(mixed $value, int $fallback, int $min, int $max): int
    {
        $duration = is_numeric($value) ? (int) $value : $fallback;

        return max($min, min($max, $duration));
    }

    private function numeric(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function string(mixed $value, string $fallback): string
    {
        return $this->nullableString($value) ?? $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : Str::limit($value, 300, '');
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
}
