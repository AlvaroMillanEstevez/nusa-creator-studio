<?php

namespace App\Services\Generation;

use App\Contracts\MotionAiVideoProvider;
use App\Support\MotionAiVideoCapabilities;
use Illuminate\Support\Str;

class MockMotionAiVideoProvider implements MotionAiVideoProvider
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array
    {
        $sourceMode = MotionAiVideoCapabilities::normalizeSourceMode(
            $input['source_mode'] ?? null,
            $input['campaign_generation_id'] ?? null,
            $input['product_image_asset_id'] ?? null,
        );
        $product = $this->value($input, 'product_name', $this->value($input, 'project_title', 'the featured product', 160), 160);
        $description = $this->value($input, 'product_description', 'A product that should be shown clearly without exaggerated claims.', 320);
        $audience = $this->value($input, 'target_audience', 'social commerce shoppers', 180);
        $platform = $this->value($input, 'platform', 'tiktok', 80);
        $aspectRatio = $this->value($input, 'aspect_ratio', 'vertical_9_16', 80);
        $duration = $this->intValue($input, 'target_duration_seconds', 8);
        $visualStyle = $this->value($input, 'visual_style', 'realistic_product', 80);
        $motionStyle = $this->value($input, 'motion_style', 'slow_push_in', 80);
        $cameraDirection = $this->value($input, 'camera_direction', $this->cameraDirection($motionStyle), 320);
        $sceneDescription = $this->value($input, 'scene_description', 'Keep the product centered in a clean, creator-friendly scene.', 600);
        $backgroundPrompt = $this->value($input, 'background_prompt', 'A simple social commerce background that supports the product without distraction.', 600);
        $negativePrompt = $this->value($input, 'negative_prompt', 'warped product, unreadable text, fake logos, exaggerated product results, distorted hands, distracting camera shake', 600);
        $primaryCta = $this->value($input, 'primary_cta', 'See product details', 180);
        $scriptSource = $this->value($input, 'script_source', 'project_brief', 80);
        $includeVoiceoverPlan = (bool) ($input['include_voiceover_plan'] ?? true);
        $includeCaptionPlan = (bool) ($input['include_caption_plan'] ?? true);
        $campaign = $this->arrayValue($input, 'campaign_generation_summary');
        $voiceover = $this->arrayValue($input, 'voiceover_generation_summary');
        $hasScenario = $this->hasValue($input, 'scenario_image_asset_id');
        $hasCampaign = $this->hasValue($input, 'campaign_generation_id');
        $hasVoiceover = $this->hasValue($input, 'voiceover_generation_id');
        $creativeAngle = $this->creativeAngle($input, $campaign, $product);
        $script = $this->script($input, $campaign, $voiceover, $scriptSource, $product, $description, $primaryCta);
        $captionLines = $includeCaptionPlan
            ? $this->captionLines($product, $creativeAngle, $primaryCta)
            : [];
        $scenePlan = $this->scenePlan(
            product: $product,
            duration: $duration,
            motionStyle: $motionStyle,
            cameraDirection: $cameraDirection,
            creativeAngle: $creativeAngle,
            primaryCta: $primaryCta,
            captionLines: $captionLines,
            includeVoiceoverPlan: $includeVoiceoverPlan,
        );

        return [
            'motion_summary' => [
                'platform' => $platform,
                'aspect_ratio' => $aspectRatio,
                'duration' => $duration,
                'visual_style' => $visualStyle,
                'motion_style' => $motionStyle,
                'source_type' => $this->sourceType($hasScenario, $hasCampaign, $hasVoiceover),
                'source_mode' => $sourceMode,
                'creative_angle' => $creativeAngle,
                'target_audience' => $audience,
                'primary_cta' => $primaryCta,
                'render_available' => false,
            ],
            'generation_prompt' => [
                'main_prompt' => $this->mainPrompt($product, $description, $sceneDescription, $backgroundPrompt, $visualStyle, $motionStyle, $platform, $aspectRatio),
                'product_description' => $description,
                'scene_description' => $sceneDescription,
                'camera_motion' => $this->cameraMotion($motionStyle),
                'lighting_style' => $this->lightingStyle($visualStyle),
                'composition_notes' => "Frame {$product} clearly, leave safe space for captions, and avoid hiding key product details.",
                'brand_safety_notes' => 'Keep the concept factual, avoid invented testimonials, and avoid claims that are not visible or verified.',
                'negative_prompt' => $negativePrompt,
            ],
            'scene_plan' => $scenePlan,
            'script_plan' => [
                'short_script' => $script['short_script'],
                'voiceover_ready_script' => $includeVoiceoverPlan ? $script['voiceover_ready_script'] : 'Voiceover plan disabled for this draft.',
                'caption_lines' => $captionLines,
                'CTA_line' => $primaryCta,
            ],
            'motion_settings' => [
                'visual_style' => $visualStyle,
                'motion_style' => $motionStyle,
                'camera_direction' => $cameraDirection,
                'aspect_ratio' => $aspectRatio,
                'target_duration_seconds' => $duration,
                'scenario_used' => $hasScenario,
                'voiceover_planned' => $includeVoiceoverPlan,
            ],
            'asset_plan' => [
                'product_image_asset_id' => $this->nullableInt($input['product_image_asset_id'] ?? null),
                'scenario_image_asset_id' => $this->nullableInt($input['scenario_image_asset_id'] ?? null),
                'campaign_generation_id' => $this->nullableInt($input['campaign_generation_id'] ?? null),
                'voiceover_generation_id' => $this->nullableInt($input['voiceover_generation_id'] ?? null),
                'recommended_next_assets' => $this->recommendedNextAssets($hasScenario, $hasCampaign, $hasVoiceover),
                'missing_items' => $this->missingItems($hasScenario, $hasCampaign, $hasVoiceover),
            ],
            'provider_readiness' => [
                'provider' => 'mock',
                'real_provider_required' => true,
                'render_available' => false,
                'recommended_provider_type' => 'image_to_video',
                'future_provider_inputs' => [
                    'product_image',
                    'scenario_image',
                    'prompt',
                    'duration',
                    'aspect_ratio',
                ],
            ],
            'compliance_notes' => [
                'Do not invent product claims.',
                'Do not invent testimonials/ratings.',
                'Verify paid ad claims before publishing.',
                'Generated motion may not perfectly preserve product details until real provider QA.',
            ],
            'metadata' => [
                'generation_type' => 'motion_ai_video',
                'provider' => 'mock',
                'source_mode' => $sourceMode,
                'product_image_asset_id' => $this->nullableInt($input['product_image_asset_id'] ?? null),
                'scenario_image_asset_id' => $this->nullableInt($input['scenario_image_asset_id'] ?? null),
                'campaign_generation_id' => $this->nullableInt($input['campaign_generation_id'] ?? null),
                'voiceover_generation_id' => $this->nullableInt($input['voiceover_generation_id'] ?? null),
                'platform' => $platform,
                'aspect_ratio' => $aspectRatio,
                'visual_style' => $visualStyle,
                'motion_style' => $motionStyle,
                'target_duration_seconds' => $duration,
                'script_source' => $scriptSource,
                'render_available' => false,
                'real_provider_enabled' => false,
                'voiceover_assisted' => $hasVoiceover,
                'campaign_assisted' => $hasCampaign,
            ],
        ];
    }

    /** @param array<string, mixed> $campaign */
    private function creativeAngle(array $input, array $campaign, string $product): string
    {
        $fromCampaign = $this->firstString([
            data_get($campaign, 'campaign_angle'),
            data_get($campaign, 'product_angle'),
            data_get($campaign, 'ad_summary.creative_angle'),
            data_get($campaign, 'campaign_title'),
        ]);

        return $fromCampaign ?? "Show {$product} clearly in a short, useful social commerce motion concept.";
    }

    /** @param array<string, mixed> $campaign @param array<string, mixed> $voiceover @return array{short_script: string, voiceover_ready_script: string} */
    private function script(array $input, array $campaign, array $voiceover, string $scriptSource, string $product, string $description, string $primaryCta): array
    {
        $manualScript = $this->string($input['manual_script'] ?? null);
        $voiceoverScript = $this->firstString([
            data_get($voiceover, 'narration_script'),
            data_get($voiceover, 'voiceover_ready_script'),
        ]);
        $campaignScript = $this->firstString([
            data_get($campaign, 'voiceover_ready_script'),
            data_get($campaign, 'narration_voice_script'),
            data_get($campaign, 'main_script.voiceover_script'),
            data_get($campaign, 'script.voiceover_script'),
            data_get($campaign, 'caption'),
        ]);

        $source = match ($scriptSource) {
            'manual_script' => $manualScript,
            'campaign_script' => $campaignScript,
            default => null,
        };

        $base = $voiceoverScript ?? $source ?? "Meet {$product}. {$description} Keep the product visible, explain the key use case, and close with: {$primaryCta}.";
        $base = Str::limit($base, 420, '');

        return [
            'short_script' => $base,
            'voiceover_ready_script' => $base,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function scenePlan(string $product, int $duration, string $motionStyle, string $cameraDirection, string $creativeAngle, string $primaryCta, array $captionLines, bool $includeVoiceoverPlan): array
    {
        $sceneCount = $duration <= 5 ? 2 : ($duration <= 10 ? 3 : 4);
        $segments = $this->segments($duration, $sceneCount);
        $captions = $captionLines === [] ? ["Meet {$product}", 'Show the product clearly', $primaryCta] : $captionLines;
        $goals = [
            ['Open on the product image with an immediate, clear reveal.', 'Keep the product centered and readable.', "Introduce {$product} in one simple line."],
            ['Build context with a scenario or background cue.', $creativeAngle, 'Show why this product fits the viewer\'s routine.'],
            ['Move closer to highlight useful product details.', 'Use motion to guide attention without hiding the product.', 'Call out the most visible benefit.'],
            ['End on a stable product hero frame with CTA room.', 'Hold the final frame so text and CTA are readable.', $primaryCta],
        ];

        return collect($segments)
            ->map(function (array $segment, int $index) use ($product, $motionStyle, $cameraDirection, $captions, $goals, $includeVoiceoverPlan): array {
                $goal = $goals[$index] ?? end($goals);
                $caption = $captions[$index] ?? end($captions);

                return [
                    'scene_number' => $index + 1,
                    'start_seconds' => $segment['start'],
                    'end_seconds' => $segment['end'],
                    'visual_direction' => $goal[0],
                    'product_action' => $goal[1],
                    'camera_direction' => $cameraDirection,
                    'overlay_or_caption' => $caption,
                    'narration_line' => $includeVoiceoverPlan ? $goal[2] : 'No narration planned for this scene.',
                    'provider_prompt_hint' => "{$product}, {$motionStyle}, scene ".($index + 1).', product remains accurate and clearly visible.',
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<int, array{start: int, end: int}> */
    private function segments(int $duration, int $count): array
    {
        $duration = max(1, $duration);
        $count = max(1, $count);
        $segments = [];

        for ($i = 0; $i < $count; $i++) {
            $start = (int) floor($duration * $i / $count);
            $end = (int) floor($duration * ($i + 1) / $count);
            $segments[] = [
                'start' => $start,
                'end' => max($start + 1, $end),
            ];
        }

        return $segments;
    }

    /** @return array<int, string> */
    private function captionLines(string $product, string $creativeAngle, string $primaryCta): array
    {
        return array_values(array_unique(array_filter([
            "Meet {$product}",
            Str::limit($creativeAngle, 54, ''),
            'Designed for quick social commerce content',
            $primaryCta,
        ], fn (string $value): bool => trim($value) !== '')));
    }

    private function sourceType(bool $hasScenario, bool $hasCampaign, bool $hasVoiceover): string
    {
        return match (true) {
            $hasVoiceover => 'voiceover_assisted',
            $hasCampaign => 'campaign_assisted',
            $hasScenario => 'product_image_with_scenario',
            default => 'product_image_only',
        };
    }

    /** @return array<int, string> */
    private function recommendedNextAssets(bool $hasScenario, bool $hasCampaign, bool $hasVoiceover): array
    {
        return array_values(array_filter([
            $hasScenario ? null : 'Scenario or background image for stronger environment control',
            $hasCampaign ? null : 'Product Content Kit campaign script for stronger hooks and CTA direction',
            $hasVoiceover ? null : 'AI Voiceover generation if the final motion video should include narration',
        ]));
    }

    /** @return array<int, string> */
    private function missingItems(bool $hasScenario, bool $hasCampaign, bool $hasVoiceover): array
    {
        return array_values(array_filter([
            $hasScenario ? null : 'Optional scenario image',
            $hasCampaign ? null : 'For stronger motion videos, generate a Product Content Kit first.',
            $hasVoiceover ? null : 'Optional voiceover reference',
        ]));
    }

    private function mainPrompt(string $product, string $description, string $sceneDescription, string $backgroundPrompt, string $visualStyle, string $motionStyle, string $platform, string $aspectRatio): string
    {
        return "Create a short image-to-video concept for {$product}. Product description: {$description}. Scene: {$sceneDescription}. Background: {$backgroundPrompt}. Visual style: {$visualStyle}. Motion style: {$motionStyle}. Platform: {$platform}. Aspect ratio: {$aspectRatio}. Keep the product accurate and avoid exaggerated claims.";
    }

    private function cameraDirection(string $motionStyle): string
    {
        return match ($motionStyle) {
            'handheld_ugc' => 'Natural handheld movement with small creator-style motion, keeping the product readable.',
            'product_orbit' => 'Slow orbit around the product while preserving its shape and proportions.',
            'top_down_demo' => 'Top-down camera angle with a gentle push toward the product detail.',
            'lifestyle_reveal' => 'Start wider in the environment, then reveal the product as the hero.',
            'before_after' => 'Split the motion into a simple before-and-after reveal without implying unverified results.',
            default => 'Slow push-in toward the product with stable framing and readable caption space.',
        };
    }

    private function cameraMotion(string $motionStyle): string
    {
        return Str::of($this->cameraDirection($motionStyle))->lower()->toString();
    }

    private function lightingStyle(string $visualStyle): string
    {
        return match ($visualStyle) {
            'premium_brand', 'clean_studio' => 'Soft studio lighting with subtle highlights and clean shadows.',
            'cinematic' => 'Cinematic contrast, controlled highlights and product-safe detail visibility.',
            'ugc_lifestyle', 'social_ad' => 'Natural creator lighting that still keeps the product clear.',
            default => 'Realistic, even lighting with no distracting reflections.',
        };
    }

    /** @param array<string, mixed> $input */
    private function value(array $input, string $key, string $fallback, int $limit): string
    {
        $value = $this->string($input[$key] ?? null);

        return $value === null ? $fallback : Str::limit($value, $limit, '');
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function arrayValue(array $input, string $key): array
    {
        $value = $input[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    private function intValue(array $input, string $key, int $fallback): int
    {
        $value = $input[$key] ?? null;

        return is_numeric($value) ? (int) $value : $fallback;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function hasValue(array $input, string $key): bool
    {
        return $this->nullableInt($input[$key] ?? null) !== null;
    }

    /** @param array<int, mixed> $values */
    private function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            $string = $this->string($value);

            if ($string !== null) {
                return $string;
            }
        }

        return null;
    }

    private function string(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
