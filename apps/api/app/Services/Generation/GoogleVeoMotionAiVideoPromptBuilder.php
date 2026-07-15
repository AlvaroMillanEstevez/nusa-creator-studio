<?php

namespace App\Services\Generation;

use App\Models\MediaAsset;
use Illuminate\Support\Str;

class GoogleVeoMotionAiVideoPromptBuilder
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    public function build(array $input, array $plan, MediaAsset $productImage, ?MediaAsset $scenarioImage = null): array
    {
        $productName = $this->stringValue($input['product_name'] ?? null)
            ?? $this->stringValue($input['project_title'] ?? null)
            ?? 'the featured product';
        $productCategory = $this->stringValue($input['product_category'] ?? null);
        $description = $this->stringValue($input['product_description'] ?? null)
            ?? $this->stringValue(data_get($plan, 'generation_prompt.product_description'))
            ?? 'A product that should be shown clearly and honestly.';
        $audience = $this->stringValue($input['target_audience'] ?? null) ?? 'social commerce shoppers';
        $platform = $this->stringValue($input['platform'] ?? null) ?? 'tiktok';
        $tone = $this->stringValue($input['tone'] ?? null) ?? 'natural';
        $duration = $this->intValue($input['target_duration_seconds'] ?? null, 8);
        $aspectRatio = $this->aspectRatio($this->stringValue($input['aspect_ratio'] ?? null) ?? 'vertical_9_16');
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
            'Task' => 'Generate a short visual-first product motion clip for social commerce. Treat the output as a base motion video asset, not a fully finished ad.',
            'Product identity' => implode(' ', array_filter([
                "Product: {$productName}.",
                $productCategory ? "Category: {$productCategory}." : null,
                "Description: {$description}",
                'Use the required product image as the main visual reference. Keep the product recognizable and consistent across the full clip.',
            ])),
            'Audience and platform' => "Platform: {$platform}. Audience: {$audience}. Tone: {$tone}. Format: {$aspectRatio}. Duration target: {$duration} seconds.",
            'Creative direction' => implode(' ', array_filter([
                "Visual style: {$visualStyle}. Motion style: {$motionStyle}.",
                $sceneDescription ? "Scene: {$sceneDescription}" : null,
                $backgroundPrompt ? "Background/environment: {$backgroundPrompt}" : null,
                $cameraDirection ? "Camera direction: {$cameraDirection}" : null,
                $creativeNotes ? "Manual creative notes: {$creativeNotes}" : null,
            ])),
            'Scenario reference' => $scenarioImage instanceof MediaAsset
                ? 'A scenario/background image is provided. Use it as environmental inspiration and scene grounding while keeping the product image as the primary identity reference.'
                : 'No scenario image is provided. Use a clean, realistic social-commerce scene that supports the product without distraction.',
            'Script and campaign context' => implode(' ', array_filter([
                $script ? "Preferred script or narration direction: {$script}" : null,
                $campaignContext ? "Campaign context: {$campaignContext}" : null,
                $voiceoverContext ? "Voiceover context for pacing only: {$voiceoverContext}" : null,
                "CTA direction: {$cta}.",
            ])),
            'Motion guidance' => 'Create a cohesive sequence, not random disconnected shots. Use realistic commercially useful product moments: clear reveal, useful detail, lifestyle context, gentle camera movement, and a stable final hero frame.',
            'Audio and post-production rule' => 'Do not rely on native audio. If the provider generates audio, it is not the primary workflow output. The result is a visual motion clip that can later be combined with Nusa voiceover, captions, or final editing.',
            'Honest marketing guardrails' => 'Do not invent testimonials, ratings, awards, medical claims, revenue claims, or exaggerated product results. Only show benefits that are visually generic and safe.',
            'Negative prompt' => $negativePrompt,
        ];

        return [
            'prompt' => collect($sections)
                ->map(fn (string $value, string $key): string => "{$key}: {$value}")
                ->implode("\n\n"),
            'negative_prompt' => $negativePrompt,
            'summary' => [
                'product_name' => $productName,
                'product_category' => $productCategory,
                'platform' => $platform,
                'aspect_ratio' => $aspectRatio,
                'target_duration_seconds' => $duration,
                'visual_style' => $visualStyle,
                'motion_style' => $motionStyle,
                'manual_script_used' => $manualScript !== null,
                'creative_notes_used' => $creativeNotes !== null,
                'scenario_image_used' => $scenarioImage instanceof MediaAsset,
                'voiceover_context_used' => $voiceoverContext !== null,
                'product_image_asset_id' => $productImage->getKey(),
                'scenario_image_asset_id' => $scenarioImage?->getKey(),
            ],
        ];
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
        ];

        $hooks = data_get($summary, 'hooks');

        if (is_array($hooks)) {
            $parts[] = collect($hooks)
                ->map(fn (mixed $hook): ?string => $this->stringValue(is_array($hook) ? ($hook['hook'] ?? $hook['text'] ?? null) : $hook))
                ->filter()
                ->take(3)
                ->implode(' ');
        }

        return $this->limitedJoined($parts, 900);
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
            'square_1_1' => '1:1',
            'portrait_4_5' => '4:5',
            default => '9:16',
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
