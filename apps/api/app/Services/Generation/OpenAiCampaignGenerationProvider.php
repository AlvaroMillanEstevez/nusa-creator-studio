<?php

namespace App\Services\Generation;

use App\Contracts\CampaignGenerationProvider;
use App\Data\Campaign\CampaignGenerationRequestData;
use App\Data\Campaign\CampaignGenerationResultData;
use App\Exceptions\Campaign\CampaignGenerationProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;

class OpenAiCampaignGenerationProvider implements CampaignGenerationProvider
{
    public function generate(CampaignGenerationRequestData $data): CampaignGenerationResultData
    {
        $apiKey = (string) config('campaign.openai.api_key', '');
        $model = (string) config('campaign.openai.campaign_model', 'gpt-5.4-nano');

        if ($apiKey === '' || $model === '') {
            throw CampaignGenerationProviderException::missingConfiguration();
        }

        [$responseData, $usedModel] = $this->requestWithFallback($data, $model, $apiKey);
        $output = $this->decodeOutput($responseData);
        $usage = $this->tokenUsage($responseData);

        return $this->resultData(
            output: $output,
            model: $usedModel,
            usage: $usage,
            responseId: is_string($responseData['id'] ?? null) ? $responseData['id'] : null,
        );
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function requestWithFallback(
        CampaignGenerationRequestData $data,
        string $model,
        string $apiKey,
    ): array {
        try {
            return [$this->request($data, $model, $apiKey), $model];
        } catch (CampaignGenerationProviderException $exception) {
            $fallbackModel = (string) config('campaign.openai.campaign_fallback_model', '');

            if ($fallbackModel === '' || $fallbackModel === $model) {
                throw $exception;
            }

            return [$this->request($data, $fallbackModel, $apiKey), $fallbackModel];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function request(
        CampaignGenerationRequestData $data,
        string $model,
        string $apiKey,
    ): array {
        $payload = $this->payload($data, $model);

        try {
            $response = Http::baseUrl((string) config('campaign.openai.base_url', 'https://api.openai.com/v1'))
                ->timeout((int) config('campaign.openai.timeout_seconds', 60))
                ->acceptJson()
                ->withToken($apiKey)
                ->post('/responses', $payload);
        } catch (ConnectionException) {
            throw CampaignGenerationProviderException::requestFailed();
        }

        if (! $response->successful()) {
            throw CampaignGenerationProviderException::requestFailed();
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw CampaignGenerationProviderException::invalidResponse();
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CampaignGenerationRequestData $data, string $model): array
    {
        return [
            'model' => $model,
            'input' => [
                [
                    'role' => 'developer',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $this->developerInstructions(),
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $this->userPrompt($data),
                        ],
                    ],
                ],
            ],
            'max_output_tokens' => (int) config('campaign.max_output_tokens', 2500),
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'nusa_affiliate_campaign',
                    'strict' => true,
                    'schema' => $this->schema(),
                ],
            ],
        ];
    }

    private function developerInstructions(): string
    {
        return implode("\n", [
            'You create practical social commerce affiliate campaign scripts for Nusa Creator Studio.',
            'Return only JSON that matches the provided schema.',
            'Use the requested language: English, Indonesian, or Spanish.',
            'Keep all claims honest, safe, generic, and based only on the user-provided product details.',
            'Do not invent fake metrics, fake testimonials, fake customer logos, revenue, ad spend, user counts, ratings, awards, studies, or case studies.',
            'Do not promise guaranteed viral results, guaranteed sales, guaranteed income, medical results, financial results, legal outcomes, or other unsupported claims.',
            'Do not create urgency or scarcity claims unless explicitly provided by the user.',
            'If a product detail needs verification, say to verify product details before publishing.',
            'Make the voiceover_ready_script clean enough to copy directly into a TTS or voiceover workflow.',
        ]);
    }

    private function userPrompt(CampaignGenerationRequestData $data): string
    {
        return implode("\n", [
            'Create one affiliate campaign package.',
            'Product name: '.$data->productName,
            'Product description: '.$data->productDescription,
            'Target audience: '.$data->targetAudience,
            'Language: '.$data->language,
            'Tone: '.$data->tone,
            'Platform: '.$data->platform,
            'Goal: '.$data->goal,
            'Additional notes: '.($data->notes ?: 'None provided.'),
            'Use short-form vertical video conventions. Keep it useful and believable.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        $stringList = [
            'type' => 'array',
            'minItems' => 1,
            'maxItems' => 10,
            'items' => ['type' => 'string'],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'campaign_title',
                'product_angle',
                'target_audience_summary',
                'hooks',
                'main_script',
                'voiceover_ready_script',
                'captions',
                'hashtags',
                'cta_options',
                'compliance_notes',
                'usage_notes',
            ],
            'properties' => [
                'campaign_title' => ['type' => 'string'],
                'product_angle' => ['type' => 'string'],
                'target_audience_summary' => ['type' => 'string'],
                'hooks' => [
                    'type' => 'array',
                    'minItems' => 5,
                    'maxItems' => 5,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['label', 'text', 'intent'],
                        'properties' => [
                            'label' => ['type' => 'string'],
                            'text' => ['type' => 'string'],
                            'intent' => ['type' => 'string'],
                        ],
                    ],
                ],
                'main_script' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['duration_seconds', 'voiceover_script', 'on_screen_text', 'scenes'],
                    'properties' => [
                        'duration_seconds' => ['type' => 'integer'],
                        'voiceover_script' => ['type' => 'string'],
                        'on_screen_text' => $stringList,
                        'scenes' => [
                            'type' => 'array',
                            'minItems' => 3,
                            'maxItems' => 6,
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => [
                                    'time_range',
                                    'visual_direction',
                                    'voiceover',
                                    'on_screen_text',
                                ],
                                'properties' => [
                                    'time_range' => ['type' => 'string'],
                                    'visual_direction' => ['type' => 'string'],
                                    'voiceover' => ['type' => 'string'],
                                    'on_screen_text' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
                'voiceover_ready_script' => ['type' => 'string'],
                'captions' => $stringList,
                'hashtags' => [
                    'type' => 'array',
                    'minItems' => 5,
                    'maxItems' => 12,
                    'items' => ['type' => 'string'],
                ],
                'cta_options' => $stringList,
                'compliance_notes' => $stringList,
                'usage_notes' => $stringList,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $responseData
     * @return array<string, mixed>
     */
    private function decodeOutput(array $responseData): array
    {
        $outputText = $this->outputText($responseData);

        if ($outputText === '') {
            throw CampaignGenerationProviderException::invalidResponse();
        }

        try {
            $decoded = json_decode($outputText, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw CampaignGenerationProviderException::invalidResponse();
        }

        if (! is_array($decoded)) {
            throw CampaignGenerationProviderException::invalidResponse();
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $responseData
     */
    private function outputText(array $responseData): string
    {
        if (is_string($responseData['output_text'] ?? null)) {
            return trim($responseData['output_text']);
        }

        $parts = [];

        foreach (($responseData['output'] ?? []) as $outputItem) {
            if (! is_array($outputItem)) {
                continue;
            }

            foreach (($outputItem['content'] ?? []) as $contentItem) {
                if (! is_array($contentItem)) {
                    continue;
                }

                if (($contentItem['type'] ?? null) === 'output_text' && is_string($contentItem['text'] ?? null)) {
                    $parts[] = $contentItem['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    /**
     * @param  array<string, mixed>  $output
     * @param  array<string, int>|null  $usage
     */
    private function resultData(
        array $output,
        string $model,
        ?array $usage,
        ?string $responseId,
    ): CampaignGenerationResultData {
        $campaignTitle = $this->requiredString($output, 'campaign_title');
        $productAngle = $this->requiredString($output, 'product_angle');
        $targetAudienceSummary = $this->requiredString($output, 'target_audience_summary');
        $mainScript = $this->mainScript($output['main_script'] ?? null);
        $voiceoverReadyScript = $this->requiredString($output, 'voiceover_ready_script');
        $hooks = $this->hooks($output['hooks'] ?? null);
        $captions = $this->stringList($output['captions'] ?? null, minItems: 1, maxItems: 5);
        $hashtags = $this->stringList($output['hashtags'] ?? null, minItems: 5, maxItems: 12);
        $ctaOptions = $this->stringList($output['cta_options'] ?? null, minItems: 1, maxItems: 5);
        $complianceNotes = $this->stringList($output['compliance_notes'] ?? null, minItems: 1, maxItems: 6);
        $usageNotes = $this->stringList($output['usage_notes'] ?? null, minItems: 1, maxItems: 6);

        return new CampaignGenerationResultData(
            provider: 'openai',
            providerModel: $model,
            campaignTitle: $campaignTitle,
            productAngle: $productAngle,
            targetAudienceSummary: $targetAudienceSummary,
            hooks: $hooks,
            mainScript: $mainScript,
            voiceoverReadyScript: $voiceoverReadyScript,
            sceneBreakdown: $mainScript['scenes'],
            captions: $captions,
            hashtags: $hashtags,
            ctaOptions: $ctaOptions,
            complianceNotes: $complianceNotes,
            usageNotes: $usageNotes,
            tokenUsage: $usage,
            estimatedCostUsd: $this->estimateCost($usage),
            metadata: [
                'response_id' => $responseId,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function requiredString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw CampaignGenerationProviderException::invalidResponse();
        }

        return Str::limit(trim($value), 2000, '');
    }

    /**
     * @return array<int, array{label: string, text: string, intent: string}>
     */
    private function hooks(mixed $hooks): array
    {
        if (! is_array($hooks)) {
            throw CampaignGenerationProviderException::invalidResponse();
        }

        $normalized = array_values(array_filter(array_map(function (mixed $hook): ?array {
            if (! is_array($hook)) {
                return null;
            }

            $text = $this->stringValue($hook['text'] ?? null);

            if ($text === null) {
                return null;
            }

            return [
                'label' => $this->stringValue($hook['label'] ?? null) ?? 'Hook',
                'text' => $text,
                'intent' => $this->stringValue($hook['intent'] ?? null) ?? 'Capture attention honestly.',
            ];
        }, $hooks)));

        if (count($normalized) < 3) {
            throw CampaignGenerationProviderException::invalidResponse();
        }

        return array_slice($normalized, 0, 5);
    }

    /**
     * @return array{duration_seconds: int, voiceover_script: string, on_screen_text: array<int, string>, scenes: array<int, array{time_range: string, visual_direction: string, voiceover: string, on_screen_text: string}>}
     */
    private function mainScript(mixed $script): array
    {
        if (! is_array($script)) {
            throw CampaignGenerationProviderException::invalidResponse();
        }

        $duration = $script['duration_seconds'] ?? null;
        $voiceoverScript = $this->stringValue($script['voiceover_script'] ?? null);
        $onScreenText = $this->stringList($script['on_screen_text'] ?? null, minItems: 1, maxItems: 10);
        $scenes = $this->scenes($script['scenes'] ?? null);

        if (! is_numeric($duration) || $voiceoverScript === null) {
            throw CampaignGenerationProviderException::invalidResponse();
        }

        return [
            'duration_seconds' => max(5, (int) $duration),
            'voiceover_script' => $voiceoverScript,
            'on_screen_text' => $onScreenText,
            'scenes' => $scenes,
        ];
    }

    /**
     * @return array<int, array{time_range: string, visual_direction: string, voiceover: string, on_screen_text: string}>
     */
    private function scenes(mixed $scenes): array
    {
        if (! is_array($scenes)) {
            throw CampaignGenerationProviderException::invalidResponse();
        }

        $normalized = array_values(array_filter(array_map(function (mixed $scene): ?array {
            if (! is_array($scene)) {
                return null;
            }

            $timeRange = $this->stringValue($scene['time_range'] ?? null);
            $visualDirection = $this->stringValue($scene['visual_direction'] ?? null);
            $voiceover = $this->stringValue($scene['voiceover'] ?? null);
            $onScreenText = $this->stringValue($scene['on_screen_text'] ?? null);

            if ($timeRange === null || $visualDirection === null || $voiceover === null || $onScreenText === null) {
                return null;
            }

            return [
                'time_range' => $timeRange,
                'visual_direction' => $visualDirection,
                'voiceover' => $voiceover,
                'on_screen_text' => $onScreenText,
            ];
        }, $scenes)));

        if (count($normalized) < 3) {
            throw CampaignGenerationProviderException::invalidResponse();
        }

        return array_slice($normalized, 0, 6);
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $values, int $minItems, int $maxItems): array
    {
        if (! is_array($values)) {
            throw CampaignGenerationProviderException::invalidResponse();
        }

        $normalized = array_values(array_filter(array_map(
            fn (mixed $value): ?string => $this->stringValue($value),
            $values,
        )));

        if (count($normalized) < $minItems) {
            throw CampaignGenerationProviderException::invalidResponse();
        }

        return array_slice($normalized, 0, $maxItems);
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, 2000, '');
    }

    /**
     * @param  array<string, mixed>  $responseData
     * @return array<string, int>|null
     */
    private function tokenUsage(array $responseData): ?array
    {
        $usage = $responseData['usage'] ?? null;

        if (! is_array($usage)) {
            return null;
        }

        return [
            'input_tokens' => (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0),
            'output_tokens' => (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0),
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
        ];
    }

    /**
     * @param  array<string, int>|null  $usage
     */
    private function estimateCost(?array $usage): ?float
    {
        if ($usage === null) {
            return null;
        }

        $inputRate = config('campaign.openai.input_cost_per_1m_tokens');
        $outputRate = config('campaign.openai.output_cost_per_1m_tokens');

        if (! is_numeric($inputRate) || ! is_numeric($outputRate)) {
            return null;
        }

        $inputCost = (($usage['input_tokens'] ?? 0) / 1_000_000) * (float) $inputRate;
        $outputCost = (($usage['output_tokens'] ?? 0) / 1_000_000) * (float) $outputRate;

        return round($inputCost + $outputCost, 6);
    }
}
