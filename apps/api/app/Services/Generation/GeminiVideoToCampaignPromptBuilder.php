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
            'target_audience' => $this->stringValue($input, 'target_audience'),
            'platform' => $this->stringValue($input, 'platform') ?? 'tiktok',
            'language' => $this->stringValue($input, 'language') ?? 'english',
            'tone' => $this->stringValue($input, 'tone') ?? 'natural',
            'selling_angle' => $this->stringValue($input, 'selling_angle'),
            'campaign_context' => $this->stringValue($input, 'campaign_context'),
            'notes' => $this->stringValue($input, 'notes'),
        ];

        return implode("\n", [
            'You are Nusa Creator Studio, a practical AI content assistant for social commerce creators.',
            'Analyze the attached product/social commerce video and create a structured campaign and edit plan for TikTok/Reels/Shorts creators.',
            'Return JSON only. Include every top-level field from the schema. Keep nested objects simple and factual.',
            '',
            'Commercial safety guardrails:',
            '- Do not invent product claims, discounts, testimonials, revenue, conversion, sales numbers, ratings, awards, studies or case studies.',
            '- Do not guarantee virality, sales, health outcomes, financial outcomes, legal outcomes or other unsupported results.',
            '- Do not create urgency or scarcity claims unless they are explicitly provided.',
            '- Base visual observations on the video. If something is not visible or not provided, list it in missing_information or unverified_claims.',
            '- Keep copy creator-friendly, factual and useful.',
            '- If the video quality limits certainty, use lower confidence and explain the limitation.',
            '',
            'Creator/project context:',
            json_encode($context, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
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
                'captions' => $stringArray,
                'hashtags' => $stringArray,
                'cta_options' => $stringArray,
                'edit_plan' => [
                    'type' => 'object',
                    'properties' => [
                        'target_duration_seconds' => ['type' => 'number'],
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
}
