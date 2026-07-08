<?php

namespace App\Data\Campaign;

use App\Models\Generation;
use App\Models\User;

class CampaignGenerationRequestData
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public readonly string $productName,
        public readonly string $productDescription,
        public readonly string $targetAudience,
        public readonly string $language,
        public readonly string $tone,
        public readonly string $platform,
        public readonly string $goal,
        public readonly ?string $notes,
        public readonly int $userId,
        public readonly int $projectId,
        public readonly int $generationId,
        public readonly bool $realProviderAllowed,
        public readonly array $input,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromGeneration(Generation $generation, User $user, array $input): self
    {
        return new self(
            productName: self::stringValue($input['product_name'] ?? null) ?? 'Featured product',
            productDescription: self::stringValue($input['product_description'] ?? null)
                ?? 'A practical product for short-form content.',
            targetAudience: self::stringValue($input['target_audience'] ?? null)
                ?? 'social commerce viewers',
            language: strtolower(self::stringValue($input['language'] ?? null) ?? 'english'),
            tone: strtolower(self::stringValue($input['tone'] ?? null) ?? 'natural'),
            platform: strtolower(self::stringValue($input['platform'] ?? null) ?? 'tiktok'),
            goal: strtolower(self::stringValue($input['goal'] ?? null) ?? 'affiliate_sale'),
            notes: self::stringValue($input['notes'] ?? null),
            userId: (int) $user->getKey(),
            projectId: (int) $generation->project_id,
            generationId: (int) $generation->getKey(),
            realProviderAllowed: $user->isAdmin()
                || $user->isInternalCreator()
                || $user->hasUnlimitedAccess()
                || $user->plan_key === 'internal_unlimited',
            input: $input,
        );
    }

    public function inputCharacterCount(): int
    {
        return mb_strlen(trim(implode("\n", array_filter([
            $this->productName,
            $this->productDescription,
            $this->targetAudience,
            $this->language,
            $this->tone,
            $this->platform,
            $this->goal,
            $this->notes,
        ], fn (?string $value): bool => $value !== null && $value !== ''))));
    }

    private static function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
