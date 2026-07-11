<?php

namespace App\Services\Generation;

class GeminiVideoToCampaignPromptBuilder
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function build(array $input): string
    {
        $context = [
            'product_name' => $this->stringValue($input, 'product_name'),
            'product_category' => $this->stringValue($input, 'product_category'),
            'product_description' => $this->stringValue($input, 'product_description'),
            'project_brief' => $this->stringValue($input, 'project_brief') ?? $this->stringValue($input, 'campaign_context'),
            'target_audience' => $this->stringValue($input, 'target_audience'),
            'platform' => $this->stringValue($input, 'platform') ?? 'tiktok',
            'language' => $this->stringValue($input, 'language') ?? 'english',
            'tone' => $this->stringValue($input, 'tone') ?? 'natural',
            'selling_angle' => $this->stringValue($input, 'selling_angle'),
            'notes' => $this->stringValue($input, 'notes'),
            'source_video_duration_seconds' => $this->numericValue($input, 'source_video_duration_seconds'),
            'desired_target_duration_seconds' => $this->numericValue($input, 'target_duration_seconds'),
            'future_auto_video_editor_settings' => [
                'editing_style' => $this->stringValue($input, 'editing_style'),
                'pacing' => $this->stringValue($input, 'pacing'),
                'caption_style' => $this->stringValue($input, 'caption_style'),
                'music_direction' => $this->stringValue($input, 'music_direction'),
                'cta' => $this->stringValue($input, 'cta'),
            ],
        ];

        return implode('
', [
            'You are Nusa Creator Studio, a practical AI content assistant for social commerce creators.',
            'Analyze the attached product/social commerce video and create a structured campaign plus a render-friendly edit plan for TikTok, Instagram Reels and YouTube Shorts.',
            'Return JSON only. Include every top-level field from the schema. Keep nested objects simple, factual and ready for a deterministic video renderer.',
            '',
            'Base creative direction:',
            '- Use a social commerce / affiliate creator use case, vertical short-form context and creator-friendly copy.',
            '- Produce practical edit instructions that can be rendered: concrete clips, captions, timing, CTA and narration guidance.',
            '- Prefer a clear opening / middle / closing structure instead of vague marketing text.',
            '- Use natural pacing for voiceover and short captions designed for mobile screens.',
            '',
            'Commercial safety guardrails:',
            '- Do not invent product claims, discounts, testimonials, revenue, conversion, sales numbers, ratings, awards, studies or case studies.',
            '- Do not guarantee virality, sales, health outcomes, financial outcomes, legal outcomes or other unsupported results.',
            '- Do not create urgency or scarcity claims unless they are explicitly provided.',
            '- Base visual observations on the video. If something is not visible or not provided, list it in missing_information or unverified_claims.',
            '- Mark uncertain details as missing or unverified instead of presenting them as facts.',
            '- Keep copy creator-friendly, factual and useful.',
            '- If the video quality limits certainty, use lower confidence and explain the limitation.',
            '',
            'Dynamic creative context:',
            json_encode($context, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            '',
            'Render-friendly edit plan requirements:',
            '- Recommend 3-5 usable cuts when possible. If the source video is very short, fewer cuts are acceptable, but still create hook, product/demo and CTA beats.',
            '- Each recommended cut and edit_plan.timeline item should include start_seconds, end_seconds, purpose, visual_reason, caption_suggestion and priority when possible.',
            '- Avoid clips shorter than 0.5 seconds unless they are intentional hook flashes.',
            '- Captions must be short, safe for vertical video, and 1-2 lines maximum. Avoid paragraphs.',
            '- Include narration_voice_script with natural spoken pacing for the requested language and tone.',
            '- Include estimated_narration_duration_seconds if you can infer it from the narration script.',
            '- Include editing_rhythm guidance such as slow, medium or fast, and a style such as UGC natural, premium clean, energetic ad or review style.',
            '- CTA options should be honest, generic and safe unless a specific verified offer is provided.',
            '',
            'Output language must match the requested language when possible: English, Indonesian or Spanish.',
            'Use numeric seconds for timestamps. Prefer short-form vertical video conventions.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        $stringArray = [
            'type' => 'array',
            'items' => ['type' => 'string'],
        ];
        $timedObject = [
            'type' => 'object',
            'properties' => [
                'start_seconds' => ['type' => 'number'],
                'end_seconds' => ['type' => 'number'],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'purpose' => ['type' => 'string'],
                'visual_reason' => ['type' => 'string'],
                'caption_suggestion' => ['type' => 'string'],
                'reason' => ['type' => 'string'],
                'observation' => ['type' => 'string'],
                'instruction' => ['type' => 'string'],
                'caption' => ['type' => 'string'],
                'confidence' => ['type' => 'string'],
                'priority' => ['type' => 'string'],
                'visual_quality' => ['type' => 'string'],
                'usable_for_edit' => ['type' => 'boolean'],
            ],
        ];

        return [
            'type' => 'object',
            'required' => ['video_summary', 'campaign_angle', 'hooks', 'narration_voice_script', 'edit_plan'],
            'properties' => [
                'video_summary' => ['type' => 'string'],
                'observed_product_features' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'feature' => ['type' => 'string'],
                            'evidence' => ['type' => 'string'],
                            'confidence' => ['type' => 'string'],
                        ],
                    ],
                ],
                'detected_product_moments' => ['type' => 'array', 'items' => $timedObject],
                'scene_observations' => ['type' => 'array', 'items' => $timedObject],
                'recommended_cuts' => ['type' => 'array', 'items' => $timedObject],
                'campaign_angle' => ['type' => 'string'],
                'hooks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'hook' => ['type' => 'string'],
                            'style' => ['type' => 'string'],
                            'best_for' => ['type' => 'string'],
                        ],
                    ],
                ],
                'narration_voice_script' => ['type' => 'string'],
                'estimated_narration_duration_seconds' => ['type' => 'number'],
                'captions' => $stringArray,
                'hashtags' => $stringArray,
                'cta_options' => $stringArray,
                'edit_plan' => [
                    'type' => 'object',
                    'properties' => [
                        'target_duration_seconds' => ['type' => 'number'],
                        'estimated_narration_duration_seconds' => ['type' => 'number'],
                        'editing_rhythm' => ['type' => 'string'],
                        'editing_style' => ['type' => 'string'],
                        'caption_style' => ['type' => 'string'],
                        'opening' => ['type' => 'string'],
                        'middle' => ['type' => 'string'],
                        'closing' => ['type' => 'string'],
                        'timeline' => ['type' => 'array', 'items' => $timedObject],
                    ],
                ],
                'compliance_notes' => $stringArray,
                'usage_notes' => $stringArray,
                'missing_information' => $stringArray,
                'unverified_claims' => $stringArray,
            ],
        ];
    }

    /** @param array<string, mixed> $input */
    private function stringValue(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $input */
    private function numericValue(array $input, string $key): int|float|null
    {
        $value = $input[$key] ?? null;

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return floor($number) === $number ? (int) $number : $number;
    }
}
