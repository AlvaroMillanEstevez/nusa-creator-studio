<?php

namespace App\Services\Generation;

use App\Jobs\GenerateAutoVideoEditJob;
use App\Models\Generation;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\ToolAvailabilityService;
use App\Support\AutoVideoCaptionStyle;
use App\Support\AutoVideoEditSettings;
use App\Support\CampaignWorkflowMetadata;
use Illuminate\Validation\ValidationException;

class AutoVideoEditorGenerationService
{
    public function __construct(
        private readonly GenerationSubmissionService $submissions,
        private readonly ToolAvailabilityService $tools,
        private readonly AutoVideoEditorProviderManager $providers,
        private readonly AutoVideoEditorCreativeBriefBuilder $creativeBriefs,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(Project $project, User $user, array $options = []): Generation
    {
        $editMode = $this->editMode($options);
        $analysisGeneration = $editMode === 'ai_video_analysis'
            ? $this->sourceVideoCampaignGeneration($project, $user, $options)
            : null;
        $sourceVideo = $analysisGeneration instanceof Generation
            ? $this->sourceVideoFromAnalysis($analysisGeneration, $project, $user)
            : $this->sourceVideoAsset($project, $user, $options, requireExplicit: $editMode === 'manual_edit');

        if (! $sourceVideo instanceof MediaAsset) {
            throw ValidationException::withMessages([
                'source_video_to_campaign_generation_id' => 'Selected campaign does not include video analysis data. Choose an Analyze Footage result or use Manual edit setup.',
            ]);
        }
        $voiceoverGeneration = $this->voiceoverGeneration($project, $user, $options);
        $voiceoverAudioAsset = $this->voiceoverAudioAsset($project, $user, $options);
        $includeVoiceover = (bool) ($options['include_voiceover'] ?? false);
        $selectedProvider = $this->providers->selectedProviderForUser($user);
        $hasExistingVoiceover = $voiceoverGeneration instanceof Generation || $voiceoverAudioAsset instanceof MediaAsset;

        if ($selectedProvider === 'shotstack' && $includeVoiceover && ! $this->hasReusableVoiceoverAudio($project, $user, $voiceoverGeneration, $voiceoverAudioAsset)) {
            throw ValidationException::withMessages([
                'voiceover_generation_id' => 'Generate a voiceover first, then reuse it in Auto Video Editor.',
            ]);
        }

        $costBreakdown = $this->costBreakdown(
            editMode: $editMode,
            hasVideoAnalysis: $analysisGeneration instanceof Generation,
            includeVoiceover: $includeVoiceover,
            hasExistingVoiceover: $hasExistingVoiceover,
            provider: $selectedProvider,
        );

        return $this->submissions->submit(
            project: $project,
            user: $user,
            jobType: 'generate_auto_video_edit',
            creditCost: (int) $costBreakdown['total_credits'],
            creditReason: $selectedProvider === 'shotstack' ? 'Auto Video Editor render' : 'Mock Auto Video Editor generation',
            dispatch: fn (int $generationId) => GenerateAutoVideoEditJob::dispatch($generationId),
            inputPayload: $this->inputPayload(
                project: $project,
                sourceVideo: $sourceVideo,
                analysisGeneration: $analysisGeneration,
                editMode: $editMode,
                voiceoverGeneration: $voiceoverGeneration,
                voiceoverAudioAsset: $voiceoverAudioAsset,
                includeVoiceover: $includeVoiceover,
                costBreakdown: $costBreakdown,
                options: $options,
            ),
            provider: $selectedProvider,
        );
    }

    /** @param array<string, mixed> $options */
    private function sourceVideoCampaignGeneration(Project $project, User $user, array $options): Generation
    {
        $id = $options['source_video_to_campaign_generation_id'] ?? null;

        if ($id === null || $id === '') {
            throw ValidationException::withMessages([
                'source_video_to_campaign_generation_id' => 'Choose an Analyze Footage result or use Manual edit setup.',
            ]);
        }

        $generation = $user->generations()
            ->whereKey((int) $id)
            ->where('project_id', $project->getKey())
            ->where('status', Generation::STATUS_COMPLETED)
            ->first();

        if (! $generation instanceof Generation) {
            throw ValidationException::withMessages([
                'source_video_to_campaign_generation_id' => 'The selected video campaign analysis is unavailable for this project.',
            ]);
        }

        $output = is_array($generation->output_payload) ? $generation->output_payload : [];
        $providerMetadata = is_array($output['provider_metadata'] ?? null) ? $output['provider_metadata'] : [];
        $mode = $output['campaign_input_mode'] ?? $providerMetadata['campaign_input_mode'] ?? null;
        $isVideoAnalysis = $generation->job_type === 'generate_video_to_campaign'
            || $mode === CampaignWorkflowMetadata::MODE_VIDEO_ANALYSIS;

        if (! $isVideoAnalysis || ! CampaignWorkflowMetadata::isAutoVideoEditorEligible($output)) {
            throw ValidationException::withMessages([
                'source_video_to_campaign_generation_id' => 'Selected campaign does not include video analysis data. Choose an Analyze Footage result or use Manual edit setup.',
            ]);
        }

        return $generation;
    }

    private function sourceVideoFromAnalysis(Generation $generation, Project $project, User $user): ?MediaAsset
    {
        $assetId = $generation->output_payload['source_video_asset_id']
            ?? $generation->input_payload['source_video_asset_id']
            ?? null;

        if ($assetId === null || $assetId === '') {
            return null;
        }

        $asset = $user->mediaAssets()
            ->whereKey((int) $assetId)
            ->where('project_id', $project->getKey())
            ->where('type', 'video')
            ->first();

        return $asset instanceof MediaAsset ? $asset : null;
    }

    /** @param array<string, mixed> $options */
    private function sourceVideoAsset(Project $project, User $user, array $options, bool $requireExplicit = false): MediaAsset
    {
        $sourceVideoId = $options['source_video_asset_id'] ?? null;

        if ($sourceVideoId !== null && $sourceVideoId !== '') {
            $asset = $user->mediaAssets()
                ->whereKey((int) $sourceVideoId)
                ->where('project_id', $project->getKey())
                ->where('type', 'video')
                ->first();

            if ($asset instanceof MediaAsset) {
                return $asset;
            }

            throw ValidationException::withMessages([
                'source_video_asset_id' => 'The selected source video is unavailable for this project.',
            ]);
        }

        if ($requireExplicit) {
            throw ValidationException::withMessages([
                'source_video_asset_id' => 'Manual edit setup requires a source video.',
            ]);
        }

        $asset = $project->mediaAssets()
            ->where('type', 'video')
            ->whereIn('usage_role', ['source_video', 'raw_product_video', 'campaign_source_video', 'video_reference', 'general'])
            ->latest('created_at')
            ->latest('id')
            ->first();

        if ($asset instanceof MediaAsset) {
            return $asset;
        }

        throw ValidationException::withMessages([
            'source_video_asset_id' => 'Select a raw video or choose an existing video campaign analysis before generating an edit plan.',
        ]);
    }

    /** @param array<string, mixed> $options */
    private function voiceoverGeneration(Project $project, User $user, array $options): ?Generation
    {
        $id = $options['voiceover_generation_id'] ?? null;

        if ($id === null || $id === '') {
            return null;
        }

        $generation = $user->generations()
            ->whereKey((int) $id)
            ->where('project_id', $project->getKey())
            ->where('job_type', 'generate_ai_voiceover')
            ->where('status', Generation::STATUS_COMPLETED)
            ->first();

        if ($generation instanceof Generation) {
            return $generation;
        }

        throw ValidationException::withMessages([
            'voiceover_generation_id' => 'The selected voiceover generation is unavailable for this project.',
        ]);
    }

    /** @param array<string, mixed> $options */
    private function voiceoverAudioAsset(Project $project, User $user, array $options): ?MediaAsset
    {
        $id = $options['voiceover_audio_asset_id'] ?? null;

        if ($id === null || $id === '') {
            return null;
        }

        $asset = $user->mediaAssets()
            ->whereKey((int) $id)
            ->where('project_id', $project->getKey())
            ->where('type', 'audio')
            ->first();

        if ($asset instanceof MediaAsset) {
            return $asset;
        }

        throw ValidationException::withMessages([
            'voiceover_audio_asset_id' => 'The selected voiceover audio asset is unavailable for this project.',
        ]);
    }

    private function hasReusableVoiceoverAudio(Project $project, User $user, ?Generation $generation, ?MediaAsset $asset): bool
    {
        if ($asset instanceof MediaAsset) {
            return true;
        }

        $assetId = $generation instanceof Generation
            ? ($generation->output_payload['audio_asset_id'] ?? null)
            : null;

        if ($assetId === null || $assetId === '') {
            return false;
        }

        return $user->mediaAssets()
            ->whereKey((int) $assetId)
            ->where('project_id', $project->getKey())
            ->where('type', 'audio')
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function costBreakdown(string $editMode, bool $hasVideoAnalysis, bool $includeVoiceover, bool $hasExistingVoiceover, string $provider): array
    {
        $baseEditorCost = $this->tools->creditCost('auto_video_editor');
        $videoAnalysisCost = 0;
        $voiceoverCost = $includeVoiceover && ! $hasExistingVoiceover
            ? $this->tools->creditCost('ai_voiceover')
            : 0;
        $renderCost = 0;

        return [
            'base_editor_cost' => $baseEditorCost,
            'base_editor_note' => $provider === 'shotstack' ? 'Auto Video Editor beta render workflow' : 'Auto Video Editor mock planning workflow',
            'video_analysis_cost' => $videoAnalysisCost,
            'video_analysis_note' => $editMode === 'ai_video_analysis' && $hasVideoAnalysis
                ? 'Video campaign analysis reused'
                : 'Manual edit setup uses fallback timeline',
            'voiceover_cost' => $voiceoverCost,
            'voiceover_note' => ! $includeVoiceover
                ? 'No voiceover included'
                : ($hasExistingVoiceover ? 'Existing voiceover reused' : 'Includes new AI voiceover'),
            'render_cost_placeholder' => $renderCost,
            'render_note' => $provider === 'shotstack' ? 'Real Shotstack render is enabled for this beta user' : 'Mock edit plan only; real rendering will be connected later',
            'total_credits' => $baseEditorCost + $videoAnalysisCost + $voiceoverCost + $renderCost,
        ];
    }

    /**
     * @param  array<string, mixed>  $costBreakdown
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function inputPayload(
        Project $project,
        ?MediaAsset $sourceVideo,
        ?Generation $analysisGeneration,
        string $editMode,
        ?Generation $voiceoverGeneration,
        ?MediaAsset $voiceoverAudioAsset,
        bool $includeVoiceover,
        array $costBreakdown,
        array $options,
    ): array {
        $projectInput = is_array($project->input_payload) ? $project->input_payload : [];
        $settings = is_array($project->settings) ? $project->settings : [];

        $requestedTargetDuration = $this->intOption($options, 'requested_target_duration_seconds')
            ?? $this->intOption($options, 'target_duration_seconds')
            ?? 30;
        $effectiveTargetDuration = max(
            $requestedTargetDuration,
            $this->intOption($options, 'effective_target_duration_seconds')
                ?? $this->intOption($options, 'target_duration_seconds')
                ?? $requestedTargetDuration,
        );
        $editingStyle = $this->stringOption($options, 'editing_style') ?? 'ugc_natural';
        $captionStyle = AutoVideoCaptionStyle::normalize($this->stringOption($options, 'caption_style'));
        $cta = $this->stringOption($options, 'cta');
        $sourceAudioMode = AutoVideoEditSettings::sourceAudioMode($this->stringOption($options, 'source_audio_mode'), $includeVoiceover);
        $voiceoverMix = AutoVideoEditSettings::voiceoverMix($this->stringOption($options, 'voiceover_mix'));
        $transitionStyle = AutoVideoEditSettings::transitionStyle($this->stringOption($options, 'transition_style'), $editingStyle);
        $motionPolish = AutoVideoEditSettings::motionPolish($this->stringOption($options, 'motion_polish'));
        $clipDiversity = AutoVideoEditSettings::clipDiversity($this->stringOption($options, 'clip_diversity'));
        $endScreen = AutoVideoEditSettings::endScreen($this->stringOption($options, 'end_screen'), $cta !== null);
        $hasProductMoments = $analysisGeneration instanceof Generation
            && is_array($analysisGeneration->output_payload)
            && ($analysisGeneration->output_payload['detected_product_moments'] ?? []) !== [];
        $overlayStyle = AutoVideoEditSettings::overlayStyle($this->stringOption($options, 'overlay_style'), $editMode, $hasProductMoments);

        $payload = array_merge($projectInput, [
            'project_title' => $project->title,
            'project_type' => $project->type,
            'edit_mode' => $editMode,
            'ai_video_analysis_used' => $editMode === 'ai_video_analysis' && $analysisGeneration instanceof Generation,
            'fallback_timeline' => $editMode === 'manual_edit',
            'source_video_asset_id' => $sourceVideo?->getKey(),
            'source_video_asset' => $sourceVideo instanceof MediaAsset ? $this->assetSummary($sourceVideo) : null,
            'source_video_to_campaign_generation_id' => $analysisGeneration?->getKey(),
            'source_video_to_campaign_summary' => $analysisGeneration?->output_payload,
            'voiceover_generation_id' => $voiceoverGeneration?->getKey(),
            'voiceover_generation_summary' => $voiceoverGeneration?->output_payload,
            'voiceover_audio_asset_id' => $voiceoverAudioAsset?->getKey(),
            'voiceover_audio_asset' => $voiceoverAudioAsset instanceof MediaAsset ? $this->assetSummary($voiceoverAudioAsset) : null,
            'include_voiceover' => $includeVoiceover,
            'platform' => $this->stringOption($options, 'platform') ?? $this->stringOption($settings, 'platform') ?? 'tiktok',
            'format' => $this->stringOption($options, 'format') ?? $this->stringOption($settings, 'format') ?? 'vertical_9_16',
            'editing_style' => $editingStyle,
            'pacing' => $this->stringOption($options, 'pacing') ?? 'medium',
            'caption_style' => $captionStyle,
            'source_audio_mode' => $sourceAudioMode,
            'voiceover_mix' => $voiceoverMix,
            'transition_style' => $transitionStyle,
            'motion_polish' => $motionPolish,
            'clip_diversity' => $clipDiversity,
            'end_screen' => $endScreen,
            'overlay_style' => $overlayStyle,
            'requested_target_duration_seconds' => $requestedTargetDuration,
            'effective_target_duration_seconds' => $effectiveTargetDuration,
            'target_duration_seconds' => $requestedTargetDuration,
            'cta' => $cta,
            'music_direction' => $this->stringOption($options, 'music_direction'),
            'notes' => $this->stringOption($options, 'notes'),
            'cost_breakdown' => $costBreakdown,
        ]);

        $payload['creative_brief'] = $this->creativeBriefs->build($payload);

        return $payload;
    }

    /** @param array<string, mixed> $options */
    private function editMode(array $options): string
    {
        $mode = $this->stringOption($options, 'edit_mode');

        if ($mode === 'ai_video_analysis' || $mode === 'manual_edit') {
            return $mode;
        }

        return ($options['source_video_to_campaign_generation_id'] ?? null) !== null
            && ($options['source_video_to_campaign_generation_id'] ?? '') !== ''
                ? 'ai_video_analysis'
                : 'manual_edit';
    }

    /** @param array<string, mixed> $input */
    private function stringOption(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /** @param array<string, mixed> $input */
    private function intOption(array $input, string $key): ?int
    {
        $value = $input[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function assetSummary(MediaAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'usage_role' => $asset->usage_role,
            'type' => $asset->type,
            'original_name' => $asset->original_name,
            'mime_type' => $asset->mime_type,
            'size_bytes' => $asset->size_bytes,
            'metadata' => $asset->metadata,
        ];
    }
}
