<?php

namespace App\Data\Campaign;

class CampaignGenerationResultData
{
    /**
     * @param  array<int, array{label: string, text: string, intent: string}>  $hooks
     * @param  array<string, mixed>  $mainScript
     * @param  array<int, array<string, mixed>>  $sceneBreakdown
     * @param  array<int, string>  $captions
     * @param  array<int, string>  $hashtags
     * @param  array<int, string>  $ctaOptions
     * @param  array<int, string>  $complianceNotes
     * @param  array<int, string>  $usageNotes
     * @param  array<string, int>|null  $tokenUsage
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $provider,
        public readonly ?string $providerModel,
        public readonly string $campaignTitle,
        public readonly string $productAngle,
        public readonly string $targetAudienceSummary,
        public readonly array $hooks,
        public readonly array $mainScript,
        public readonly string $voiceoverReadyScript,
        public readonly array $sceneBreakdown,
        public readonly array $captions,
        public readonly array $hashtags,
        public readonly array $ctaOptions,
        public readonly array $complianceNotes,
        public readonly array $usageNotes,
        public readonly ?array $tokenUsage = null,
        public readonly ?float $estimatedCostUsd = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toOutputPayload(): array
    {
        $duration = is_numeric($this->mainScript['duration_seconds'] ?? null)
            ? (int) $this->mainScript['duration_seconds']
            : 30;
        $scenes = $this->normalizedScenes();
        $firstCaption = $this->captions[0] ?? '';
        $firstCta = $this->ctaOptions[0] ?? '';

        return [
            'campaign_title' => $this->campaignTitle,
            'product_angle' => $this->productAngle,
            'target_audience_summary' => $this->targetAudienceSummary,
            'hooks' => $this->hooks,
            'main_script' => [
                'duration_seconds' => $duration,
                'voiceover_script' => (string) ($this->mainScript['voiceover_script'] ?? $this->voiceoverReadyScript),
                'on_screen_text' => $this->stringList($this->mainScript['on_screen_text'] ?? []),
                'scenes' => $scenes,
            ],
            'voiceover_ready_script' => $this->voiceoverReadyScript,
            'scene_breakdown' => $this->sceneBreakdown,
            'captions' => $this->captions,
            'hashtags' => $this->hashtags,
            'cta_options' => $this->ctaOptions,
            'compliance_notes' => $this->complianceNotes,
            'usage_notes' => $this->usageNotes,
            'provider_metadata' => array_filter([
                'provider' => $this->provider,
                'model' => $this->providerModel,
                'token_usage' => $this->tokenUsage,
                'estimated_cost_usd' => $this->estimatedCostUsd,
                ...$this->metadata,
            ], fn (mixed $value): bool => $value !== null),

            // Backward-compatible aliases used by the first campaign renderer.
            'script' => [
                'title' => $this->campaignTitle,
                'duration_seconds' => $duration,
                'scenes' => array_map(fn (array $scene): array => [
                    'timestamp' => (string) ($scene['time_range'] ?? ''),
                    'voiceover' => (string) ($scene['voiceover'] ?? ''),
                    'visual_direction' => (string) ($scene['visual_direction'] ?? ''),
                    'on_screen_text' => (string) ($scene['on_screen_text'] ?? ''),
                ], $scenes),
            ],
            'caption' => $firstCaption,
            'cta' => $firstCta,
            'notes' => $this->usageNotes,
        ];
    }

    /**
     * @return array<int, array{time_range: string, visual_direction: string, voiceover: string, on_screen_text: string}>
     */
    private function normalizedScenes(): array
    {
        $scenes = $this->mainScript['scenes'] ?? [];

        if (! is_array($scenes)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $scene): ?array {
            if (! is_array($scene)) {
                return null;
            }

            return [
                'time_range' => (string) ($scene['time_range'] ?? $scene['timestamp'] ?? ''),
                'visual_direction' => (string) ($scene['visual_direction'] ?? ''),
                'voiceover' => (string) ($scene['voiceover'] ?? ''),
                'on_screen_text' => (string) ($scene['on_screen_text'] ?? ''),
            ];
        }, $scenes)));
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $value): ?string => is_scalar($value) ? trim((string) $value) : null,
            $values,
        ), fn (?string $value): bool => $value !== null && $value !== ''));
    }
}
