<?php

namespace Database\Seeders;

use App\Models\CreatorTool;
use Illuminate\Database\Seeder;

class CreatorToolSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->tools() as $tool) {
            $this->seedTool($tool);
        }
    }

    /**
     * @param  array<string, mixed>  $tool
     */
    private function seedTool(array $tool): void
    {
        /** @var CreatorTool $creatorTool */
        $creatorTool = CreatorTool::query()->firstOrNew(['key' => $tool['key']]);

        if (! $creatorTool->exists) {
            $creatorTool->fill($tool)->save();

            return;
        }

        if (! in_array($tool['key'], ['ai_voiceover', 'fashion_try_on', 'motion_ai_video'], true)) {
            return;
        }

        $legacy = $this->legacyValues($tool['key']);

        if (in_array($creatorTool->short_description, $legacy['short_description'], true)) {
            $creatorTool->short_description = $tool['short_description'];
        }

        if (in_array($creatorTool->long_description, $legacy['long_description'], true)) {
            $creatorTool->long_description = $tool['long_description'];
        }

        if ($creatorTool->status === 'coming_soon') {
            $creatorTool->status = $tool['status'];
        }

        if ($creatorTool->route === null) {
            $creatorTool->route = $tool['route'];
        }

        if ($creatorTool->credit_cost_label === null || $creatorTool->credit_cost_label === 'Credit cost coming soon') {
            $creatorTool->credit_cost = $tool['credit_cost'];
            $creatorTool->credit_cost_label = $tool['credit_cost_label'];
        }

        if ($creatorTool->badge_label === null || $creatorTool->badge_label === 'Coming soon') {
            $creatorTool->badge_label = $tool['badge_label'];
        }

        if ((int) $creatorTool->sort_order === 0) {
            $creatorTool->sort_order = $tool['sort_order'];
        }

        $creatorTool->save();
    }

    /**
     * @return array{short_description: array<int, string>, long_description: array<int, string>}
     */
    private function legacyValues(string $key): array
    {
        return match ($key) {
            'ai_voiceover' => [
                'short_description' => ['Generate natural product-ad voiceovers in creator-friendly styles.'],
                'long_description' => ['Prepare clear voice tracks for product campaigns across supported languages and tones.'],
            ],
            'fashion_try_on' => [
                'short_description' => ['Create creator-led try-on concepts for fashion products.'],
                'long_description' => ['Plan fashion-focused visual concepts that present products in realistic creator scenarios.'],
            ],
            'motion_ai_video' => [
                'short_description' => ['Create motion-led video concepts from product or creator assets.'],
                'long_description' => ['Turn product and creator assets into concise vertical-video concepts with clear motion direction.'],
            ],
            default => ['short_description' => [], 'long_description' => []],
        };
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
                'short_description' => 'Generate voiceover scripts, delivery direction and audio-ready narration notes.',
                'long_description' => 'Turn a product brief or campaign script into a voiceover-ready narration plan for short-form video.',
                'category' => 'audio',
                'status' => 'available',
                'route' => '/voiceover/new',
                'credit_cost' => 1,
                'credit_cost_label' => '1 credit',
                'badge_label' => 'New mock',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 40,
            ],
            [
                'key' => 'fashion_try_on',
                'title' => 'Fashion Try-On',
                'short_description' => 'Prepare product-model try-on concepts using uploaded model and garment references.',
                'long_description' => 'Combine a model/person reference and a garment/product reference into a structured try-on generation plan.',
                'category' => 'fashion',
                'status' => 'available',
                'route' => '/fashion-try-on/new',
                'credit_cost' => 2,
                'credit_cost_label' => '2 credits',
                'badge_label' => 'New mock',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 50,
            ],
            [
                'key' => 'motion_ai_video',
                'title' => 'Motion AI Video',
                'short_description' => 'Prepare short-form motion video concepts from product, image, video or brand references.',
                'long_description' => 'Turn project assets into a structured AI video generation plan for vertical creator content.',
                'category' => 'ai_video',
                'status' => 'available',
                'route' => '/motion-video/new',
                'credit_cost' => 3,
                'credit_cost_label' => '3 credits',
                'badge_label' => 'New mock',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 60,
            ],
        ];
    }
}