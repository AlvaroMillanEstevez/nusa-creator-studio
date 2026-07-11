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
        $this->assertGreaterThan(0, $timeline['timeline_summary']['repeated_clip_count']);
        $this->assertSame('Voiceover or target duration was longer than the distinct usable source clips.', $timeline['timeline_summary']['repeated_clip_reason']);
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
        $this->assertTrue($timeline['captions_plan']['caption_schedule_validated']);
        $this->assertTrue($timeline['timeline_summary']['caption_schedule_validated']);
        $this->assertSame(count($schedule), $timeline['timeline_summary']['final_caption_count']);
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
        $this->assertStringContainsString('max-width:430px', $captionClip['asset']['css']);
        $this->assertStringContainsString('background:rgba(0, 0, 0, 0)', $captionClip['asset']['css']);
        $this->assertStringContainsString('box-shadow:none', $captionClip['asset']['css']);
        $this->assertSame('shadow_text_light_caption', $timeline['captions_plan']['caption_visual_strategy']);
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

    public function test_invalid_cut_timestamps_are_discarded_and_timeline_ranges_stay_safe(): void
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

        $this->assertSame(2, $timeline['timeline_summary']['invalid_clip_ranges_discarded']);

        foreach ($timeline['timeline'] as $item) {
            $this->assertArrayHasKey('timeline_start_seconds', $item);
            $this->assertArrayHasKey('timeline_end_seconds', $item);
            $this->assertArrayHasKey('source_start_seconds', $item);
            $this->assertArrayHasKey('source_end_seconds', $item);
            $this->assertGreaterThanOrEqual(0, $item['timeline_start_seconds']);
            $this->assertGreaterThan($item['timeline_start_seconds'], $item['timeline_end_seconds']);
            $this->assertGreaterThanOrEqual(0, $item['source_start_seconds']);
            $this->assertGreaterThan($item['source_start_seconds'], $item['source_end_seconds']);
            $this->assertStringNotContainsString('-3s', $item['timestamp']);
            $this->assertStringNotContainsString('16-2s', $item['timestamp']);
        }
    }

    public function test_long_source_video_uses_diverse_segments_before_repeating(): void
    {
        $timeline = app(ShotstackTimelineBuilder::class)->build(
            sourceVideoUrl: 'https://assets.nusa.test/source.mp4',
            voiceoverUrl: null,
            input: [
                'target_duration_seconds' => 20,
                'creative_brief' => [
                    'source_duration_seconds' => 55,
                    'target_duration_seconds' => 20,
                    'selected_cta' => 'Review details before buying.',
                    'preferred_cuts' => [
                        ['start_seconds' => 0, 'end_seconds' => 4, 'caption' => 'Opening reveal', 'priority' => 'high', 'product_visibility' => 'high', 'stability' => 'high', 'visual_quality' => 'high', 'use_for_final_edit' => true],
                        ['start_seconds' => 12, 'end_seconds' => 16, 'caption' => 'Side angle', 'priority' => 'high', 'product_visibility' => 'high', 'stability' => 'medium', 'visual_quality' => 'high', 'use_for_final_edit' => true],
                        ['start_seconds' => 24, 'end_seconds' => 28, 'caption' => 'Button demo', 'priority' => 'high', 'product_visibility' => 'high', 'stability' => 'high', 'visual_quality' => 'medium', 'use_for_final_edit' => true],
                        ['start_seconds' => 36, 'end_seconds' => 40, 'caption' => 'Detail lighting', 'priority' => 'medium', 'product_visibility' => 'high', 'stability' => 'high', 'visual_quality' => 'high', 'use_for_final_edit' => true],
                        ['start_seconds' => 48, 'end_seconds' => 52, 'caption' => 'Final hero shot', 'priority' => 'high', 'product_visibility' => 'high', 'stability' => 'high', 'visual_quality' => 'high', 'use_for_final_edit' => true],
                    ],
                ],
            ],
        );

        $ranges = $timeline['timeline_summary']['source_clip_ranges_used'];

        $this->assertGreaterThanOrEqual(5, count($ranges));
        $this->assertContains('48-52s', $ranges);
        $this->assertSame(0, $timeline['timeline_summary']['repeated_clip_count']);
        $this->assertTrue($timeline['timeline_summary']['diverse_source_segments_used']);
        $this->assertNotContains('repeated_product_clip', array_column($timeline['timeline'], 'source'));
    }

    public function test_long_source_with_sparse_gemini_cuts_adds_even_fallback_segments_before_repeating(): void
    {
        $timeline = app(ShotstackTimelineBuilder::class)->build(
            sourceVideoUrl: 'https://assets.nusa.test/source.mp4',
            voiceoverUrl: 'https://assets.nusa.test/voiceover.mp3',
            input: [
                'target_duration_seconds' => 30,
                'source_video_to_campaign_summary' => [
                    'recommended_cuts' => [
                        ['start_seconds' => 0, 'end_seconds' => 3, 'caption' => 'Opening reveal', 'priority' => 'high', 'product_visibility' => 'high', 'stability' => 'high', 'visual_quality' => 'high', 'use_for_final_edit' => true],
                        ['start_seconds' => 8, 'end_seconds' => 11, 'caption' => 'Button detail', 'priority' => 'high', 'product_visibility' => 'high', 'stability' => 'medium', 'visual_quality' => 'medium', 'use_for_final_edit' => true],
                    ],
                ],
                'creative_brief' => [
                    'source_duration_seconds' => 55,
                    'target_duration_seconds' => 30,
                    'effective_target_duration_seconds' => 30,
                    'voiceover_duration_seconds' => 18.0,
                    'selected_cta' => 'Review details before buying.',
                ],
            ],
        );

        $ranges = $timeline['timeline_summary']['source_clip_ranges_used'];

        $this->assertGreaterThan(0, $timeline['timeline_summary']['fallback_even_segments_added']);
        $this->assertGreaterThanOrEqual(5, $timeline['timeline_summary']['candidate_clip_count']);
        $this->assertGreaterThanOrEqual(5, $timeline['timeline_summary']['unique_candidate_clip_count']);
        $this->assertGreaterThanOrEqual(30.0, $timeline['timeline_summary']['final_duration_seconds']);
        $this->assertSame(0, $timeline['timeline_summary']['repeated_clip_count']);
        $this->assertNull($timeline['timeline_summary']['repeated_clip_reason']);
        $this->assertSame('diverse_product_showcase_and_cta', $timeline['timeline_summary']['post_voiceover_visual_strategy']);
        $this->assertGreaterThan(0, $timeline['timeline_summary']['source_segments_used_after_voiceover']);
        $this->assertTrue(collect($ranges)->contains(fn (string $range): bool => str_starts_with($range, '49')));
    }

    public function test_repeated_captions_are_deduplicated_and_cta_only_appears_near_end(): void
    {
        $timeline = app(ShotstackTimelineBuilder::class)->build(
            sourceVideoUrl: 'https://assets.nusa.test/source.mp4',
            voiceoverUrl: null,
            input: [
                'target_duration_seconds' => 20,
                'source_video_to_campaign_summary' => [
                    'captions' => ['Easy setup', 'Easy setup', 'Review details before buying.'],
                    'hooks' => [['hook' => 'Easy setup']],
                ],
                'creative_brief' => [
                    'target_duration_seconds' => 20,
                    'selected_cta' => 'Review details before buying.',
                    'preferred_cuts' => [
                        ['start_seconds' => 0, 'end_seconds' => 4, 'caption' => 'Easy setup'],
                        ['start_seconds' => 8, 'end_seconds' => 12, 'caption' => 'Easy setup'],
                        ['start_seconds' => 16, 'end_seconds' => 20, 'caption' => 'Clear product view'],
                    ],
                ],
            ],
        );

        $schedule = $timeline['captions_plan']['caption_schedule'];
        $texts = array_map(fn (array $caption): string => $caption['text'], $schedule);
        $ctaItems = array_values(array_filter($schedule, fn (array $caption): bool => $caption['type'] === 'cta'));

        $this->assertGreaterThan(0, $timeline['timeline_summary']['repeated_captions_removed']);
        $this->assertSame(count($texts), count(array_unique($texts)));
        $this->assertCount(1, $ctaItems);
        $this->assertGreaterThanOrEqual($timeline['timeline_summary']['final_duration_seconds'] - 4.0, $ctaItems[0]['start']);
        $this->assertSame($ctaItems[0]['start'], $timeline['timeline_summary']['cta_caption_start_seconds']);
        $this->assertFalse($timeline['timeline_summary']['captions_overlapping']);
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
        $this->assertArrayHasKey('source_duration_seconds', $brief);

        $override = app(AutoVideoEditorCreativeBriefBuilder::class)->build([
            'cta' => 'Use my custom CTA.',
            'source_video_to_campaign_summary' => [
                'cta_options' => ['Ignore this when form CTA exists.'],
            ],
        ]);

        $this->assertSame('Use my custom CTA.', $override['selected_cta']);
    }
}
