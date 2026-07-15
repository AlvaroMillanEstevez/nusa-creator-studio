<?php

namespace App\Services\Generation;

use App\Contracts\AutoVideoAdProvider;
use Illuminate\Support\Str;

class MockAutoVideoAdProvider implements AutoVideoAdProvider
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array
    {
        $productName = $this->text($input['product_name'] ?? null, $input['project_title'] ?? 'your product');
        $description = $this->text($input['product_description'] ?? null, 'A useful product for social commerce buyers.');
        $audience = $this->text($input['audience_override'] ?? null, $input['target_audience'] ?? 'social commerce shoppers');
        $platform = $this->text($input['platform'] ?? null, 'general_paid_social');
        $format = $this->text($input['ad_format'] ?? null, 'vertical_9_16');
        $adGoal = $this->text($input['ad_goal'] ?? null, 'sales');
        $duration = $this->positiveInt($input['target_duration_seconds'] ?? null, 15);
        $visualSourceType = $this->text($input['visual_source_type'] ?? null, 'image');
        $cta = $this->text($input['primary_cta'] ?? null, 'See product details');
        $offer = $this->nullableText($input['offer_text'] ?? null);
        $tone = $this->text($input['tone'] ?? null, 'direct_response');
        $visualStyle = $this->text($input['visual_style'] ?? null, 'clean_product');
        $hookStyle = $this->text($input['hook_style'] ?? null, 'benefit_first');
        $campaign = $this->campaignContext($input);
        $creativeAngle = $this->creativeAngle($productName, $description, $adGoal, $campaign);

        return [
            'ad_summary' => [
                'platform' => $platform,
                'format' => $format,
                'duration' => $duration,
                'ad_goal' => $adGoal,
                'visual_source_type' => $visualSourceType,
                'creative_angle' => $creativeAngle,
                'target_audience' => $audience,
                'main_cta' => $cta,
            ],
            'hook_options' => $this->hookOptions($productName, $audience, $hookStyle),
            'ad_script' => $this->adScript($productName, $description, $creativeAngle, $cta, $offer),
            'storyboard' => $this->storyboard($productName, $duration, $visualSourceType, $cta),
            'overlay_plan' => $this->overlayPlan($productName, $duration, $visualStyle, $cta, $offer),
            'caption_variants' => [
                'short_caption' => "{$productName} in a simple, clear product demo.",
                'direct_response_caption' => "{$productName} helps {$audience} understand the benefit before they click.",
                'soft_sell_caption' => "A practical look at {$productName} without exaggerated claims.",
            ],
            'hashtags' => [
                '#ProductDemo',
                '#SocialCommerce',
                '#CreatorTools',
                '#ProductFinds',
                '#ShopSmarter',
                '#PaidSocial',
                '#AdCreative',
                '#UGCAds',
                '#NusaStudio',
                '#ContentWorkflow',
            ],
            'cta_options' => [
                $cta,
                'Check the product page',
                'Learn more before you buy',
                'Save this for later',
            ],
            'compliance_notes' => $this->complianceNotes($offer),
            'render_plan' => [
                'renderer' => 'shotstack_future',
                'render_ready' => $visualSourceType !== 'image',
                'missing_items' => $visualSourceType === 'image'
                    ? ['Motion graphics renderer is not connected yet.']
                    : ['Final Shotstack render pass is not implemented for Auto Video Ads yet.'],
                'recommended_assets' => $this->recommendedAssets($visualSourceType),
                'timeline_strategy' => 'Use a fast hook, two benefit/proof beats, and a clear CTA end card.',
                'overlay_style' => $visualStyle,
                'end_card_strategy' => 'End on product, offer context if provided, and one verified CTA.',
            ],
            'metadata' => [
                'generation_type' => 'auto_video_ad',
                'provider' => 'mock',
                'visual_source_type' => $visualSourceType,
                'campaign_generation_id' => $this->nullableInt($input['campaign_generation_id'] ?? null),
                'product_image_asset_id' => $this->nullableInt($input['product_image_asset_id'] ?? null),
                'source_video_asset_id' => $this->nullableInt($input['source_video_asset_id'] ?? null),
                'voiceover_generation_id' => $this->nullableInt($input['voiceover_generation_id'] ?? null),
                'target_duration_seconds' => $duration,
                'ad_format' => $format,
                'platform' => $platform,
                'ad_goal' => $adGoal,
                'tone' => $tone,
                'visual_style' => $visualStyle,
                'hook_style' => $hookStyle,
                'render_available' => false,
                'shotstack_render_pending' => false,
                'voiceover_ready' => (bool) ($input['voiceover_ready'] ?? false),
                'campaign_source_used' => $this->nullableInt($input['campaign_generation_id'] ?? null) !== null,
            ],
        ];
    }

    /**
     * @return array<int, array{text: string, style: string, why_it_works: string}>
     */
    private function hookOptions(string $productName, string $audience, string $hookStyle): array
    {
        return [
            [
                'text' => "Before you skip, see how {$productName} fits a real workflow.",
                'style' => $hookStyle,
                'why_it_works' => 'Opens with a pattern interrupt while staying factual.',
            ],
            [
                'text' => "If you create content often, {$productName} is worth a closer look.",
                'style' => 'benefit_first',
                'why_it_works' => 'Names the audience and invites attention without overclaiming.',
            ],
            [
                'text' => 'Here is the quick product demo I would show before asking for a click.',
                'style' => 'demo_first',
                'why_it_works' => 'Frames the ad as a useful demonstration instead of hype.',
            ],
            [
                'text' => "For {$audience}, the real question is whether this solves a daily friction.",
                'style' => 'pain_point',
                'why_it_works' => 'Connects the product to a practical problem the viewer can evaluate.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adScript(string $productName, string $description, string $creativeAngle, string $cta, ?string $offer): array
    {
        $offerLine = $offer === null
            ? 'No offer was provided, so keep the CTA focused on learning more.'
            : "Mention the offer only if it is currently valid: {$offer}.";

        return [
            'opening_hook' => "Quick look at {$productName}.",
            'problem_or_context' => 'Show the viewer the everyday context before presenting the product.',
            'product_benefit_sequence' => [
                "Introduce {$productName} with a clear visual.",
                Str::limit($description, 120, ''),
                'Show one practical usage moment or product detail.',
            ],
            'proof_or_demo_line' => 'Use visual proof from the asset instead of making unsupported performance claims.',
            'CTA' => $cta,
            'voiceover_ready_script' => "Quick look at {$productName}. {$creativeAngle} Show the product in use, highlight one practical detail, then close with: {$cta}. {$offerLine}",
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function storyboard(string $productName, int $duration, string $visualSourceType, string $cta): array
    {
        $cuts = $this->timelineCuts($duration);

        return [
            [
                'scene_number' => 1,
                'start_seconds' => $cuts[0][0],
                'end_seconds' => $cuts[0][1],
                'visual_direction' => "Open with the clearest {$productName} shot.",
                'overlay_text' => 'Worth a quick look?',
                'narration_line' => "Here is {$productName} in context.",
                'asset_role' => $visualSourceType === 'video' ? 'product_video' : 'product_image',
                'transition_note' => 'Fast clean cut into benefit.',
            ],
            [
                'scene_number' => 2,
                'start_seconds' => $cuts[1][0],
                'end_seconds' => $cuts[1][1],
                'visual_direction' => 'Show the main benefit with a product-focused frame.',
                'overlay_text' => 'Clear benefit',
                'narration_line' => 'Focus on one practical reason the viewer should care.',
                'asset_role' => $visualSourceType === 'image' ? 'campaign_text' : 'product_video',
                'transition_note' => 'Use a subtle zoom or cutaway.',
            ],
            [
                'scene_number' => 3,
                'start_seconds' => $cuts[2][0],
                'end_seconds' => $cuts[2][1],
                'visual_direction' => 'Add proof through a detail, usage moment, or close-up.',
                'overlay_text' => 'See the detail',
                'narration_line' => 'Let the visual demonstrate the product instead of exaggerating.',
                'asset_role' => $visualSourceType === 'image' ? 'product_image' : 'product_video',
                'transition_note' => 'Keep this section simple and readable.',
            ],
            [
                'scene_number' => 4,
                'start_seconds' => $cuts[3][0],
                'end_seconds' => $cuts[3][1],
                'visual_direction' => 'End with a stable product shot and CTA card.',
                'overlay_text' => $cta,
                'narration_line' => $cta,
                'asset_role' => 'end_card',
                'transition_note' => 'Hold long enough for the CTA to read.',
            ],
        ];
    }

    /**
     * @return array<int, array{text: string, placement: string, timing: string, style: string, purpose: string}>
     */
    private function overlayPlan(string $productName, int $duration, string $visualStyle, string $cta, ?string $offer): array
    {
        return [
            [
                'text' => "{$productName} in action",
                'placement' => 'upper third',
                'timing' => '0-3s',
                'style' => $visualStyle,
                'purpose' => 'Establish product context quickly.',
            ],
            [
                'text' => $offer ?? 'Practical product detail',
                'placement' => 'lower third',
                'timing' => '4-9s',
                'style' => 'clean callout',
                'purpose' => 'Add one benefit or verified offer without clutter.',
            ],
            [
                'text' => $cta,
                'placement' => 'center end card',
                'timing' => max(0, $duration - 3).'-'.$duration.'s',
                'style' => 'clear CTA',
                'purpose' => 'Close with one readable action.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function complianceNotes(?string $offer): array
    {
        $notes = [
            'Do not invent discounts, prices, guarantees or performance claims.',
            'Paid ad claims should be verified before publishing.',
        ];

        if ($offer === null) {
            $notes[] = 'No offer was provided, so avoid implying a sale or discount.';
        } else {
            $notes[] = 'Only use the provided offer if it is still valid on the landing page.';
        }

        return $notes;
    }

    /**
     * @return array<int, string>
     */
    private function recommendedAssets(string $visualSourceType): array
    {
        return match ($visualSourceType) {
            'hybrid' => ['Product image for end card', 'Source video for demo clips', 'Campaign text for overlays'],
            'video' => ['Source video for demo clips', 'Product still or logo for future end card'],
            default => ['Product image for hero visual', 'Optional source video for stronger demo proof'],
        };
    }

    /**
     * @return array<int, array{0: int, 1: int}>
     */
    private function timelineCuts(int $duration): array
    {
        $first = min(3, max(2, (int) floor($duration * 0.2)));
        $second = min($duration - 6, max($first + 3, (int) floor($duration * 0.47)));
        $third = min($duration - 3, max($second + 3, (int) floor($duration * 0.75)));

        return [
            [0, $first],
            [$first, $second],
            [$second, $third],
            [$third, $duration],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function campaignContext(array $input): ?string
    {
        $campaign = $input['campaign_generation_summary'] ?? null;

        if (! is_array($campaign)) {
            return null;
        }

        return $this->nullableText($campaign['campaign_angle'] ?? null)
            ?? $this->nullableText($campaign['campaign_title'] ?? null)
            ?? $this->nullableText($campaign['video_summary'] ?? null)
            ?? $this->nullableText($campaign['concept_summary'] ?? null);
    }

    private function creativeAngle(string $productName, string $description, string $adGoal, ?string $campaign): string
    {
        if ($campaign !== null) {
            return Str::limit($campaign, 150, '');
        }

        return "Position {$productName} around a practical {$adGoal} message: ".Str::limit($description, 120, '');
    }

    private function text(mixed $value, string $fallback): string
    {
        $text = $this->nullableText($value);

        return $text ?? $fallback;
    }

    private function nullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function positiveInt(mixed $value, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return max(1, (int) $value);
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
