<?php

namespace App\Services\Generation;

use App\Models\MediaAsset;
use App\Support\MotionAiVideoCapabilities;
use Illuminate\Support\Str;

class GoogleVeoMotionAiVideoPromptBuilder
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    public function build(array $input, array $plan, ?MediaAsset $productImage = null, ?MediaAsset $scenarioImage = null): array
    {
        $sourceMode = MotionAiVideoCapabilities::normalizeSourceMode(
            $input['source_mode'] ?? null,
            $input['campaign_generation_id'] ?? null,
            $input['product_image_asset_id'] ?? null,
        );
        $productName = $this->stringValue($input['product_name'] ?? null) ?? 'the featured product';
        $productCategory = $this->stringValue($input['product_category'] ?? null);
        $explicitBrandName = $this->explicitName($input, ['brand_name', 'product_brand', 'brand']);
        $description = $this->stringValue($input['product_description'] ?? null)
            ?? $this->stringValue(data_get($plan, 'generation_prompt.product_description'))
            ?? 'A product or offer that should be shown clearly and honestly.';
        $audience = $this->stringValue($input['target_audience'] ?? null) ?? 'social commerce shoppers';
        $platform = $this->stringValue($input['platform'] ?? null) ?? 'tiktok';
        $tone = $this->stringValue($input['tone'] ?? null) ?? 'natural';
        $requestedDuration = $this->intValue($input['target_duration_seconds'] ?? null, MotionAiVideoCapabilities::defaultDurationSeconds());
        $duration = MotionAiVideoCapabilities::defaultDurationSeconds();
        $requestedAspectRatio = $this->stringValue($input['aspect_ratio'] ?? null) ?? 'vertical_9_16';
        $aspectRatio = $this->aspectRatio($requestedAspectRatio);
        $visualStyle = $this->stringValue($input['visual_style'] ?? null) ?? 'realistic_product';
        $motionStyle = $this->stringValue($input['motion_style'] ?? null) ?? 'slow_push_in';
        $manualScript = $this->stringValue($input['manual_script'] ?? null);
        $creativeNotes = $this->stringValue($input['creative_notes'] ?? null);
        $cameraDirection = $this->stringValue($input['camera_direction'] ?? null)
            ?? $this->stringValue(data_get($plan, 'motion_settings.camera_direction'));
        $sceneDescription = $this->stringValue($input['scene_description'] ?? null)
            ?? $this->stringValue(data_get($plan, 'generation_prompt.scene_description'));
        $backgroundPrompt = $this->stringValue($input['background_prompt'] ?? null);
        $userNegativePrompt = $this->stringValue($input['user_negative_prompt'] ?? null)
            ?? $this->stringValue($input['negative_prompt'] ?? null);
        $primaryCtaSource = $this->stringValue($input['primary_cta_source'] ?? null) ?? 'default_suggestion';
        $captionsRequested = $this->boolOption($input, ['use_captions', 'include_caption_plan'], true);
        $voiceoverRequested = $this->boolOption($input, ['use_voiceover', 'include_voiceover_plan'], true);
        $hasVoiceoverAsset = $this->nullableInt($input['voiceover_generation_id'] ?? null) !== null
            && is_array($input['voiceover_generation_summary'] ?? null);
        $campaignVisualContext = $this->campaignVisualContext($input);
        $voiceoverPacingContext = $voiceoverRequested && $hasVoiceoverAsset
            ? $this->voiceoverPacingContext($input)
            : null;
        $explicitStoreName = $this->explicitName($input, ['store_name', 'shop_name', 'seller_name']);
        $explicitAccountName = $this->explicitName($input, ['creator_account_name', 'account_name', 'creator_name']);
        $explicitSocialHandle = $this->explicitName($input, ['social_handle', 'account_handle', 'creator_handle']);

        $sections = [
            'Task' => 'Generate clean visual-first social-commerce footage. Treat the output as base full-frame footage for later Nusa editing, captions, voiceover, and post-production.',
            'Visual integrity requirements' => $this->visualIntegrityRequirements(),
            'Source mode' => $this->sourceModeInstruction($sourceMode, $productImage),
            'Product or offer context' => implode(' ', array_filter([
                "Product/offer: {$productName}.",
                $productCategory ? "Category: {$productCategory}." : null,
                $explicitBrandName ? "Explicit brand/product name supplied by the user: {$explicitBrandName}. Use it as semantic product context, not as a generated logo or text overlay." : null,
                "Description: {$description}",
            ])),
            'Audience and platform' => implode(' ', array_filter([
                "Platform selection: {$platform}.",
                "Audience: {$audience}.",
                "Tone: {$tone}.",
                "Format: {$aspectRatio}.",
                "Provider duration target: {$duration} seconds.",
                $this->platformGuidance($platform),
            ])),
            'Creative direction' => implode(' ', array_filter([
                "Visual style: {$visualStyle}. Motion style: {$motionStyle}.",
                $sceneDescription ? "Scene: {$sceneDescription}" : null,
                $backgroundPrompt ? "Background/environment: {$backgroundPrompt}" : null,
                $cameraDirection ? "Camera direction: {$cameraDirection}" : null,
                $creativeNotes ? "User creative direction: {$creativeNotes}" : null,
            ])),
            'Campaign visual context' => $campaignVisualContext
                ? "Use these saved campaign ideas only as visual context: {$campaignVisualContext}"
                : 'No saved campaign visual context is required. Use the project brief and creative direction.',
            'Caption and overlay policy' => $this->captionPolicy($captionsRequested),
            'Voiceover and audio policy' => $this->voiceoverPolicy($voiceoverRequested, $hasVoiceoverAsset, $voiceoverPacingContext),
            'Explicit names policy' => $this->explicitNamesPolicy($explicitStoreName, $explicitAccountName, $explicitSocialHandle),
            'Scenario reference' => $scenarioImage instanceof MediaAsset
                ? 'A scenario/background image exists in the Nusa workspace. Use it only as prompt context unless the provider request explicitly supports image references.'
                : 'No scenario image is provided. Use a clean, realistic social-commerce scene that supports the product without distraction.',
            'Motion guidance' => 'Create a cohesive sequence, not random disconnected shots. Use realistic commercially useful moments: clear opening reveal, useful detail, lifestyle context, gentle camera movement, and a stable final hero frame.',
            'System visual negative constraints' => $this->systemNegativeConstraints(),
            'User negative prompt' => $userNegativePrompt
                ? "Additional user negative prompt, lower priority than the visual integrity requirements: {$userNegativePrompt}"
                : 'No additional user negative prompt supplied. The visual integrity requirements still apply.',
        ];

        $prompt = Str::limit(collect($sections)
            ->filter(fn (?string $value): bool => $value !== null && trim($value) !== '')
            ->map(fn (string $value, string $key): string => "{$key}: {$value}")
            ->implode(PHP_EOL.PHP_EOL), 5200, '');
        $summary = [
            'source_mode' => $sourceMode,
            'product_name' => $productName,
            'product_category' => $productCategory,
            'platform' => $platform,
            'aspect_ratio' => $aspectRatio,
            'requested_aspect_ratio' => $requestedAspectRatio,
            'target_duration_seconds' => $duration,
            'requested_target_duration_seconds' => $requestedDuration,
            'duration_normalized_for_provider' => $requestedDuration !== $duration,
            'visual_style' => $visualStyle,
            'motion_style' => $motionStyle,
            'manual_script_used' => $manualScript !== null,
            'creative_notes_used' => $creativeNotes !== null,
            'scenario_image_used' => $scenarioImage instanceof MediaAsset,
            'voiceover_requested' => $voiceoverRequested,
            'voiceover_context_used' => $voiceoverPacingContext !== null,
            'captions_requested' => $captionsRequested,
            'primary_cta_source' => $primaryCtaSource,
            'user_negative_prompt_supplied' => $userNegativePrompt !== null,
            'system_visual_constraints_applied' => true,
            'overlay_text_deferred' => true,
            'platform_ui_forbidden' => true,
            'invented_names_forbidden' => true,
            'explicit_brand_name_provided' => $explicitBrandName !== null,
            'explicit_store_name_provided' => $explicitStoreName !== null,
            'explicit_account_name_provided' => $explicitAccountName !== null || $explicitSocialHandle !== null,
            'product_image_asset_id' => $productImage?->getKey(),
            'scenario_image_asset_id' => $scenarioImage?->getKey(),
            'final_prompt_character_count' => mb_strlen($prompt),
            'final_prompt_hash' => hash('sha256', $prompt),
        ];

        return [
            'prompt' => $prompt,
            'display_prompt' => 'Clean visual-first product footage. Exact captions, CTA, account/store names, and overlays are deferred to post-production.',
            'negative_prompt' => $userNegativePrompt,
            'display_negative_prompt' => $userNegativePrompt,
            'system_negative_constraints' => $this->systemNegativeConstraints(),
            'summary' => $summary,
        ];
    }

    private function visualIntegrityRequirements(): string
    {
        return implode(' ', [
            'Generate clean full-frame video footage only.',
            'Do not recreate TikTok, Instagram, YouTube or other social-media interfaces.',
            'Do not add usernames, account handles, shop names, store names, watermarks, app controls or platform chrome.',
            'Do not invent brand names, seller names, account names, claims, prices or promotional text.',
            'Do not render on-screen text unless exact visual text was explicitly supplied in a dedicated visual-text field and requested; this workflow currently defers text overlays to post-production.',
            'Leave safe visual space for captions and CTA overlays to be added during post-production.',
        ]);
    }

    private function systemNegativeConstraints(): string
    {
        return implode(', ', [
            'social media UI',
            'TikTok interface',
            'Instagram interface',
            'YouTube interface',
            'app controls',
            'username overlay',
            'account handle overlay',
            'shop name overlay',
            'store name overlay',
            'watermark',
            'price text',
            'discount label',
            'URL text',
            'invented CTA text',
            'burned-in captions',
            'unreadable text',
            'fake logos',
            'exaggerated claims',
            'distorted hands',
            'distracting camera shake',
        ]);
    }

    private function platformGuidance(string $platform): string
    {
        return "Use the platform only for pacing, framing, visual rhythm, and composition. Do not recreate the {$platform} app interface, feed UI, controls, buttons, comment panels, profile headers, or platform chrome.";
    }

    private function captionPolicy(bool $captionsRequested): string
    {
        if (! $captionsRequested) {
            return 'Captions are disabled for the Veo clip. Generate no captions, no CTA text, no subtitles and no on-screen text. Campaign captions and CTA are reserved for later post-production only.';
        }

        return 'Captions may be added later in post-production. Do not render captions, hooks, CTA text, hashtags, prices or promo labels directly in the Veo footage. Compose the frame with readable safe space for later captions and CTA overlays.';
    }

    private function voiceoverPolicy(bool $voiceoverRequested, bool $hasVoiceoverAsset, ?string $voiceoverPacingContext): string
    {
        if (! $voiceoverRequested) {
            return 'Voiceover is disabled for this Veo clip. Do not request narration, spoken dialogue or a presenter speaking. Generate visual-only footage or neutral native ambience where appropriate.';
        }

        if ($hasVoiceoverAsset && $voiceoverPacingContext !== null) {
            return "A separate Nusa voiceover may be composed later. Use this only for timing, pacing and visual sequencing; do not synthesize exact narration or speech in Veo: {$voiceoverPacingContext}";
        }

        return 'If voiceover is added later, it will be composed in post-production. Do not synthesize exact narration or spoken dialogue in the Veo footage.';
    }

    private function explicitNamesPolicy(?string $storeName, ?string $accountName, ?string $socialHandle): string
    {
        $provided = array_filter([
            $storeName ? 'store/shop name was provided for business context' : null,
            $accountName ? 'account/creator name was provided for business context' : null,
            $socialHandle ? 'social handle was provided for business context' : null,
        ]);

        $prefix = $provided === []
            ? 'No explicit store name, account name or social handle was supplied in dedicated user fields.'
            : implode('; ', $provided).'.';

        return $prefix.' Do not display any store name, account name, social handle, username, seller name, price, discount, URL or watermark in the generated footage.';
    }

    private function sourceModeInstruction(string $sourceMode, ?MediaAsset $productImage): string
    {
        return match ($sourceMode) {
            MotionAiVideoCapabilities::SOURCE_MODE_TEXT_PROMPT => 'Text prompt mode. Use the user prompt, project brief, and creative direction only. No product image is required or sent to the provider.',
            MotionAiVideoCapabilities::SOURCE_MODE_CAMPAIGN_ASSISTED => 'Campaign-assisted mode. Transform saved campaign angle, benefits, audience and visual ideas into clean footage. Do not render campaign hooks, captions, CTA, hashtags, account names or raw JSON as pixels.',
            MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_CONTEXT => $productImage instanceof MediaAsset
                ? 'Image-context mode. A product image exists in the Nusa workspace, but image bytes are not sent in the default provider request. Use product metadata and filename-style context only; do not promise exact visual preservation.'
                : 'Image-context mode requested, but no product image metadata is available. Use project context only and do not promise exact visual preservation.',
            MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_FIRST_FRAME => 'First-frame image-to-video mode. The product image is sent as the initial frame only when the dedicated image-to-video feature flag is enabled. Animate from the starting image without claiming exact product preservation.',
            MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_REFERENCE_EXPERIMENTAL => 'Experimental reference-image mode. Reference images are separate from first-frame image-to-video and are sent only when explicit backend capability enables verified reference images. Preserve product identity from visual references while keeping claims honest.',
            default => 'Use project context and safe social-commerce creative direction.',
        };
    }

    /** @param array<string, mixed> $input */
    private function campaignVisualContext(array $input): ?string
    {
        $summary = $input['campaign_generation_summary'] ?? null;

        if (! is_array($summary)) {
            return null;
        }

        $parts = [
            data_get($summary, 'product_name'),
            data_get($summary, 'product_description'),
            data_get($summary, 'target_audience'),
            data_get($summary, 'campaign_angle'),
            data_get($summary, 'product_angle'),
            data_get($summary, 'core_selling_proposition'),
            data_get($summary, 'desired_tone'),
            data_get($summary, 'visual_direction'),
            data_get($summary, 'ad_summary.creative_angle'),
            data_get($summary, 'render_plan.timeline_strategy'),
        ];

        $benefits = data_get($summary, 'benefits');

        if (is_array($benefits)) {
            $parts[] = collect($benefits)
                ->map(fn (mixed $value): ?string => $this->stringValue(is_array($value) ? ($value['benefit'] ?? $value['text'] ?? $value['label'] ?? null) : $value))
                ->filter()
                ->take(4)
                ->implode(' ');
        }

        $scenes = data_get($summary, 'script.scenes');

        if (is_array($scenes)) {
            $parts[] = collect($scenes)
                ->map(fn (mixed $scene): ?string => is_array($scene)
                    ? $this->limitedJoined([
                        $scene['visual_direction'] ?? null,
                        $scene['visual'] ?? null,
                        $scene['product_action'] ?? null,
                    ], 300)
                    : $this->stringValue($scene))
                ->filter()
                ->take(4)
                ->implode(' ');
        }

        return $this->limitedJoined($parts, 1500);
    }

    /** @param array<string, mixed> $input */
    private function voiceoverPacingContext(array $input): ?string
    {
        $summary = $input['voiceover_generation_summary'] ?? null;

        if (! is_array($summary)) {
            return null;
        }

        return $this->limitedJoined([
            data_get($summary, 'voice_direction'),
            data_get($summary, 'pacing_notes.0'),
            data_get($summary, 'pacing_notes.1'),
            data_get($summary, 'duration_seconds') ? 'Approximate voiceover duration: '.data_get($summary, 'duration_seconds').' seconds.' : null,
        ], 500);
    }

    /** @param array<int, mixed> $parts */
    private function limitedJoined(array $parts, int $limit): ?string
    {
        $text = collect($parts)
            ->map(fn (mixed $part): ?string => $this->stringValue($part))
            ->filter()
            ->implode(' ');

        return $text === '' ? null : Str::limit($text, $limit, '');
    }

    private function aspectRatio(string $value): string
    {
        return match ($value) {
            'horizontal_16_9', 'landscape_16_9', '16:9' => '16:9',
            default => MotionAiVideoCapabilities::defaultAspectRatio(),
        };
    }

    /** @param array<string, mixed> $input @param array<int, string> $keys */
    private function boolOption(array $input, array $keys, bool $fallback): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                return filter_var($input[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $fallback;
            }
        }

        return $fallback;
    }

    /** @param array<string, mixed> $input @param array<int, string> $keys */
    private function explicitName(array $input, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->stringValue($input[$key] ?? null);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, 4000, '');
    }

    private function intValue(mixed $value, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return max(1, (int) $value);
    }
}
