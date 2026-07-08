<?php

namespace App\Data\Voiceover;

use App\Models\Generation;
use App\Models\User;

class AiVoiceoverRequestData
{
    /**
     * @param  array<string, mixed>  $voiceSettings
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public readonly string $text,
        public readonly string $language,
        public readonly ?string $voiceId,
        public readonly ?string $modelId,
        public readonly array $voiceSettings,
        public readonly int $projectId,
        public readonly int $generationId,
        public readonly int $userId,
        public readonly bool $realProviderAllowed,
        public readonly ?int $sourceGenerationId,
        public readonly ?string $sourceGenerationExcerpt,
        public readonly array $input,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromGeneration(Generation $generation, User $user, array $input): self
    {
        $text = self::stringValue($input['source_text'] ?? null)
            ?? self::fallbackText($input);

        return new self(
            text: $text,
            language: self::stringValue($input['language'] ?? null) ?? 'english',
            voiceId: self::stringValue($input['voice_id'] ?? null) ?: config('voiceover.elevenlabs.default_voice_id'),
            modelId: self::stringValue($input['model_id'] ?? null) ?: config('voiceover.elevenlabs.default_model_id'),
            voiceSettings: self::voiceSettings($input['voice_settings'] ?? null),
            projectId: (int) $generation->project_id,
            generationId: (int) $generation->getKey(),
            userId: (int) $user->getKey(),
            realProviderAllowed: $user->isAdmin()
                || $user->isInternalCreator()
                || $user->hasUnlimitedAccess()
                || $user->plan_key === 'internal_unlimited',
            sourceGenerationId: isset($input['source_generation_id']) && is_numeric($input['source_generation_id'])
                ? (int) $input['source_generation_id']
                : null,
            sourceGenerationExcerpt: self::stringValue($input['source_generation_excerpt'] ?? null),
            input: $input,
        );
    }

    private static function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $input */
    private static function fallbackText(array $input): string
    {
        $product = self::stringValue($input['product_name'] ?? null) ?? 'this product';
        $description = self::stringValue($input['product_description'] ?? null)
            ?? 'a practical product for short-form content';
        $audience = self::stringValue($input['target_audience'] ?? null) ?? 'social commerce viewers';

        return "Meet {$product}. It is described as {$description}. Here is why it may be useful for {$audience}: check the product details, compare it with your needs, and decide if it fits your routine.";
    }

    /**
     * @return array<string, mixed>
     */
    private static function voiceSettings(mixed $settings): array
    {
        if (! is_array($settings)) {
            return [];
        }

        $allowed = [];

        foreach (['stability', 'similarity_boost', 'style'] as $key) {
            if (isset($settings[$key]) && is_numeric($settings[$key])) {
                $allowed[$key] = (float) $settings[$key];
            }
        }

        if (array_key_exists('use_speaker_boost', $settings)) {
            $allowed['use_speaker_boost'] = (bool) $settings['use_speaker_boost'];
        }

        return $allowed;
    }
}
