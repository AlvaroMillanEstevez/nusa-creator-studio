<?php

namespace Database\Seeders;

use App\Models\CreatorTool;
use Illuminate\Database\Seeder;

class CreatorToolSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->tools() as $tool) {
            CreatorTool::query()->firstOrCreate(
                ['key' => $tool['key']],
                $tool,
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tools(): array
    {
        return [
            [
                'key' => 'affiliate_campaign',
                'title' => 'Affiliate Campaign Generator',
                'short_description' => 'Generate hooks, a TikTok script, caption, hashtags, CTA and filming notes.',
                'long_description' => 'Turn a product brief into a structured affiliate campaign ready to film and refine.',
                'category' => 'campaign_creation',
                'status' => 'available',
                'route' => '/campaigns/new',
                'credit_cost' => 1,
                'credit_cost_label' => '1 credit',
                'badge_label' => 'Available now',
                'is_recommended' => true,
                'is_visible' => true,
                'sort_order' => 10,
            ],
            [
                'key' => 'video_enhancer',
                'title' => 'Video Enhancer / Caption Pack',
                'short_description' => 'Create subtitles, edit ideas, captions, CTA and posting copy from a recorded-video brief.',
                'long_description' => 'Polish an existing product video with a practical edit plan and complete posting copy pack.',
                'category' => 'video_production',
                'status' => 'available',
                'route' => '/video-enhancer/new',
                'credit_cost' => 1,
                'credit_cost_label' => '1 credit',
                'badge_label' => 'Available now',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 20,
            ],
            [
                'key' => 'product_to_video',
                'title' => 'Product-to-Video Script Pack',
                'short_description' => 'Create a storyboard, scene plan, voiceover direction and on-screen text from product details.',
                'long_description' => 'Build a production-ready vertical video plan before recording product footage.',
                'category' => 'video_production',
                'status' => 'available',
                'route' => '/product-to-video/new',
                'credit_cost' => 1,
                'credit_cost_label' => '1 credit',
                'badge_label' => 'Available now',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 30,
            ],
            [
                'key' => 'ai_voiceover',
                'title' => 'AI Voiceover',
                'short_description' => 'Generate natural product-ad voiceovers in creator-friendly styles.',
                'long_description' => 'Prepare clear voice tracks for product campaigns across supported languages and tones.',
                'category' => 'audio',
                'status' => 'coming_soon',
                'route' => null,
                'credit_cost' => 1,
                'credit_cost_label' => 'Credit cost coming soon',
                'badge_label' => 'Coming soon',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 40,
            ],
            [
                'key' => 'fashion_try_on',
                'title' => 'Fashion Try-On',
                'short_description' => 'Create creator-led try-on concepts for fashion products.',
                'long_description' => 'Plan fashion-focused visual concepts that present products in realistic creator scenarios.',
                'category' => 'fashion',
                'status' => 'coming_soon',
                'route' => null,
                'credit_cost' => 1,
                'credit_cost_label' => 'Credit cost coming soon',
                'badge_label' => 'Coming soon',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 50,
            ],
            [
                'key' => 'motion_ai_video',
                'title' => 'Motion AI Video',
                'short_description' => 'Create motion-led video concepts from product or creator assets.',
                'long_description' => 'Turn product and creator assets into concise vertical-video concepts with clear motion direction.',
                'category' => 'ai_video',
                'status' => 'coming_soon',
                'route' => null,
                'credit_cost' => 1,
                'credit_cost_label' => 'Credit cost coming soon',
                'badge_label' => 'Coming soon',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 60,
            ],
        ];
    }
}
