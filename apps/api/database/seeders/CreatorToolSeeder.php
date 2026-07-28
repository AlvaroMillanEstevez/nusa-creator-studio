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

        $this->applyTaxonomyDefaults($creatorTool, $tool);
        $this->applyMetadataDefaults($creatorTool, $tool);

        if (in_array($tool['key'], ['affiliate_campaign', 'video_to_campaign', 'ai_voiceover', 'fashion_try_on', 'motion_ai_video', 'auto_video_editor', 'auto_video_ads'], true)) {
            $this->refreshLegacyLaunchCopy($creatorTool, $tool);
        }

        $creatorTool->save();
    }

    /**
     * @param  array<string, mixed>  $tool
     */
    private function applyTaxonomyDefaults(CreatorTool $creatorTool, array $tool): void
    {
        if (($creatorTool->service_tier === null || $creatorTool->service_tier === 'basic') && $tool['service_tier'] !== 'basic') {
            $creatorTool->service_tier = $tool['service_tier'];
        }

        if ($creatorTool->output_type === null) {
            $creatorTool->output_type = $tool['output_type'];
        }

        if (($creatorTool->implementation_stage === null || $creatorTool->implementation_stage === 'mock') && $tool['implementation_stage'] !== 'mock') {
            $creatorTool->implementation_stage = $tool['implementation_stage'];
        }
    }

    /**
     * @param  array<string, mixed>  $tool
     */
    private function applyMetadataDefaults(CreatorTool $creatorTool, array $tool): void
    {
        if (! isset($tool['metadata'])) {
            return;
        }

        $metadata = $creatorTool->metadata ?? [];

        if (! is_array($metadata)) {
            $metadata = [];
        }

        foreach ($tool['metadata'] as $key => $value) {
            if (! array_key_exists($key, $metadata)) {
                $metadata[$key] = $value;
            }
        }

        $creatorTool->metadata = $metadata;
    }

    /**
     * @param  array<string, mixed>  $tool
     */
    private function refreshLegacyLaunchCopy(CreatorTool $creatorTool, array $tool): void
    {
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

        if (in_array($tool['key'], ['affiliate_campaign', 'video_to_campaign', 'fashion_try_on', 'motion_ai_video', 'auto_video_editor', 'auto_video_ads'], true)) {
            $creatorTool->status = $tool['status'];
            $creatorTool->implementation_stage = $tool['implementation_stage'];
            $creatorTool->route = $tool['route'];
            $creatorTool->credit_cost = $tool['credit_cost'];
            $creatorTool->credit_cost_label = $tool['credit_cost_label'];
            $creatorTool->badge_label = $tool['badge_label'];
            $creatorTool->sort_order = $tool['sort_order'];
        }

        if ($tool['key'] === 'motion_ai_video' && $creatorTool->implementation_stage === 'future') {
            $creatorTool->implementation_stage = $tool['implementation_stage'];
        }

        if ($tool['key'] === 'auto_video_ads') {
            $creatorTool->implementation_stage = $tool['implementation_stage'];
            $creatorTool->route = $tool['route'];
            $creatorTool->credit_cost = $tool['credit_cost'];
            $creatorTool->credit_cost_label = $tool['credit_cost_label'];
            $creatorTool->badge_label = $tool['badge_label'];
        }

        if ($creatorTool->route === null || ($tool['key'] === 'motion_ai_video' && $creatorTool->route === '/motion-video/new')) {
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
                'short_description' => [
                    'Create motion-led video concepts from product or creator assets.',
                    'Prepare short-form motion video concepts from product, image, video or brand references.',
                ],
                'long_description' => [
                    'Turn product and creator assets into concise vertical-video concepts with clear motion direction.',
                    'Turn project assets into a structured AI video generation plan for vertical creator content.',
                ],
            ],
            'auto_video_ads' => [
                'short_description' => ['Plan paid video ad creatives from project content and assets.'],
                'long_description' => ['Future premium workflow for paid social video ads.'],
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
                'title' => 'Product Content Kit',
                'short_description' => 'Create reusable campaign content from your product brief, including hooks, scripts, captions, CTA suggestions and publishing guidance.',
                'long_description' => 'Create a reusable content kit from product and project information. Footage analysis is handled separately through Analyze Footage with AI.',
                'category' => 'campaign_creation',
                'service_tier' => 'basic',
                'output_type' => 'script_pack',
                'implementation_stage' => 'mock',
                'status' => 'available',
                'route' => '/campaigns/new',
                'credit_cost' => 1,
                'credit_cost_label' => '1 credit',
                'badge_label' => 'Available now',
                'is_recommended' => true,
                'is_visible' => true,
                'sort_order' => 10,
                'metadata' => [
                    'service_note' => 'Low-cost content intelligence for scripts, hooks, captions and campaign ideas.',
                ],
            ],
            [
                'key' => 'video_enhancer',
                'title' => 'Video Enhancer / Caption Pack',
                'short_description' => 'Create subtitles, edit ideas, captions, CTA and posting copy from a recorded-video brief.',
                'long_description' => 'Polish an existing product video with a practical edit plan and complete posting copy pack.',
                'category' => 'video_production',
                'service_tier' => 'advanced',
                'output_type' => 'assisted_media',
                'implementation_stage' => 'mock',
                'status' => 'available',
                'route' => '/video-enhancer/new',
                'credit_cost' => 1,
                'credit_cost_label' => '1 credit',
                'badge_label' => 'Available now',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 20,
                'metadata' => [
                    'service_note' => 'Currently a mock edit plan; future real v1 should analyze uploaded video, transcripts or frames.',
                ],
            ],
            [
                'key' => 'video_to_campaign',
                'title' => 'Analyze Footage with AI',
                'short_description' => 'Analyze product footage to identify key moments, recommended cuts, campaign angles, captions, narration and a reusable edit plan.',
                'long_description' => 'Use product footage as the source for observed product moments, campaign direction, narration, captions, recommended cuts and a reusable edit plan for Auto Video Editor.',
                'category' => 'video_production',
                'service_tier' => 'advanced',
                'output_type' => 'assisted_media',
                'implementation_stage' => 'beta',
                'status' => 'available',
                'route' => '/dashboard?tool=video_to_campaign',
                'credit_cost' => 2,
                'credit_cost_label' => '2 credits',
                'badge_label' => 'Available now',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 20,
                'metadata' => [
                    'service_note' => 'Analyzes product footage and stores reusable video-analysis context for downstream editor and voiceover actions.',
                ],
            ],
            [
                'key' => 'product_to_video',
                'title' => 'Product-to-Video Script Pack',
                'short_description' => 'Create a storyboard, scene plan, voiceover direction and on-screen text from product details.',
                'long_description' => 'Build a production-ready vertical video plan before recording product footage.',
                'category' => 'video_production',
                'service_tier' => 'advanced',
                'output_type' => 'assisted_media',
                'implementation_stage' => 'mock',
                'status' => 'available',
                'route' => '/product-to-video/new',
                'credit_cost' => 1,
                'credit_cost_label' => '1 credit',
                'badge_label' => 'Available now',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 30,
                'metadata' => [
                    'service_note' => 'Current script and storyboard workflow; future real v1 can move from product image or URL to storyboard and prompt, then future v2 to image-to-video.',
                ],
            ],
            [
                'key' => 'ai_voiceover',
                'title' => 'AI Voiceover',
                'short_description' => 'Generate voiceover scripts, delivery direction and audio-ready narration notes.',
                'long_description' => 'Turn a product brief or campaign script into a voiceover-ready narration plan for short-form video.',
                'category' => 'audio',
                'service_tier' => 'advanced',
                'output_type' => 'audio',
                'implementation_stage' => 'real_provider',
                'status' => 'available',
                'route' => '/voiceover/new',
                'credit_cost' => 1,
                'credit_cost_label' => '1 credit',
                'badge_label' => 'Core beta',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 40,
                'metadata' => [
                    'service_note' => 'Future real provider should generate audio assets.',
                ],
            ],
            [
                'key' => 'fashion_try_on',
                'title' => 'AI Fashion Try-On',
                'short_description' => 'Combine a person photo with a clothing or wearable product image to create a realistic fashion result.',
                'long_description' => 'Create an AI Fashion Try-On from one person photo and one clothing or wearable product image. Standard is prepared for FASHN tryon-v1.6; Premium remains disabled until tryon-max is validated.',
                'category' => 'fashion',
                'service_tier' => 'premium',
                'output_type' => 'image',
                'implementation_stage' => 'real_provider',
                'status' => 'available',
                'route' => '/dashboard?tool=ai_fashion_try_on',
                'credit_cost' => 2,
                'credit_cost_label' => '2 credits',
                'badge_label' => 'Available now',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 50,
                'metadata' => [
                    'service_note' => 'Standard uses FASHN when the real provider is enabled for internal users. Mock mode remains an honest preview plan. Premium is disabled until validation.',
                ],
            ],
            [
                'key' => 'motion_ai_video',
                'title' => 'Motion AI Video',
                'short_description' => 'Generate premium AI product footage from a description, a saved campaign or a product image.',
                'long_description' => 'Generate short vertical product footage from project context, campaign-assisted creative direction or an enabled product image first frame.',
                'category' => 'ai_video',
                'service_tier' => 'premium',
                'output_type' => 'video',
                'implementation_stage' => 'real_provider',
                'status' => 'available',
                'route' => '/dashboard?tool=motion_ai_video',
                'credit_cost' => 3,
                'credit_cost_label' => '3 credits',
                'badge_label' => 'Available now',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 60,
                'metadata' => [
                    'service_note' => 'Creates Motion AI Video outputs through the configured provider mode and stores reusable private MP4 assets when rendering completes.',
                ],
            ],
            [
                'key' => 'auto_video_editor',
                'title' => 'Auto Video Editor',
                'short_description' => 'Turn raw or AI-generated product footage into an edited creator video using manual setup or a previous AI footage analysis.',
                'long_description' => 'Render or plan an edited vertical creator video from project footage, optional footage analysis and optional voiceover.',
                'category' => 'ai_video',
                'service_tier' => 'premium',
                'output_type' => 'video',
                'implementation_stage' => 'real_provider',
                'status' => 'available',
                'route' => '/dashboard?tool=auto_video_editor',
                'credit_cost' => 2,
                'credit_cost_label' => '2 credits base',
                'badge_label' => 'Available now',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 65,
                'metadata' => [
                    'service_note' => 'Uses manual setup or completed footage analysis with optional voiceover to create a reusable edited-video result.',
                ],
            ],
            [
                'key' => 'auto_video_ads',
                'title' => 'Auto Video Ads',
                'short_description' => 'Create paid ad creative plans with hook, benefits, overlays, CTA and end card from your campaign and product assets.',
                'long_description' => 'Planned paid social ad workflow for campaign content, product footage, overlays, CTA and end cards.',
                'category' => 'paid_ads',
                'service_tier' => 'premium',
                'output_type' => 'ad_plan',
                'implementation_stage' => 'future',
                'status' => 'coming_soon',
                'route' => null,
                'credit_cost' => 0,
                'credit_cost_label' => 'Credit cost coming soon',
                'badge_label' => 'Coming soon',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 67,
                'metadata' => [
                    'service_note' => 'Coming soon paid social ad workflow. It is intentionally not runnable in the public catalog yet.',
                ],
            ],
            [
                'key' => 'ai_avatar_presenter',
                'title' => 'AI Avatar Presenter',
                'short_description' => 'Create product presenter videos using an AI avatar and voice.',
                'long_description' => 'Future premium workflow for avatar-led product ads and social commerce videos.',
                'category' => 'avatar',
                'service_tier' => 'premium',
                'output_type' => 'avatar',
                'implementation_stage' => 'future',
                'status' => 'coming_soon',
                'route' => null,
                'credit_cost' => 0,
                'credit_cost_label' => 'Credit cost coming soon',
                'badge_label' => 'Future',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 70,
                'metadata' => [
                    'service_note' => 'Future premium workflow for avatar-led product ads and social commerce videos.',
                ],
            ],
            [
                'key' => 'custom_ai_avatar',
                'title' => 'Custom AI Avatar',
                'short_description' => 'Create a reusable avatar from a person image, photo set, or description.',
                'long_description' => 'Future premium workflow for creating custom avatar identities for product videos.',
                'category' => 'avatar',
                'service_tier' => 'premium',
                'output_type' => 'avatar',
                'implementation_stage' => 'future',
                'status' => 'coming_soon',
                'route' => null,
                'credit_cost' => 0,
                'credit_cost_label' => 'Credit cost coming soon',
                'badge_label' => 'Future',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 80,
                'metadata' => [
                    'service_note' => 'Future premium workflow for creating custom avatar identities for product videos.',
                ],
            ],
            [
                'key' => 'avatar_voice_ads',
                'title' => 'Avatar Voice Ads',
                'short_description' => 'Combine AI avatar presenters, voiceover and product scripts into social ads.',
                'long_description' => 'Future premium workflow for generated avatar-led product ad creatives.',
                'category' => 'avatar',
                'service_tier' => 'premium',
                'output_type' => 'mixed_media',
                'implementation_stage' => 'future',
                'status' => 'coming_soon',
                'route' => null,
                'credit_cost' => 0,
                'credit_cost_label' => 'Credit cost coming soon',
                'badge_label' => 'Future',
                'is_recommended' => false,
                'is_visible' => true,
                'sort_order' => 90,
                'metadata' => [
                    'service_note' => 'Future premium workflow for generated avatar-led product ad creatives.',
                ],
            ],
        ];
    }
}
