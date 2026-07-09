<?php

namespace App\Services;

use App\Models\CreatorTool;

class ToolAvailabilityService
{
    private const PROJECT_TYPE_TO_TOOL_KEY = [
        'workspace' => null,
        'affiliate_campaign' => 'affiliate_campaign',
        'video_enhancer' => 'video_enhancer',
        'product_to_video' => 'product_to_video',
        'ai_voiceover' => 'ai_voiceover',
        'fashion_try_on' => 'fashion_try_on',
        'motion_ai_video' => 'motion_ai_video',
    ];

    public function isAvailableForProjectType(string $projectType): bool
    {
        $key = self::PROJECT_TYPE_TO_TOOL_KEY[$projectType] ?? null;

        return array_key_exists($projectType, self::PROJECT_TYPE_TO_TOOL_KEY)
            && ($key === null || $this->isAvailable($key));
    }

    public function isAvailable(string $key): bool
    {
        return CreatorTool::query()
            ->where('key', $key)
            ->where('status', 'available')
            ->exists();
    }

    public function creditCost(string $key): int
    {
        $cost = CreatorTool::query()
            ->where('key', $key)
            ->value('credit_cost');

        if ($cost === null) {
            throw new \DomainException("Creator tool [{$key}] is not configured.");
        }

        return (int) $cost;
    }

    public function keyForProjectType(string $projectType): ?string
    {
        return self::PROJECT_TYPE_TO_TOOL_KEY[$projectType] ?? null;
    }
}
