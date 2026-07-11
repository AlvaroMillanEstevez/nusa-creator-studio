<?php

namespace Tests\Feature;

use App\Services\Generation\AutoVideoEditorCreativeBriefBuilder;
use App\Services\Generation\ShotstackTimelineBuilder;
use Tests\TestCase;

class AutoVideoEditorTimelineQualityTest extends TestCase
{
    public function test_timeline_extends_short_clips_to_fit_longer_voiceover(): void
    {
        $timeline = app(ShotstackTimelineBuilder::class)->build(
            sourceVideoUrl: 'https://assets.nusa.test/source.mp4',
            voiceoverUrl: 'https://assets.nusa.test/voiceover.mp3',
            input: [
                'platform' => 'tiktok',
                'format' => 'vertical_9_16',
                'creative_brief' => [
                    'target_duration_seconds' => 15,
                    'voiceover_duration_seconds' => 18.0,
                    'effective_target_duration_seconds' => 19,
                    'selected_cta' => 'Review the product details before buying.',
                    'caption_style' => 'premium_clean',
                    'preferred_cuts' => [
                        [
                            'start_seconds' => 0,
                            'end_seconds' => 3,
                            'purpose' => 'hook',
                            'visual_reason' => 'Clear opening product shot.',
                            'caption' => 'Small creator light',
                        ],
                        [
                            'start_seconds' => 3,
                            'end_seconds' => 6,
                            'purpose' => 'demo',
                            'visual_reason' => 'Shows the product in use.',
                            'caption' => 'Use it on your desk',
                        ],
                    ],
                ],
            ],
        );

        $this->assertGreaterThanOrEqual(18.5, $timeline['timeline_summary']['final_duration_seconds']);
        $this->assertSame('repeated_product_clip', $timeline['timeline_summary']['duration_sync_strategy']);
        $this->assertSame(18.0, $timeline['timeline_summary']['voiceover_duration_seconds']);
        $this->assertSame('Review the product details before buying.', $timeline['timeline_summary']['cta_used']);
        $this->assertContains('repeated_product_clip', array_column($timeline['timeline'], 'source'));
        $this->assertContains('cta_end_card', array_column($timeline['timeline'], 'source'));
    }

    public function test_caption_schedule_is_short_limited_and_non_overlapping(): void
    {
        $timeline = app(ShotstackTimelineBuilder::class)->build(
            sourceVideoUrl: 'https://assets.nusa.test/source.mp4',
            voiceoverUrl: 'https://assets.nusa.test/voiceover.mp3',
            input: [
                'creative_brief' => [
                    'target_duration_seconds' => 15,
                    'voiceover_duration_seconds' => 18.0,
                    'effective_target_duration_seconds' => 19,
                    'selected_cta' => 'Would you try this? Learn more below!',
                    'preferred_cuts' => [
                        ['start_seconds' => 0, 'end_seconds' => 2.5, 'caption' => 'Compact ice at home'],
                        ['start_seconds' => 2.5, 'end_seconds' => 5.5, 'caption' => 'Fast daily ice'],
                        ['start_seconds' => 5.5, 'end_seconds' => 8.5, 'caption' => 'Easy countertop setup'],
                    ],
                ],
            ],
        );

        $schedule = $timeline['captions_plan']['caption_schedule'];

        $this->assertGreaterThanOrEqual(3, count($schedule));
        $this->assertLessThanOrEqual(5, count($schedule));
        $this->assertFalse($timeline['captions_plan']['captions_overlapping']);
        $this->assertFalse($timeline['timeline_summary']['captions_overlapping']);
        $this->assertSame($schedule, $timeline['timeline_summary']['caption_schedule']);

        $previousEnd = 0.0;
        foreach ($schedule as $item) {
            $this->assertGreaterThanOrEqual(1.8, $item['length']);
            $this->assertLessThanOrEqual(3.2, $item['length']);
            $this->assertGreaterThanOrEqual($previousEnd + 0.19, $item['start']);
            $previousEnd = $item['start'] + $item['length'];
        }

        $this->assertSame('cta', $schedule[count($schedule) - 1]['type']);
        $this->assertGreaterThanOrEqual($timeline['timeline_summary']['final_duration_seconds'] - 4.0, $schedule[count($schedule) - 1]['start']);
    }

    public function test_shotstack_output_is_vertical_social_video_with_cover_crop_metadata(): void
    {
        $timeline = app(ShotstackTimelineBuilder::class)->build(
            sourceVideoUrl: 'https://assets.nusa.test/source.mp4',
            voiceoverUrl: null,
            input: [
                'target_duration_seconds' => 15,
                'creative_brief' => [
                    'selected_cta' => 'Check the details.',
                    'preferred_cuts' => [
                        ['start_seconds' => 0, 'end_seconds' => 4, 'caption' => 'Product demo'],
                    ],
                ],
            ],
        );

        $this->assertSame('9:16', $timeline['edit']['output']['aspectRatio']);
        $this->assertSame(720, $timeline['edit']['output']['size']['width']);
        $this->assertSame(1280, $timeline['edit']['output']['size']['height']);
        $this->assertSame('9:16', $timeline['render_settings']['output_aspect_ratio']);
        $this->assertSame(720, $timeline['render_settings']['output_width']);
        $this->assertSame(1280, $timeline['render_settings']['output_height']);
        $this->assertSame('cover_crop_to_vertical', $timeline['render_settings']['source_fit_strategy']);
        $this->assertFalse($timeline['render_settings']['has_letterboxing_expected']);

        $videoClip = $timeline['edit']['timeline']['tracks'][1]['clips'][0];
        $this->assertSame('cover', $videoClip['fit']);
        $this->assertSame('center', $videoClip['position']);
    }

    public function test_captions_are_wrapped_and_have_safe_area_metadata(): void
    {
        $timeline = app(ShotstackTimelineBuilder::class)->build(
            sourceVideoUrl: 'https://assets.nusa.test/source.mp4',
            voiceoverUrl: null,
            input: [
                'caption_style' => 'bold_keywords',
                'creative_brief' => [
                    'target_duration_seconds' => 15,
                    'effective_target_duration_seconds' => 15,
                    'selected_cta' => 'Check the details.',
                    'caption_style' => 'bold_keywords',
                    'preferred_cuts' => [
                        [
                            'start_seconds' => 0,
                            'end_seconds' => 4,
                            'caption' => 'This is a very long caption that should be split safely before it can overflow the mobile safe area',
                        ],
                        [
                            'start_seconds' => 4,
                            'end_seconds' => 7,
                            'caption' => 'Final product shot',
                        ],
                    ],
                ],
            ],
        );

        $caption = $timeline['captions_plan']['sample_captions'][0];
        $captionClip = $timeline['edit']['timeline']['tracks'][0]['clips'][0];

        $this->assertLessThanOrEqual(2, count(explode("\n", $caption)));
        foreach (explode("\n", $caption) as $line) {
            $this->assertLessThanOrEqual(22, mb_strlen($line));
        }
        $this->assertSame('bottom_center', $timeline['captions_plan']['safe_area']['position']);
        $this->assertSame(72, $timeline['captions_plan']['safe_area']['width_percent']);
        $this->assertSame(520, $timeline['captions_plan']['safe_area']['width_pixels']);
        $this->assertSame(120, $timeline['captions_plan']['safe_area']['height_pixels']);
        $this->assertSame(18, $timeline['captions_plan']['safe_area']['bottom_margin_percent']);
        $this->assertSame(2, $timeline['captions_plan']['safe_area']['max_lines']);
        $this->assertTrue($timeline['captions_plan']['caption_safe_area_applied']);
        $this->assertNotEmpty($timeline['captions_plan']['caption_warnings']);
        $this->assertSame('html', $captionClip['asset']['type']);
        $this->assertSame(520, $captionClip['asset']['width']);
        $this->assertSame(120, $captionClip['asset']['height']);
        $this->assertSame('bottom', $captionClip['position']);
        $this->assertSame(0.22, $captionClip['offset']['y']);
        $this->assertStringContainsString('display:inline-block', $captionClip['asset']['css']);
        $this->assertStringContainsString('max-width:460px', $captionClip['asset']['css']);
        $this->assertStringContainsString('letter-spacing:0', $captionClip['asset']['css']);
        $this->assertStringContainsString('text-transform:none', $captionClip['asset']['css']);
        $this->assertStringNotContainsString('letter-spacing:4', $captionClip['asset']['css']);
    }

    public function test_cta_end_card_caption_stays_inside_safe_bounds(): void
    {
        $timeline = app(ShotstackTimelineBuilder::class)->build(
            sourceVideoUrl: 'https://assets.nusa.test/source.mp4',
            voiceoverUrl: 'https://assets.nusa.test/voiceover.mp3',
            input: [
                'creative_brief' => [
                    'target_duration_seconds' => 8,
                    'voiceover_duration_seconds' => 14.0,
                    'effective_target_duration_seconds' => 15,
                    'selected_cta' => 'Check details before buying and compare the seller information carefully.',
                    'preferred_cuts' => [
                        ['start_seconds' => 0, 'end_seconds' => 2.5, 'caption' => 'Product detail'],
                    ],
                ],
            ],
        );

        $captionTrack = collect($timeline['edit']['timeline']['tracks'])
            ->first(fn (array $track): bool => data_get($track, 'clips.0.asset.type') === 'html');
        $lastCaptionClip = $captionTrack['clips'][count($captionTrack['clips']) - 1];
        $lastScheduleItem = $timeline['captions_plan']['caption_schedule'][count($timeline['captions_plan']['caption_schedule']) - 1];
        $lastCaptionText = $lastScheduleItem['text'];

        $this->assertSame('cta_end_card', $timeline['timeline'][count($timeline['timeline']) - 1]['source']);
        $this->assertSame('cta', $lastScheduleItem['type']);
        $this->assertSame(520, $lastCaptionClip['asset']['width']);
        $this->assertSame(120, $lastCaptionClip['asset']['height']);
        $this->assertSame('bottom', $lastCaptionClip['position']);
        $this->assertSame(0.18, $lastCaptionClip['offset']['y']);
        $this->assertLessThanOrEqual(2, count(explode("\n", $lastCaptionText)));
        foreach (explode("\n", $lastCaptionText) as $line) {
            $this->assertLessThanOrEqual(22, mb_strlen($line));
        }
    }

    public function test_invalid_cut_timestamps_are_clamped_to_safe_positive_ranges(): void
    {
        $timeline = app(ShotstackTimelineBuilder::class)->build(
            sourceVideoUrl: 'https://assets.nusa.test/source.mp4',
            voiceoverUrl: null,
            input: [
                'target_duration_seconds' => 15,
                'creative_brief' => [
                    'selected_cta' => 'Check the details.',
                    'preferred_cuts' => [
                        ['start_seconds' => -5, 'end_seconds' => -1, 'caption' => 'Bad negative timing'],
                        ['start_seconds' => 6, 'end_seconds' => 4, 'caption' => 'Impossible timing'],
                    ],
                ],
            ],
        );

        foreach ($timeline['timeline'] as $item) {
            $this->assertGreaterThanOrEqual(0, $item['start_seconds']);
            $this->assertGreaterThanOrEqual(0.5, $item['duration_seconds']);
            $this->assertGreaterThanOrEqual(0, $item['source_start_seconds']);
            $this->assertGreaterThan($item['source_start_seconds'], $item['source_end_seconds']);
        }
    }

    public function test_creative_brief_uses_context_cta_and_voiceover_duration(): void
    {
        $brief = app(AutoVideoEditorCreativeBriefBuilder::class)->build([
            'include_voiceover' => true,
            'target_duration_seconds' => 15,
            'editing_style' => 'review',
            'pacing' => 'steady',
            'caption_style' => 'clean_subtitles',
            'source_video_to_campaign_summary' => [
                'cta_options' => ['Review the product details before buying.'],
                'edit_plan' => [
                    'timeline' => [
                        ['start_seconds' => 0, 'end_seconds' => 3, 'caption' => 'Open cleanly'],
                    ],
                ],
            ],
            'voiceover_generation_summary' => [
                'audio' => ['duration_seconds' => 22.4],
            ],
        ]);

        $this->assertSame('Review the product details before buying.', $brief['selected_cta']);
        $this->assertSame(22.4, $brief['voiceover_duration_seconds']);
        $this->assertSame(23, $brief['effective_target_duration_seconds']);
        $this->assertSame(15, $brief['target_duration_seconds']);
        $this->assertSame('review', $brief['editing_style']);
        $this->assertNotEmpty($brief['preferred_cuts']);

        $override = app(AutoVideoEditorCreativeBriefBuilder::class)->build([
            'cta' => 'Use my custom CTA.',
            'source_video_to_campaign_summary' => [
                'cta_options' => ['Ignore this when form CTA exists.'],
            ],
        ]);

        $this->assertSame('Use my custom CTA.', $override['selected_cta']);
    }
}
