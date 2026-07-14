<?php

namespace App\Support;

class CampaignWorkflowMetadata
{
    public const MODE_BRIEF_ONLY = 'brief_only';

    public const MODE_IMAGE_ASSISTED = 'image_assisted';

    public const MODE_VIDEO_ANALYSIS = 'video_analysis';

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function forProductContentKit(array $input, ?int $sourceImageAssetId = null): array
    {
        $imageAssetId = $sourceImageAssetId
            ?? self::nullableInt($input['source_image_asset_id'] ?? null)
            ?? self::nullableInt($input['product_image_asset_id'] ?? null);
        $mode = $imageAssetId === null ? self::MODE_BRIEF_ONLY : self::MODE_IMAGE_ASSISTED;

        return [
            'campaign_input_mode' => $mode,
            'has_source_video' => false,
            'source_video_asset_id' => null,
            'source_image_asset_id' => $imageAssetId,
            'has_edit_plan' => false,
            'auto_video_editor_eligible' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $output
     * @return array<string, mixed>
     */
    public static function forVideoAnalysis(array $output, ?int $sourceVideoAssetId = null): array
    {
        $videoAssetId = $sourceVideoAssetId
            ?? self::nullableInt($output['source_video_asset_id'] ?? null);
        $hasEditPlan = self::hasUsableEditPlan($output);

        return [
            'campaign_input_mode' => self::MODE_VIDEO_ANALYSIS,
            'has_source_video' => $videoAssetId !== null,
            'source_video_asset_id' => $videoAssetId,
            'source_image_asset_id' => null,
            'has_edit_plan' => $hasEditPlan,
            'auto_video_editor_eligible' => $videoAssetId !== null && $hasEditPlan,
        ];
    }

    /** @param array<string, mixed> $output */
    public static function isAutoVideoEditorEligible(array $output): bool
    {
        $metadata = is_array($output['provider_metadata'] ?? null)
            ? $output['provider_metadata']
            : [];
        $mode = $output['campaign_input_mode']
            ?? $metadata['campaign_input_mode']
            ?? null;
        $explicitlyEligible = ($output['auto_video_editor_eligible'] ?? null) === true
            || ($metadata['auto_video_editor_eligible'] ?? null) === true;
        $sourceVideoAssetId = self::nullableInt($output['source_video_asset_id'] ?? $metadata['source_video_asset_id'] ?? null);

        return ($mode === self::MODE_VIDEO_ANALYSIS || $explicitlyEligible)
            && $sourceVideoAssetId !== null
            && self::hasUsableEditPlan($output);
    }

    /** @param array<string, mixed> $output */
    public static function hasUsableEditPlan(array $output): bool
    {
        $editPlanTimeline = data_get($output, 'edit_plan.timeline');
        $recommendedCuts = $output['recommended_cuts'] ?? null;

        return (is_array($editPlanTimeline) && count($editPlanTimeline) > 0)
            || (is_array($recommendedCuts) && count($recommendedCuts) > 0);
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
