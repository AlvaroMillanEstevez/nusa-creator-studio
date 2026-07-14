<?php

namespace App\Services\Generation;

use App\Contracts\AutoVideoEditorProvider;
use App\Support\AutoVideoCaptionStyle;
use App\Support\AutoVideoEditSettings;
use Illuminate\Support\Str;

class MockAutoVideoEditorProvider implements AutoVideoEditorProvider
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array
    {
        $projectTitle = $this->value($input, 'project_title', 'Product video workspace', 160);
        $product = $this->value($input, 'product_name', $projectTitle, 160);
        $platform = $this->label($this->value($input, 'platform', 'tiktok', 80));
        $format = $this->label($this->value($input, 'format', 'vertical_9_16', 80));
        $style = $this->label($this->value($input, 'editing_style', 'ugc_natural', 80));
        $pacing = $this->label($this->value($input, 'pacing', 'medium', 80));
        $captionStyle = AutoVideoCaptionStyle::normalize($input['caption_style'] ?? null);
        $captionStyleLabel = $this->label($captionStyle);
        $duration = $this->intValue($input, 'target_duration_seconds', 30);
        $cta = $this->value($input, 'cta', 'Review the product details before buying.', 240);
        $music = $this->value($input, 'music_direction', 'Use light, creator-friendly background music if licensed.', 300);
        $includeVoiceover = (bool) ($input['include_voiceover'] ?? false);
        $sourceAudioMode = AutoVideoEditSettings::sourceAudioMode($input['source_audio_mode'] ?? null, $includeVoiceover);
        $voiceoverMix = AutoVideoEditSettings::voiceoverMix($input['voiceover_mix'] ?? null);
        $transitionStyle = AutoVideoEditSettings::transitionStyle($input['transition_style'] ?? null, $input['editing_style'] ?? null);
        $motionPolish = AutoVideoEditSettings::motionPolish($input['motion_polish'] ?? null);
        $clipDiversity = AutoVideoEditSettings::clipDiversity($input['clip_diversity'] ?? null);
        $endScreen = AutoVideoEditSettings::endScreen($input['end_screen'] ?? null, trim($cta) !== '');
        $audioMix = AutoVideoEditSettings::audioMix($sourceAudioMode, $voiceoverMix, $includeVoiceover);
        $editMode = $this->value($input, 'edit_mode', 'manual_edit', 80);
        $overlayStyle = AutoVideoEditSettings::overlayStyle($input['overlay_style'] ?? null, $editMode, $editMode === 'ai_video_analysis');
        $overlaysCount = $overlayStyle === AutoVideoEditSettings::OVERLAY_MINIMAL_CLEAN ? ($endScreen === AutoVideoEditSettings::END_SCREEN_NONE ? 0 : 1) : 3;
        $captionsCount = 3;
        $fallbackTimeline = (bool) ($input['fallback_timeline'] ?? $editMode === 'manual_edit');
        $aiVideoAnalysisUsed = (bool) ($input['ai_video_analysis_used'] ?? $editMode === 'ai_video_analysis');
        $analysisGenerationId = $this->intValue($input, 'source_video_to_campaign_generation_id', 0);
        $voiceoverGenerationId = $this->intValue($input, 'voiceover_generation_id', 0);
        $voiceoverAudioAssetId = $this->intValue($input, 'voiceover_audio_asset_id', 0);
        $sourceVideoAssetId = $this->intValue($input, 'source_video_asset_id', 0);
        $analysisReference = $analysisGenerationId > 0
            ? ['type' => 'video_to_campaign_generation', 'generation_id' => $analysisGenerationId, 'note' => 'Video campaign analysis reused']
            : ['type' => 'manual_source_video', 'media_asset_id' => $sourceVideoAssetId ?: null, 'note' => 'Manual source video used with fallback timeline'];
        $voiceoverReference = $this->voiceoverReference($includeVoiceover, $voiceoverGenerationId, $voiceoverAudioAssetId);
        $costBreakdown = is_array($input['cost_breakdown'] ?? null) ? $input['cost_breakdown'] : [];
        $sourceSummary = $this->sourceSummary($input, $product);

        return [
            'source_summary' => $sourceSummary,
            'selected_video_analysis_reference' => $analysisReference,
            'selected_voiceover_reference' => $voiceoverReference,
            'edit_decision_list' => [
                ['order' => 1, 'timestamp' => '0-2s', 'decision' => 'Open on the clearest product frame with a direct hook.', 'reason' => 'Immediate context reduces swipe-away risk.'],
                ['order' => 2, 'timestamp' => '2-8s', 'decision' => "Use {$pacing} cuts to show the product context.", 'reason' => 'The middle should feel informative without being overloaded.'],
                ['order' => 3, 'timestamp' => '8-18s', 'decision' => "Add {$captionStyleLabel} captions over the strongest explanation beats.", 'reason' => 'Many social commerce viewers watch muted first.'],
                ['order' => 4, 'timestamp' => '18-'.$duration.'s', 'decision' => "Close with CTA: {$cta}", 'reason' => 'The ending should tell viewers the safe next step.'],
            ],
            'timeline' => [
                ['timestamp' => '0-3s', 'layer' => 'video', 'instruction' => "Hook shot for {$product} in {$format}.", 'text_overlay' => "Start with {$product}"],
                ['timestamp' => '3-10s', 'layer' => 'video', 'instruction' => 'Show usage/context clips and keep product visible.', 'text_overlay' => 'What to notice'],
                ['timestamp' => '10-20s', 'layer' => 'caption', 'instruction' => 'Place captions in mobile safe zones.', 'text_overlay' => 'Check the details'],
                ['timestamp' => '20-'.$duration.'s', 'layer' => 'cta', 'instruction' => 'End on a stable product frame.', 'text_overlay' => $cta],
            ],
            'clip_sequence' => [
                ['clip' => 'A-roll', 'source' => 'source video', 'use' => 'opening hook and product reveal'],
                ['clip' => 'Detail shot', 'source' => 'source video or still frame', 'use' => 'support product description without exaggerating claims'],
                ['clip' => 'CTA card', 'source' => 'generated edit plan', 'use' => 'final mobile-safe call to action'],
            ],
            'captions_plan' => [
                'style' => $captionStyle,
                'caption_count' => $captionsCount,
                'safe_zone' => 'Keep lower third clear of shopping buttons and affiliate badges.',
                'sample_captions' => [
                    "Meet {$product}",
                    'What the creator noticed',
                    'Review the product details',
                ],
            ],
            'transition_plan' => [
                'Primary cuts should be clean and short.',
                'Use one subtle zoom or push-in only when product detail is visible.',
                'Avoid flashy transitions that hide product information.',
            ],
            'audio_mix_plan' => [
                'include_voiceover' => $includeVoiceover,
                'voiceover_reference' => $voiceoverReference,
                'source_audio_mode' => $sourceAudioMode,
                'source_audio_volume' => $audioMix['source_audio_volume'],
                'voiceover_mix' => $voiceoverMix,
                'voiceover_volume' => $audioMix['voiceover_volume'],
                'audio_cleanup_strategy' => $audioMix['audio_cleanup_strategy'],
                'original_audio_muted' => $audioMix['original_audio_muted'],
                'music_direction' => $music,
                'mix_notes' => $this->audioMixNotes($sourceAudioMode, $voiceoverMix, $includeVoiceover),
            ],
            'voiceover_script' => $includeVoiceover
                ? $this->voiceoverScript($product, $sourceSummary, $cta)
                : null,
            'render_settings' => [
                'platform' => $platform,
                'format' => $format,
                'target_duration_seconds' => $duration,
                'editing_style' => $style,
                'pacing' => $pacing,
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
                'overlay_style' => $overlayStyle,
                'overlays_count' => $overlaysCount,
                'captions_count' => $captionsCount,
                'end_screen_type' => $endScreen,
                'clip_strategy' => $editMode.'_'.$clipDiversity.'_'.$transitionStyle,
                'edit_recipe' => [
                    'edit_mode' => $editMode,
                    'overlay_style' => $overlayStyle,
                    'transition_style' => $transitionStyle,
                    'motion_polish' => $motionPolish,
                    'clip_diversity' => $clipDiversity,
                    'end_screen' => $endScreen,
                    'source_audio' => $sourceAudioMode,
                    'voiceover_mix' => $voiceoverMix,
                    'captions_used' => $captionsCount,
                    'overlays_used' => $overlaysCount,
                    'unique_clips_used' => $editMode === 'manual_edit' ? 3 : 4,
                ],
                'edit_mode' => $editMode,
                'ai_video_analysis_used' => $aiVideoAnalysisUsed,
                'fallback_timeline' => $fallbackTimeline,
            ],
            'timeline_summary' => [
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
                'overlays_count' => $overlaysCount,
                'captions_count' => $captionsCount,
                'end_screen_type' => $endScreen,
                'clip_strategy' => $editMode.'_'.$clipDiversity.'_'.$transitionStyle,
                'edit_recipe' => [
                    'edit_mode' => $editMode,
                    'overlay_style' => $overlayStyle,
                    'transition_style' => $transitionStyle,
                    'motion_polish' => $motionPolish,
                    'clip_diversity' => $clipDiversity,
                    'end_screen' => $endScreen,
                    'source_audio' => $sourceAudioMode,
                    'voiceover_mix' => $voiceoverMix,
                    'captions_used' => $captionsCount,
                    'overlays_used' => $overlaysCount,
                    'unique_clips_used' => $editMode === 'manual_edit' ? 3 : 4,
                ],
                'edit_mode' => $editMode,
                'ai_video_analysis_used' => $aiVideoAnalysisUsed,
                'fallback_timeline' => $fallbackTimeline,
            ],
            'final_video_placeholder' => [
                'status' => 'placeholder',
                'message' => 'Mock edit plan only. Real rendering will be connected later.',
                'provider' => 'mock',
            ],
            'cost_breakdown' => $costBreakdown,
            'compliance_notes' => [
                'Review all product claims against the original listing or seller-provided facts.',
                'Add affiliate disclosure before posting promotional content.',
                'Use only source footage, audio and music that the creator has rights to use.',
            ],
            'usage_notes' => [
                'This is an edit-ready plan, not a rendered video file.',
                $editMode === 'manual_edit'
                    ? 'Manual edit setup used a fallback timeline from the selected source video and settings.'
                    : 'The selected video analysis and voiceover references remain traceable in this project.',
                'Use this output to guide a real editor or a future rendering provider integration.',
            ],
            'provider_metadata' => [
                'provider' => 'mock',
                'mode' => 'deterministic_auto_video_edit_plan',
                'real_rendering' => false,
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
                'overlays_count' => $overlaysCount,
                'captions_count' => $captionsCount,
                'end_screen_type' => $endScreen,
                'clip_strategy' => $editMode.'_'.$clipDiversity.'_'.$transitionStyle,
                'edit_recipe' => [
                    'edit_mode' => $editMode,
                    'overlay_style' => $overlayStyle,
                    'transition_style' => $transitionStyle,
                    'motion_polish' => $motionPolish,
                    'clip_diversity' => $clipDiversity,
                    'end_screen' => $endScreen,
                    'source_audio' => $sourceAudioMode,
                    'voiceover_mix' => $voiceoverMix,
                    'captions_used' => $captionsCount,
                    'overlays_used' => $overlaysCount,
                    'unique_clips_used' => $editMode === 'manual_edit' ? 3 : 4,
                ],
                'edit_mode' => $editMode,
                'ai_video_analysis_used' => $aiVideoAnalysisUsed,
                'fallback_timeline' => $fallbackTimeline,
                'source_video_asset_id' => $sourceVideoAssetId ?: null,
                'source_video_to_campaign_generation_id' => $analysisGenerationId ?: null,
            ],
        ];
    }

    /** @return array<int, string> */
    private function audioMixNotes(string $sourceAudioMode, string $voiceoverMix, bool $includeVoiceover): array
    {
        if ($sourceAudioMode === AutoVideoEditSettings::SOURCE_AUDIO_MUTE) {
            return $includeVoiceover
                ? ['Original camera audio is muted for a cleaner voiceover-focused mock plan.', 'Beta cleanup controls volume and mixing; advanced AI denoise will come later.']
                : ['Original camera audio is muted in the mock plan.', 'Beta cleanup controls volume and mixing; advanced AI denoise will come later.'];
        }

        $notes = match ($sourceAudioMode) {
            AutoVideoEditSettings::SOURCE_AUDIO_PRODUCT_SOUNDS => ['Keep useful product handling sounds subtle under the main message.'],
            AutoVideoEditSettings::SOURCE_AUDIO_KEEP => ['Keep the original source audio, reducing it if voiceover is present.'],
            default => ['Keep original source audio low so it does not overpower the edit.'],
        };

        if ($includeVoiceover) {
            $notes[] = match ($voiceoverMix) {
                AutoVideoEditSettings::VOICEOVER_MIX_BALANCED => 'Use a balanced voiceover/source mix.',
                AutoVideoEditSettings::VOICEOVER_MIX_SOFT_BACKGROUND => 'Use a softer voiceover with low source ambience.',
                default => 'Keep the voiceover dominant and clear.',
            };
        }

        $notes[] = 'No music generation or TikTok music selection is included in this beta step.';

        return $notes;
    }

    /** @return array<string, mixed> */
    private function voiceoverReference(bool $includeVoiceover, int $voiceoverGenerationId, int $voiceoverAudioAssetId): array
    {
        if (! $includeVoiceover) {
            return ['type' => 'none', 'note' => 'No voiceover included'];
        }

        if ($voiceoverGenerationId > 0) {
            return ['type' => 'voiceover_generation', 'generation_id' => $voiceoverGenerationId, 'note' => 'Existing voiceover reused'];
        }

        if ($voiceoverAudioAssetId > 0) {
            return ['type' => 'voiceover_audio_asset', 'media_asset_id' => $voiceoverAudioAssetId, 'note' => 'Existing voiceover audio reused'];
        }

        return ['type' => 'planned_mock_voiceover', 'note' => 'Includes new AI voiceover'];
    }

    /** @param array<string, mixed> $input */
    private function sourceSummary(array $input, string $product): string
    {
        $analysis = $input['source_video_to_campaign_summary'] ?? null;

        if (is_array($analysis) && is_string($analysis['video_summary'] ?? null)) {
            return $analysis['video_summary'];
        }

        $asset = $input['source_video_asset'] ?? [];
        $name = is_array($asset) && is_string($asset['original_name'] ?? null)
            ? $asset['original_name']
            : 'uploaded source video';

        return "Use {$name} as the raw source footage for {$product}. This mock edit plan assumes product shots are available but does not render or inspect the video frames yet.";
    }

    private function voiceoverScript(string $product, string $sourceSummary, string $cta): string
    {
        return "Start with {$product} on screen. Explain the product context simply, using only details you can verify. {$sourceSummary} End with: {$cta}";
    }

    /** @param array<string, mixed> $input */
    private function value(array $input, string $key, string $fallback, int $limit): string
    {
        $value = $input[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }

        return Str::limit(trim($value), $limit, '');
    }

    /** @param array<string, mixed> $input */
    private function intValue(array $input, string $key, int $fallback): int
    {
        $value = $input[$key] ?? null;

        return is_numeric($value) ? (int) $value : $fallback;
    }

    private function label(string $value): string
    {
        return str_replace('_', ' ', $value);
    }
}
