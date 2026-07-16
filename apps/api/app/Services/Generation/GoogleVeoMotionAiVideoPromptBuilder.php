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
        $productName = $this->stringValue($input['product_name'] ?? null)
            ?? $this->stringValue($input['project_title'] ?? null)
            ?? 'the featured product';
        $productCategory = $this->stringValue($input['product_category'] ?? null);
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
        $negativePrompt = $this->stringValue($input['negative_prompt'] ?? null)
            ?? $this->stringValue(data_get($plan, 'generation_prompt.negative_prompt'))
            ?? 'warped product, unreadable text, fake logos, exaggerated product results, distorted hands, distracting camera shake';
        $cta = $this->stringValue($input['primary_cta'] ?? null)
            ?? $this->stringValue(data_get($plan, 'script_plan.CTA_line'))
            ?? 'See product details';
        $script = $manualScript
            ?? $this->stringValue(data_get($plan, 'script_plan.voiceover_ready_script'))
            ?? $this->stringValue(data_get($plan, 'script_plan.short_script'));
        $campaignContext = $this->campaignContext($input);
        $voiceoverContext = $this->voiceoverContext($input);

        $sections = [
            'Task' => 'Generate a short visual-first social commerce motion video. Treat the output as a base motion clip that can later be edited with Nusa captions, voiceover, or post-production.',
            'Source mode' => $this->sourceModeInstruction($sourceMode, $productImage),
            'Product or offer context' => implode(' ', array_filter([
                "Product/offer: {$productName}.",
                $productCategory ? "Category: {$productCategory}." : null,
                "Description: {$description}",
            ])),
            'Audience and platform' => "Platform: {$platform}. Audience: {$audience}. Tone: {$tone}. Format: {$aspectRatio}. Provider duration target: {$duration} seconds.",
            'Creative direction' => implode(' ', array_filter([
                "Visual style: {$visualStyle}. Motion style: {$motionStyle}.",
                $sceneDescription ? "Scene: {$sceneDescription}" : null,
                $backgroundPrompt ? "Background/environment: {$backgroundPrompt}" : null,
                $cameraDirection ? "Camera direction: {$cameraDirection}" : null,
                $creativeNotes ? "User prompt or creative notes: {$creativeNotes}" : null,
            ])),
            'Scenario reference' => $scenarioImage instanceof MediaAsset
                ? 'A scenario/background image exists in the Nusa workspace. Use it only as prompt context unless the provider request explicitly supports image references.'
                : 'No scenario image is provided. Use a clean, realistic social-commerce scene that supports the product without distraction.',
            'Script and campaign context' => implode(' ', array_filter([
                $this->primarySourceText($sourceMode, $manualScript, $creativeNotes, $campaignContext),
                $script ? "Preferred narration or script direction: {$script}" : null,
                $voiceoverContext ? "Voiceover context for pacing only: {$voiceoverContext}" : null,
                "CTA direction: {$cta}.",
            ])),
            'Motion guidance' => 'Create a cohesive sequence, not random disconnected shots. Use realistic commercially useful moments: clear opening reveal, useful detail, lifestyle context, gentle camera movement, and a stable final hero frame.',
            'Composition guardrails' => 'Keep the frame clean and commercial. Avoid distorted product shapes, unreadable text, fake logos, overlaid claims, or confusing hands. Leave safe space for future captions.',
            'Audio and post-production rule' => 'Veo may generate native audio, but Nusa currently treats this output as a visual-first base motion clip. Do not depend on speech or music being correct.',
            'Honest marketing guardrails' => 'Do not invent testimonials, ratings, awards, medical claims, revenue claims, or exaggerated product results. Only show benefits that are visually generic and safe.',
            'Negative prompt' => $negativePrompt,
        ];

        return [
            'prompt' => collect($sections)
                ->map(fn (string $value, string $key): string => "{$key}: {$value}")
                ->implode('

'),
            'negative_prompt' => $negativePrompt,
            'summary' => [
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
                'voiceover_context_used' => $voiceoverContext !== null,
                'product_image_asset_id' => $productImage?->getKey(),
                'scenario_image_asset_id' => $scenarioImage?->getKey(),
            ],
        ];
    }

    private function sourceModeInstruction(string $sourceMode, ?MediaAsset $productImage): string
    {
        return match ($sourceMode) {
            MotionAiVideoCapabilities::SOURCE_MODE_TEXT_PROMPT => 'Text prompt mode. Use the user prompt, project brief, and creative direction only. No product image is required or sent to the provider.',
            MotionAiVideoCapabilities::SOURCE_MODE_CAMPAIGN_ASSISTED => 'Campaign-assisted mode. Transform the saved campaign hooks, script, benefits, CTA, and project context into a visual Veo prompt. Do not simply dump JSON.',
            MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_CONTEXT => $productImage instanceof MediaAsset
                ? 'Image-context mode. A product image exists in the Nusa workspace, but image bytes are not sent in the default provider request. Use product metadata and filename-style context only; do not promise exact visual preservation.'
                : 'Image-context mode requested, but no product image metadata is available. Use project context only and do not promise exact visual preservation.',
            MotionAiVideoCapabilities::SOURCE_MODE_IMAGE_REFERENCE_EXPERIMENTAL => 'Experimental image-reference mode. The product image is sent only when backend provider capability explicitly enables verified reference images. Preserve product identity from the visual reference while keeping claims honest.',
            default => 'Use project context and safe social-commerce creative direction.',
        };
    }

    private function primarySourceText(string $sourceMode, ?string $manualScript, ?string $creativeNotes, ?string $campaignContext): ?string
    {
        return match ($sourceMode) {
            MotionAiVideoCapabilities::SOURCE_MODE_TEXT_PROMPT => $this->limitedJoined([
                $manualScript ? "Manual prompt/script: {$manualScript}" : null,
                $creativeNotes ? "Creative direction: {$creativeNotes}" : null,
            ], 1000),
            MotionAiVideoCapabilities::SOURCE_MODE_CAMPAIGN_ASSISTED => $campaignContext
                ? "Campaign source to transform into visuals: {$campaignContext}"
                : null,
            default => $campaignContext ? "Optional campaign context: {$campaignContext}" : null,
        };
    }

    /** @param array<string, mixed> $input */
    private function campaignContext(array $input): ?string
    {
        $summary = $input['campaign_generation_summary'] ?? null;

        if (! is_array($summary)) {
            return null;
        }

        $parts = [
            data_get($summary, 'campaign_angle'),
            data_get($summary, 'product_angle'),
            data_get($summary, 'campaign_title'),
            data_get($summary, 'caption'),
            data_get($summary, 'cta'),
            data_get($summary, 'ad_summary.creative_angle'),
            data_get($summary, 'main_script.voiceover_script'),
            data_get($summary, 'script.voiceover_script'),
        ];

        $hooks = data_get($summary, 'hooks');

        if (is_array($hooks)) {
            $parts[] = collect($hooks)
                ->map(fn (mixed $hook): ?string => $this->stringValue(is_array($hook) ? ($hook['hook'] ?? $hook['text'] ?? null) : $hook))
                ->filter()
                ->take(3)
                ->implode(' ');
        }

        return $this->limitedJoined($parts, 1200);
    }

    /** @param array<string, mixed> $input */
    private function voiceoverContext(array $input): ?string
    {
        $summary = $input['voiceover_generation_summary'] ?? null;

        if (! is_array($summary)) {
            return null;
        }

        return $this->limitedJoined([
            data_get($summary, 'narration_script'),
            data_get($summary, 'voiceover_ready_script'),
            data_get($summary, 'voice_direction'),
            data_get($summary, 'pacing_notes.0'),
        ], 700);
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
