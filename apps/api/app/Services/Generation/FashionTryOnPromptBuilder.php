<?php

namespace App\Services\Generation;

use App\Support\FashionTryOnCapabilities;
use Illuminate\Support\Str;

class FashionTryOnPromptBuilder
{
    public const VARIATION_DIVERSITY_MODE = 'controlled_pose_and_framing_diversity';

    private const VARIATION_PROVIDER_SUMMARY = 'distinct_pose_framing_composition_preserve_identity_and_garment';

    public function variationInstruction(): string
    {
        return implode(' ', [
            'Create distinct fashion try-on variations.',
            'Each output must keep the same person identity and same garment, but use a visibly different pose, body angle, arm or hand placement, framing, crop, or camera perspective.',
            'Avoid near-duplicate compositions.',
            'Keep the garment accurate, clearly visible, realistic, natural, and commercially usable.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function premiumProviderPrompt(array $input): ?string
    {
        $userPrompt = FashionTryOnCapabilities::sanitizePremiumPrompt($input['premium_prompt'] ?? $input['prompt'] ?? null);

        if (! $this->isVariations($input)) {
            return $userPrompt;
        }

        $instruction = $this->variationInstruction();
        $maxCharacters = FashionTryOnCapabilities::premiumPromptMaxCharacters();

        if ($userPrompt === null) {
            return Str::limit($instruction, $maxCharacters, '');
        }

        $prefix = $instruction."\n\nCreator styling note: ";
        $remaining = $maxCharacters - mb_strlen($prefix);

        if ($remaining <= 0) {
            return Str::limit($instruction, $maxCharacters, '');
        }

        return $prefix.Str::limit($userPrompt, $remaining, '');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function safeMetadata(array $input, string $tier): array
    {
        $userPromptPresent = FashionTryOnCapabilities::sanitizePremiumPrompt($input['premium_prompt'] ?? $input['prompt'] ?? null) !== null;

        if (! $this->isVariations($input)) {
            return [
                'user_styling_prompt_present' => $userPromptPresent,
            ];
        }

        $tier = FashionTryOnCapabilities::normalizeTier($tier);
        $providerPromptApplied = $tier === FashionTryOnCapabilities::TIER_PREMIUM;

        return [
            'variation_diversity_requested' => true,
            'variation_diversity_mode' => self::VARIATION_DIVERSITY_MODE,
            'variation_prompt_applied' => $providerPromptApplied,
            'variation_prompt_channel' => $providerPromptApplied ? 'premium_prompt' : 'standard_provider_contract_unavailable',
            'standard_variation_prompt_supported' => $tier === FashionTryOnCapabilities::TIER_STANDARD ? false : null,
            'user_styling_prompt_present' => $userPromptPresent,
            'provider_prompt_summary' => self::VARIATION_PROVIDER_SUMMARY,
            'prompt_strategy' => $providerPromptApplied
                ? 'internal_diversity_instruction_prepended_to_user_prompt'
                : 'metadata_only_no_standard_prompt_field',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function isVariations(array $input): bool
    {
        return FashionTryOnCapabilities::normalizePackage(is_string($input['package_key'] ?? null) ? $input['package_key'] : null)
            === FashionTryOnCapabilities::PACKAGE_VARIATIONS;
    }
}
