<?php

namespace App\Services\Generation;

use App\Contracts\AutoVideoEditorProvider;
use App\Exceptions\AutoVideoEditor\AutoVideoEditorProviderException;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use App\Services\Media\TemporaryProviderAssetUrlService;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShotstackAutoVideoEditorProvider implements AutoVideoEditorProvider
{
    public function __construct(
        private readonly TemporaryProviderAssetUrlService $assetUrls,
        private readonly ShotstackTimelineBuilder $timelineBuilder,
        private readonly ShotstackAutoVideoEditorClient $client,
        private readonly GeneratedEditedVideoMediaService $videos,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array
    {
        $user = User::query()->findOrFail((int) ($input['user_id'] ?? 0));

        return $this->generateForUser($input, $user);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generateForUser(array $input, User $user): array
    {
        $startedAt = microtime(true);
        $generationId = (int) ($input['generation_id'] ?? 0);
        $project = $this->project($input, $user);
        $sourceVideo = $this->sourceVideo($input, $project, $user);
        $voiceoverAsset = $this->voiceoverAsset($input, $project, $user);
        $context = [
            'provider' => 'shotstack',
            'generation_id' => $generationId ?: null,
            'project_id' => $project->getKey(),
            'source_media_asset_id' => $sourceVideo->getKey(),
            'voiceover_asset_id' => $voiceoverAsset?->getKey(),
        ];

        $sourceVideoUrl = $this->assetUrls->create($sourceVideo, 'shotstack');
        $voiceoverUrl = $voiceoverAsset instanceof MediaAsset
            ? $this->assetUrls->create($voiceoverAsset, 'shotstack')
            : null;

        $timeline = $this->timelineBuilder->build($sourceVideoUrl, $voiceoverUrl, $input);
        $submit = $this->client->submitRender($timeline['edit'], $context);
        $renderId = (string) $submit['render_id'];
        $completion = $this->client->waitForCompletion($renderId, $context + ['render_id' => $renderId]);
        $downloaded = $this->client->download((string) $completion['output_url'], $context + [
            'render_id' => $renderId,
        ]);

        try {
            $finalVideo = $this->videos->store(
                user: $user,
                project: $project,
                generationId: $generationId,
                videoBinary: $downloaded,
                metadata: [
                    'render_id' => $renderId,
                    'source_video_asset_id' => $sourceVideo->getKey(),
                    'voiceover_asset_id' => $voiceoverAsset?->getKey(),
                    'source_video_to_campaign_generation_id' => $this->nullableInt($input['source_video_to_campaign_generation_id'] ?? null),
                    'timeline_summary' => $timeline['timeline_summary'] ?? null,
                    'render_status' => $completion['render_status'] ?? null,
                    'render_duration_ms' => $completion['render_time_ms'] ?? null,
                ],
            );
        } catch (Throwable $exception) {
            throw AutoVideoEditorProviderException::requestFailed(
                'shotstack_download_failed',
                $context + ['stage' => 'store', 'render_id' => $renderId],
                $exception,
            );
        }

        Log::info('Shotstack Auto Video Editor render completed.', [
            'provider' => 'shotstack',
            'generation_id' => $generationId ?: null,
            'project_id' => $project->getKey(),
            'render_id' => $renderId,
            'final_video_asset_id' => $finalVideo->getKey(),
        ]);

        return $this->outputPayload(
            input: $input,
            project: $project,
            sourceVideo: $sourceVideo,
            voiceoverAsset: $voiceoverAsset,
            finalVideo: $finalVideo,
            timeline: $timeline,
            submit: $submit,
            completion: $completion,
            providerLatencyMs: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function project(array $input, User $user): Project
    {
        $project = Project::query()
            ->whereKey((int) ($input['project_id'] ?? 0))
            ->where('user_id', $user->getKey())
            ->first();

        if (! $project instanceof Project) {
            throw AutoVideoEditorProviderException::invalidResponse('shotstack_invalid_response', [
                'provider' => 'shotstack',
                'stage' => 'resolve_project',
            ]);
        }

        return $project;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function sourceVideo(array $input, Project $project, User $user): MediaAsset
    {
        $asset = $user->mediaAssets()
            ->whereKey((int) ($input['source_video_asset_id'] ?? 0))
            ->where('project_id', $project->getKey())
            ->where('type', 'video')
            ->first();

        if (! $asset instanceof MediaAsset) {
            throw AutoVideoEditorProviderException::invalidResponse('shotstack_invalid_response', [
                'provider' => 'shotstack',
                'stage' => 'resolve_source_video',
                'source_media_asset_id' => $input['source_video_asset_id'] ?? null,
            ]);
        }

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function voiceoverAsset(array $input, Project $project, User $user): ?MediaAsset
    {
        $assetId = $this->nullableInt($input['voiceover_audio_asset_id'] ?? null)
            ?? $this->nullableInt(data_get($input, 'voiceover_generation_summary.audio_asset_id'));

        if ($assetId === null) {
            return null;
        }

        $asset = $user->mediaAssets()
            ->whereKey($assetId)
            ->where('project_id', $project->getKey())
            ->where('type', 'audio')
            ->first();

        if (! $asset instanceof MediaAsset) {
            throw AutoVideoEditorProviderException::invalidResponse('shotstack_invalid_response', [
                'provider' => 'shotstack',
                'stage' => 'resolve_voiceover',
                'voiceover_asset_id' => $assetId,
            ]);
        }

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $timeline
     * @param  array<string, mixed>  $submit
     * @param  array<string, mixed>  $completion
     * @return array<string, mixed>
     */
    private function outputPayload(
        array $input,
        Project $project,
        MediaAsset $sourceVideo,
        ?MediaAsset $voiceoverAsset,
        MediaAsset $finalVideo,
        array $timeline,
        array $submit,
        array $completion,
        int $providerLatencyMs,
    ): array {
        $analysisGenerationId = $this->nullableInt($input['source_video_to_campaign_generation_id'] ?? null);
        $voiceoverGenerationId = $this->nullableInt($input['voiceover_generation_id'] ?? null);
        $costBreakdown = is_array($input['cost_breakdown'] ?? null) ? $input['cost_breakdown'] : [];
        $renderSettings = is_array($timeline['render_settings'] ?? null) ? $timeline['render_settings'] : [];
        $timelineSummary = is_array($timeline['timeline_summary'] ?? null) ? $timeline['timeline_summary'] : [];
        $estimatedCost = config('auto_video_editor.shotstack.estimated_provider_cost_usd');
        $estimatedCost = is_numeric($estimatedCost) ? (float) $estimatedCost : null;

        return [
            'source_summary' => $this->sourceSummary($input, $sourceVideo),
            'selected_video_analysis_reference' => $analysisGenerationId !== null
                ? ['type' => 'video_to_campaign_generation', 'generation_id' => $analysisGenerationId, 'note' => 'Video campaign analysis reused for Shotstack render']
                : ['type' => 'raw_video_asset', 'media_asset_id' => $sourceVideo->getKey(), 'note' => 'Raw source video used for Shotstack render'],
            'selected_voiceover_reference' => $voiceoverAsset instanceof MediaAsset
                ? [
                    'type' => $voiceoverGenerationId !== null ? 'voiceover_generation' : 'voiceover_audio_asset',
                    'generation_id' => $voiceoverGenerationId,
                    'media_asset_id' => $voiceoverAsset->getKey(),
                    'note' => 'Existing voiceover audio reused',
                ]
                : ['type' => 'none', 'note' => 'No voiceover included'],
            'edit_decision_list' => $this->editDecisionList($timeline['timeline'] ?? []),
            'timeline' => $timeline['timeline'] ?? [],
            'clip_sequence' => $timeline['clip_sequence'] ?? [],
            'captions_plan' => $timeline['captions_plan'] ?? [],
            'transition_plan' => $timeline['transition_plan'] ?? [],
            'audio_mix_plan' => $timeline['audio_mix_plan'] ?? [],
            'voiceover_script' => data_get($input, 'voiceover_generation_summary.narration_script'),
            'render_settings' => $renderSettings,
            'final_video_asset_id' => $finalVideo->getKey(),
            'final_video_stream_url' => route('media-assets.stream', ['mediaAsset' => $finalVideo->getKey()], false),
            'final_video_download_url' => route('media-assets.download', ['mediaAsset' => $finalVideo->getKey()], false),
            'final_video_placeholder' => [
                'status' => 'rendered',
                'message' => 'Real video rendered and stored as a private project media asset.',
                'provider' => 'shotstack',
            ],
            'cost_breakdown' => $costBreakdown,
            'compliance_notes' => [
                'Review product claims against verified seller or listing facts before posting.',
                'Add affiliate disclosure before publishing promotional content.',
                'Use only footage, audio and music that the creator has rights to use.',
            ],
            'usage_notes' => array_values(array_filter([
                'This beta render uses simple clean cuts from the selected video analysis.',
                $voiceoverAsset instanceof MediaAsset
                    ? 'Voiceover was reused as an audio track; confirm timing before publishing.'
                    : 'No voiceover track was included in this render.',
                'Advanced transitions, music licensing and automatic new voiceover generation are planned later.',
            ])),
            'provider_metadata' => [
                'provider' => 'shotstack',
                'mode' => 'real',
                'render_id' => $submit['render_id'] ?? null,
                'render_status' => $completion['render_status'] ?? null,
                'output_duration' => $renderSettings['actual_duration_seconds'] ?? null,
                'output_size' => $finalVideo->size_bytes,
                'selected_clip_count' => $timelineSummary['selected_clip_count'] ?? null,
                'final_video_asset_id' => $finalVideo->getKey(),
                'provider_latency_ms' => $providerLatencyMs,
                'render_time_ms' => $completion['render_time_ms'] ?? null,
                'asset_url_ttl_minutes' => $this->assetUrls->ttlMinutes(),
                'estimated_provider_cost_usd' => $estimatedCost,
                'source_video_asset_id' => $sourceVideo->getKey(),
                'voiceover_asset_id' => $voiceoverAsset?->getKey(),
                'source_video_to_campaign_generation_id' => $analysisGenerationId,
                'timeline_summary' => $timelineSummary,
                'submit_latency_ms' => $submit['submit_latency_ms'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $timeline
     * @return array<int, array<string, mixed>>
     */
    private function editDecisionList(array $timeline): array
    {
        return collect($timeline)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->map(fn (array $item, int $index): array => [
                'order' => $index + 1,
                'timestamp' => $item['timestamp'] ?? null,
                'decision' => $item['instruction'] ?? 'Use selected source video moment.',
                'reason' => $index === 0
                    ? 'Open with a clear product moment.'
                    : 'Keep the beta render concise and readable.',
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function sourceSummary(array $input, MediaAsset $sourceVideo): string
    {
        $summary = data_get($input, 'source_video_to_campaign_summary.video_summary');

        return is_string($summary) && trim($summary) !== ''
            ? trim($summary)
            : 'Shotstack rendered a beta vertical edit from '.$sourceVideo->original_name.'.';
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
