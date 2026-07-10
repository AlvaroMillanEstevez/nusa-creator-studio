<?php

namespace App\Services\Generation;

use App\Contracts\VideoToCampaignProvider;
use App\Exceptions\VideoToCampaign\VideoToCampaignProviderException;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;

class GeminiVideoToCampaignProvider implements VideoToCampaignProvider
{
    public function __construct(
        private readonly GeminiVideoToCampaignClient $client,
        private readonly GeminiVideoToCampaignPromptBuilder $prompt,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array
    {
        $apiKey = (string) config('video_to_campaign.gemini.api_key', '');
        $requestedModel = (string) config('video_to_campaign.gemini.model', '');

        if ($apiKey === '' || $requestedModel === '') {
            throw VideoToCampaignProviderException::missingConfiguration();
        }

        $asset = $this->sourceVideo($input);
        $this->ensureAllowedSize($asset);

        $analysis = $this->client->analyze(
            asset: $asset,
            prompt: $this->prompt->build($input),
            schema: $this->prompt->schema(),
        );
        $decoded = $this->decodeOutput($analysis['response']);
        $warnings = $decoded['warnings'];
        $normalized = $this->normalizedOutput($decoded['output'], $asset, $input, $warnings);
        $warnings = array_values(array_slice(array_unique($warnings), 0, 20));
        $usage = $this->tokenUsage($analysis['response']);
        $estimatedCost = $this->estimateCost($usage);
        $finalModel = $this->stringValue(data_get($analysis['response'], 'modelVersion'))
            ?? $this->stringValue(data_get($analysis['response'], 'model'))
            ?? $requestedModel;

        if ($warnings !== []) {
            Log::info('Gemini Video-to-Campaign response normalized with fallbacks.', [
                'provider' => 'gemini',
                'requested_model' => $requestedModel,
                'final_model' => $finalModel,
                'stage' => 'normalize_output',
                'response_shape_summary' => $decoded['shape_summary'],
                'normalization_warnings' => $warnings,
            ]);
        }

        return array_merge($normalized, [
            'source_video_asset_id' => $asset->getKey(),
            'provider_metadata' => [
                'provider' => 'gemini',
                'model' => $finalModel,
                'requested_model' => $requestedModel,
                'final_model' => $finalModel,
                'mode' => 'real',
                'real_video_analysis' => true,
                'response_shape_summary' => $decoded['shape_summary'],
                'normalized_with_fallbacks' => $warnings !== [],
                'normalization_warnings' => $warnings,
                'token_usage' => $usage,
                'estimated_cost_usd' => $estimatedCost,
                'latency_ms' => $analysis['latency_ms'],
                'file' => [
                    'source_video_asset_id' => $asset->getKey(),
                    'size_bytes' => $asset->size_bytes,
                    'mime_type' => $asset->mime_type,
                    'provider_file_deleted' => $analysis['provider_file_deleted'],
                    'provider_file_retention' => 'Temporary Gemini Files API upload. Raw provider file URI and private storage path are not stored in this API output.',
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function sourceVideo(array $input): MediaAsset
    {
        $id = $input['source_video_asset_id'] ?? null;

        if (! is_numeric($id)) {
            throw VideoToCampaignProviderException::fileUnavailable();
        }

        $asset = MediaAsset::query()
            ->whereKey((int) $id)
            ->where('type', 'video')
            ->first();

        if (! $asset instanceof MediaAsset) {
            throw VideoToCampaignProviderException::fileUnavailable();
        }

        return $asset;
    }

    private function ensureAllowedSize(MediaAsset $asset): void
    {
        $maxBytes = max(1, (int) config('video_to_campaign.gemini.max_file_size_mb', 50)) * 1024 * 1024;

        if ($asset->size_bytes !== null && $asset->size_bytes > $maxBytes) {
            throw VideoToCampaignProviderException::fileUnavailable();
        }
    }

    /**
     * @param  array<string, mixed>  $responseData
     * @return array{output: array<string, mixed>, shape_summary: array<string, mixed>, warnings: array<int, string>}
     */
    private function decodeOutput(array $responseData): array
    {
        $warnings = [];
        $shape = $this->responseShapeSummary($responseData);
        $candidates = $this->responseCandidates($responseData, $shape);
        $foundText = false;
        $lastText = null;

        foreach ($candidates as $candidate) {
            if (($candidate['type'] ?? null) === 'object' && is_array($candidate['value'] ?? null)) {
                $output = $candidate['value'];

                if ($this->looksLikeCampaignPayload($output)) {
                    $shape['parsed_json_top_level_keys'] = array_keys($output);

                    return ['output' => $output, 'shape_summary' => $shape, 'warnings' => $warnings];
                }
            }

            if (($candidate['type'] ?? null) !== 'text' || ! is_string($candidate['value'] ?? null)) {
                continue;
            }

            $foundText = true;
            $lastText = $candidate['value'];
            $output = $this->parseModelJsonText($candidate['value']);

            if ($output !== null) {
                $shape['text_found'] = true;
                $shape['parsed_json_top_level_keys'] = array_keys($output);

                return ['output' => $output, 'shape_summary' => $shape, 'warnings' => $warnings];
            }
        }

        $shape['text_found'] = $foundText;

        if (! $foundText) {
            $this->logResponseExtractionFailure('Gemini Video-to-Campaign provider returned an empty output.', 'gemini_unexpected_response_shape', $shape);

            throw VideoToCampaignProviderException::invalidResponse('gemini_unexpected_response_shape', [
                'provider' => 'gemini',
                'stage' => 'decode_output',
                'reason' => 'empty_output_text',
            ]);
        }

        $this->logResponseExtractionFailure('Gemini Video-to-Campaign provider returned invalid JSON.', 'gemini_invalid_json', $shape, $lastText);

        throw VideoToCampaignProviderException::invalidJson([
            'provider' => 'gemini',
            'stage' => 'decode_output',
            'output_length' => is_string($lastText) ? strlen($lastText) : 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $responseData
     * @return array<string, mixed>
     */
    private function responseShapeSummary(array $responseData): array
    {
        $candidates = $responseData['candidates'] ?? [];
        $candidateCount = is_array($candidates) ? count($candidates) : 0;
        $partCount = 0;

        if (is_array($candidates)) {
            foreach ($candidates as $candidate) {
                $parts = is_array($candidate) ? data_get($candidate, 'content.parts', []) : [];
                $partCount += is_array($parts) ? count($parts) : 0;
            }
        }

        return [
            'top_level_response_keys' => array_keys($responseData),
            'candidate_count' => $candidateCount,
            'part_count' => $partCount,
            'text_found' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $responseData
     * @param  array<string, mixed>  $shape
     * @return array<int, array{type: string, value: mixed}>
     */
    private function responseCandidates(array $responseData, array &$shape): array
    {
        $candidates = [];

        if ($this->looksLikeCampaignPayload($responseData)) {
            $candidates[] = ['type' => 'object', 'value' => $responseData];
        }

        foreach (['output_text', 'text'] as $key) {
            if (is_string($responseData[$key] ?? null)) {
                $shape['text_found'] = true;
                $candidates[] = ['type' => 'text', 'value' => $responseData[$key]];
            }
        }

        foreach (($responseData['outputs'] ?? []) as $output) {
            if (is_array($output) && $this->looksLikeCampaignPayload($output)) {
                $candidates[] = ['type' => 'object', 'value' => $output];
            }

            if (is_array($output) && is_string($output['text'] ?? null)) {
                $shape['text_found'] = true;
                $candidates[] = ['type' => 'text', 'value' => $output['text']];
            }
        }

        foreach (($responseData['output'] ?? []) as $outputItem) {
            if (! is_array($outputItem)) {
                continue;
            }

            if ($this->looksLikeCampaignPayload($outputItem)) {
                $candidates[] = ['type' => 'object', 'value' => $outputItem];
            }

            foreach (($outputItem['content'] ?? []) as $contentItem) {
                if (is_array($contentItem) && $this->looksLikeCampaignPayload($contentItem)) {
                    $candidates[] = ['type' => 'object', 'value' => $contentItem];
                }

                if (is_array($contentItem) && is_string($contentItem['text'] ?? null)) {
                    $shape['text_found'] = true;
                    $candidates[] = ['type' => 'text', 'value' => $contentItem['text']];
                }
            }
        }

        foreach (($responseData['candidates'] ?? []) as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            if ($this->looksLikeCampaignPayload($candidate)) {
                $candidates[] = ['type' => 'object', 'value' => $candidate];
            }

            $parts = data_get($candidate, 'content.parts', []);
            $textParts = [];

            if (! is_array($parts)) {
                continue;
            }

            foreach ($parts as $part) {
                if (! is_array($part)) {
                    continue;
                }

                if ($this->looksLikeCampaignPayload($part)) {
                    $candidates[] = ['type' => 'object', 'value' => $part];
                }

                foreach (['json', 'data', 'structured', 'object'] as $key) {
                    if (is_array($part[$key] ?? null) && $this->looksLikeCampaignPayload($part[$key])) {
                        $candidates[] = ['type' => 'object', 'value' => $part[$key]];
                    }
                }

                if (is_string($part['text'] ?? null)) {
                    $textParts[] = $part['text'];
                }
            }

            if ($textParts !== []) {
                $shape['text_found'] = true;
                $candidates[] = ['type' => 'text', 'value' => trim(implode("\n", $textParts))];
            }
        }

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function looksLikeCampaignPayload(array $value): bool
    {
        foreach (['video_summary', 'campaign_angle', 'hooks', 'edit_plan', 'narration_voice_script', 'recommended_cuts'] as $key) {
            if (array_key_exists($key, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseModelJsonText(string $text): ?array
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        $clean = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $clean = preg_replace('/\s*```$/', '', trim($clean)) ?? $clean;
        $decoded = $this->decodeJsonObject($clean);

        if ($decoded !== null) {
            return $decoded;
        }

        foreach ([['{', '}'], ['[', ']']] as [$startToken, $endToken]) {
            $start = strpos($clean, $startToken);
            $end = strrpos($clean, $endToken);

            if ($start === false || $end === false || $end <= $start) {
                continue;
            }

            $decoded = $this->decodeJsonObject(substr($clean, $start, $end - $start + 1));

            if ($decoded !== null) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(string $json): ?array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        if (array_is_list($decoded)) {
            return isset($decoded[0]) && is_array($decoded[0]) && ! array_is_list($decoded[0]) ? $decoded[0] : null;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $shape
     */
    private function logResponseExtractionFailure(string $message, string $reason, array $shape, ?string $modelText = null): void
    {
        $context = [
            'provider' => 'gemini',
            'model' => (string) config('video_to_campaign.gemini.model', ''),
            'stage' => 'decode_output',
            'reason' => $reason,
            'top_level_response_keys' => $shape['top_level_response_keys'] ?? [],
            'candidate_count' => $shape['candidate_count'] ?? 0,
            'part_count' => $shape['part_count'] ?? 0,
            'text_found' => (bool) ($shape['text_found'] ?? false),
            'parsed_json_top_level_keys' => $shape['parsed_json_top_level_keys'] ?? [],
        ];

        if ($modelText !== null && $this->canLogDebugResponseText()) {
            $context['model_text_excerpt'] = $this->safeModelTextExcerpt($modelText);
        }

        Log::warning($message, $context);
    }

    private function canLogDebugResponseText(): bool
    {
        return (bool) config('video_to_campaign.gemini.debug_response_log', false)
            && app()->environment('local');
    }

    private function safeModelTextExcerpt(string $text): string
    {
        $text = preg_replace('/gemini:\/\/[^\s"\']+/', '[redacted-provider-uri]', $text) ?? $text;
        $text = preg_replace('/files\/[A-Za-z0-9._\-]+/', 'files/[redacted]', $text) ?? $text;

        return Str::limit($text, 1500, '');
    }

    /**
     * @param  array<string, mixed>  $output
     * @param  array<string, mixed>  $input
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function normalizedOutput(array $output, MediaAsset $asset, array $input, array &$warnings): array
    {
        $videoSummary = $this->firstString($output, ['video_summary', 'summary', 'videoSummary'])
            ?? 'Video analysis completed.';

        if (! isset($output['video_summary'])) {
            $this->warn($warnings, 'video_summary_fallback_used');
        }

        $campaignAngle = $this->firstString($output, ['campaign_angle', 'angle', 'product_angle', 'campaignAngle'])
            ?? $this->campaignAngleFallback($input, $videoSummary);

        if (! isset($output['campaign_angle'])) {
            $this->warn($warnings, 'campaign_angle_fallback_used');
        }

        $features = $this->features($output['observed_product_features'] ?? $output['product_features'] ?? null, $warnings);
        $moments = $this->moments($output['detected_product_moments'] ?? $output['product_moments'] ?? $output['moments'] ?? null, $warnings);
        $observations = $this->observations($output['scene_observations'] ?? $output['observations'] ?? $output['scenes'] ?? null, $warnings);
        $rawEditPlan = $output['edit_plan'] ?? $output['editPlan'] ?? null;
        $cuts = $this->cuts($output['recommended_cuts'] ?? $output['cuts'] ?? $output['recommendedCuts'] ?? null, $warnings);

        if ($cuts === []) {
            $cuts = $this->cutsFromEditPlanSource($rawEditPlan, $warnings);
        }

        if ($cuts === []) {
            $cuts = $this->cutsFromMoments($moments, $warnings);
        }

        $editPlan = $this->editPlan($rawEditPlan, $asset, $videoSummary, $campaignAngle, $cuts, $moments, $warnings);
        $hooks = $this->hooks($output['hooks'] ?? $output['hook'] ?? null, $campaignAngle, $warnings);
        $narration = $this->narration($output, $videoSummary, $campaignAngle, $warnings);
        $captions = $this->stringList($output['captions'] ?? $output['caption'] ?? null, 8, ['caption', 'text', 'title']);

        if ($captions === []) {
            $captions = [$campaignAngle];
            $this->warn($warnings, 'captions_fallback_used');
        }

        $hashtags = $this->hashtags($output['hashtags'] ?? $output['hashtag'] ?? null, $warnings);
        $ctaOptions = $this->stringList($output['cta_options'] ?? $output['cta'] ?? $output['call_to_action'] ?? null, 6, ['cta', 'text', 'label']);

        if ($ctaOptions === []) {
            $ctaOptions = ['Review the product details before buying.'];
            $this->warn($warnings, 'cta_options_fallback_used');
        }

        $complianceNotes = $this->stringList($output['compliance_notes'] ?? null, 8, ['note', 'text']);

        if ($complianceNotes === []) {
            $complianceNotes = ['Verify product claims, pricing and availability before publishing.'];
            $this->warn($warnings, 'compliance_notes_default_used');
        }

        $usageNotes = $this->stringList($output['usage_notes'] ?? null, 8, ['note', 'text']);

        if ($usageNotes === []) {
            $usageNotes = ['Use this as a draft campaign plan and adapt it with real product details.'];
            $this->warn($warnings, 'usage_notes_default_used');
        }

        return [
            'video_summary' => $videoSummary,
            'observed_product_features' => $features,
            'detected_product_moments' => $moments,
            'scene_observations' => $observations,
            'recommended_cuts' => $cuts,
            'campaign_angle' => $campaignAngle,
            'hooks' => $hooks,
            'narration_voice_script' => $narration,
            'captions' => $captions,
            'hashtags' => $hashtags,
            'cta_options' => $ctaOptions,
            'edit_plan' => $editPlan,
            'compliance_notes' => $complianceNotes,
            'usage_notes' => $usageNotes,
            'missing_information' => $this->stringList($output['missing_information'] ?? null, 8, ['item', 'text', 'note']),
            'unverified_claims' => $this->stringList($output['unverified_claims'] ?? null, 8, ['claim', 'text', 'note']),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function campaignAngleFallback(array $input, string $videoSummary): string
    {
        $productName = $this->stringValue($input['product_name'] ?? null);

        if ($productName !== null) {
            return "Turn {$productName} into a clear, honest short-form product campaign.";
        }

        return Str::limit($videoSummary, 220, '');
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $keys
     */
    private function firstString(array $values, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->stringValue($values[$key] ?? null);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function warn(array &$warnings, string $warning): void
    {
        if (! in_array($warning, $warnings, true)) {
            $warnings[] = $warning;
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function items(mixed $items): array
    {
        if ($items === null || $items === '') {
            return [];
        }

        if (is_string($items)) {
            return [$items];
        }

        if (! is_array($items)) {
            return [];
        }

        return array_is_list($items) ? $items : [$items];
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<int, array{feature: string, evidence: string, confidence: string}>
     */
    private function features(mixed $items, array &$warnings): array
    {
        $features = [];

        foreach ($this->items($items) as $item) {
            if (is_string($item)) {
                $feature = $this->stringValue($item);

                if ($feature !== null) {
                    $features[] = [
                        'feature' => $feature,
                        'evidence' => 'Provided by the video analysis summary.',
                        'confidence' => 'medium',
                    ];
                    $this->warn($warnings, 'observed_product_features_strings_normalized');
                }

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $feature = $this->stringValue($item['feature'] ?? $item['name'] ?? $item['title'] ?? $item['text'] ?? null);
            $evidence = $this->stringValue($item['evidence'] ?? $item['description'] ?? $item['observation'] ?? null);

            if ($feature === null && $evidence === null) {
                continue;
            }

            $features[] = [
                'feature' => $feature ?? Str::limit((string) $evidence, 120, ''),
                'evidence' => $evidence ?? 'Visible or implied in the source video analysis.',
                'confidence' => $this->enumValue($item['confidence'] ?? null, ['low', 'medium', 'high'], 'medium'),
            ];
        }

        return array_slice($features, 0, 8);
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function moments(mixed $items, array &$warnings): array
    {
        $moments = [];

        foreach ($this->items($items) as $index => $item) {
            if (is_string($item)) {
                $text = $this->stringValue($item);

                if ($text !== null) {
                    $moments[] = [
                        'start_seconds' => null,
                        'end_seconds' => null,
                        'title' => 'Product moment',
                        'description' => $text,
                        'why_it_matters' => 'Useful moment for campaign structure.',
                        'visual_quality' => 'medium',
                    ];
                    $this->warn($warnings, 'detected_product_moments_strings_normalized');
                }

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            [$start, $end] = $this->timeRange($item);
            $title = $this->stringValue($item['title'] ?? $item['moment'] ?? $item['scene'] ?? $item['label'] ?? null);
            $description = $this->stringValue($item['description'] ?? $item['campaign_use'] ?? $item['why_it_matters'] ?? $item['observation'] ?? $item['text'] ?? null);

            $moments[] = [
                'start_seconds' => $start,
                'end_seconds' => $end,
                'title' => $title ?? 'Product moment '.($index + 1),
                'description' => $description ?? 'Useful source video moment.',
                'why_it_matters' => $this->stringValue($item['why_it_matters'] ?? null) ?? 'Useful moment for campaign structure.',
                'visual_quality' => $this->enumValue($item['visual_quality'] ?? $item['quality'] ?? null, ['low', 'medium', 'high'], 'medium'),
            ];

            if ($start === null && $end === null) {
                $this->warn($warnings, 'detected_product_moments_missing_timestamps');
            }
        }

        return array_slice($moments, 0, 10);
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function observations(mixed $items, array &$warnings): array
    {
        $observations = [];

        foreach ($this->items($items) as $item) {
            if (is_string($item)) {
                $observation = $this->stringValue($item);

                if ($observation !== null) {
                    $observations[] = [
                        'start_seconds' => null,
                        'end_seconds' => null,
                        'observation' => $observation,
                        'usable_for_edit' => true,
                    ];
                    $this->warn($warnings, 'scene_observations_strings_normalized');
                }

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            [$start, $end] = $this->timeRange($item);
            $observation = $this->stringValue($item['observation'] ?? $item['description'] ?? $item['scene'] ?? $item['text'] ?? null);

            if ($observation === null) {
                continue;
            }

            $observations[] = [
                'start_seconds' => $start,
                'end_seconds' => $end,
                'observation' => $observation,
                'usable_for_edit' => (bool) ($item['usable_for_edit'] ?? true),
            ];
        }

        return array_slice($observations, 0, 12);
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function cuts(mixed $items, array &$warnings): array
    {
        $cuts = [];

        foreach ($this->items($items) as $item) {
            if (is_string($item)) {
                $reason = $this->stringValue($item);

                if ($reason !== null) {
                    $cuts[] = [
                        'start_seconds' => null,
                        'end_seconds' => null,
                        'reason' => $reason,
                        'priority' => 'medium',
                    ];
                    $this->warn($warnings, 'recommended_cuts_strings_normalized');
                }

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            [$start, $end] = $this->timeRange($item);
            $reason = $this->stringValue($item['reason'] ?? $item['cut'] ?? $item['instruction'] ?? $item['description'] ?? $item['text'] ?? null);

            if ($reason === null) {
                continue;
            }

            $cuts[] = [
                'start_seconds' => $start,
                'end_seconds' => $end,
                'reason' => $reason,
                'priority' => $this->enumValue($item['priority'] ?? null, ['low', 'medium', 'high'], 'medium'),
            ];
        }

        return array_slice($cuts, 0, 12);
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function cutsFromEditPlanSource(mixed $editPlan, array &$warnings): array
    {
        $entries = $this->editPlanEntries($editPlan);

        if ($entries === []) {
            return [];
        }

        $cuts = $this->cuts($entries, $warnings);

        if ($cuts !== []) {
            $this->warn($warnings, 'recommended_cuts_derived_from_edit_plan');
        }

        return $cuts;
    }

    /**
     * @param  array<int, array<string, mixed>>  $moments
     * @param  array<int, string>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function cutsFromMoments(array $moments, array &$warnings): array
    {
        if ($moments === []) {
            return [];
        }

        $cuts = array_map(fn (array $moment): array => [
            'start_seconds' => $moment['start_seconds'] ?? null,
            'end_seconds' => $moment['end_seconds'] ?? null,
            'reason' => 'Use this moment: '.($moment['title'] ?? $moment['description'] ?? 'Product moment'),
            'priority' => 'medium',
        ], $moments);

        $this->warn($warnings, 'recommended_cuts_derived_from_detected_moments');

        return array_slice($cuts, 0, 8);
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<int, array{hook: string, style: string, best_for: string}>
     */
    private function hooks(mixed $items, string $campaignAngle, array &$warnings): array
    {
        $hooks = [];

        foreach ($this->items($items) as $item) {
            if (is_string($item)) {
                $hook = $this->stringValue($item);

                if ($hook !== null) {
                    $hooks[] = ['hook' => $hook, 'style' => 'direct', 'best_for' => 'Opening hook'];
                    $this->warn($warnings, 'hooks_strings_normalized');
                }

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $hook = $this->stringValue($item['hook'] ?? $item['text'] ?? $item['title'] ?? null);

            if ($hook === null) {
                continue;
            }

            $hooks[] = [
                'hook' => $hook,
                'style' => $this->stringValue($item['style'] ?? null) ?? 'direct',
                'best_for' => $this->stringValue($item['best_for'] ?? null) ?? 'Opening hook',
            ];
        }

        if ($hooks === []) {
            $hooks[] = [
                'hook' => Str::limit($campaignAngle, 140, ''),
                'style' => 'direct',
                'best_for' => 'Opening hook',
            ];
            $this->warn($warnings, 'hooks_fallback_used');
        }

        return array_slice($hooks, 0, 8);
    }

    /**
     * @param  array<string, mixed>  $output
     * @param  array<int, string>  $warnings
     */
    private function narration(array $output, string $videoSummary, string $campaignAngle, array &$warnings): string
    {
        foreach (['narration_voice_script', 'voiceover_script', 'narration_script', 'script', 'main_script'] as $key) {
            $value = $output[$key] ?? null;
            $text = $this->stringValue($value);

            if ($text !== null) {
                return $text;
            }

            if (is_array($value)) {
                $text = $this->stringValue($value['voiceover_script'] ?? $value['voiceover'] ?? $value['script'] ?? null);

                if ($text !== null) {
                    return $text;
                }

                $sceneLines = $this->stringList($value['scenes'] ?? [], 8, ['voiceover', 'narration', 'text']);

                if ($sceneLines !== []) {
                    $this->warn($warnings, 'narration_derived_from_scene_voiceovers');

                    return implode("\n", $sceneLines);
                }
            }
        }

        $this->warn($warnings, 'narration_voice_script_fallback_used');

        return implode(' ', array_filter([
            Str::limit($videoSummary, 180, ''),
            Str::limit($campaignAngle, 180, ''),
            'Check the product details before buying.',
        ]));
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    private function stringList(mixed $items, int $maxItems, array $keys = ['text', 'value', 'label']): array
    {
        $values = [];

        foreach ($this->items($items) as $item) {
            if (is_string($item) || is_numeric($item)) {
                $value = $this->stringValue($item);

                if ($value !== null) {
                    $values[] = $value;
                }

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            foreach ($keys as $key) {
                $value = $this->stringValue($item[$key] ?? null);

                if ($value !== null) {
                    $values[] = $value;
                    break;
                }
            }
        }

        return array_values(array_slice(array_unique($values), 0, $maxItems));
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<int, string>
     */
    private function hashtags(mixed $items, array &$warnings): array
    {
        $raw = [];

        foreach ($this->items($items) as $item) {
            if (is_string($item)) {
                array_push($raw, ...preg_split('/[\s,]+/', $item, -1, PREG_SPLIT_NO_EMPTY));

                continue;
            }

            if (is_array($item)) {
                $value = $this->stringValue($item['hashtag'] ?? $item['tag'] ?? $item['text'] ?? null);

                if ($value !== null) {
                    $raw[] = $value;
                }
            }
        }

        $hashtags = [];

        foreach ($raw as $value) {
            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $value = ltrim($value, '#');
            $value = preg_replace('/[^\pL\pN_]+/u', '', Str::studly($value)) ?? '';

            if ($value !== '') {
                $hashtags[] = '#'.$value;
            }
        }

        if ($hashtags === []) {
            $hashtags = ['#ProductVideo', '#CreatorTools', '#SocialCommerce'];
            $this->warn($warnings, 'hashtags_fallback_used');
        }

        return array_values(array_slice(array_unique($hashtags), 0, 12));
    }

    /**
     * @param  array<int, array<string, mixed>>  $cuts
     * @param  array<int, array<string, mixed>>  $moments
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function editPlan(
        mixed $item,
        MediaAsset $asset,
        string $videoSummary,
        string $campaignAngle,
        array $cuts,
        array $moments,
        array &$warnings,
    ): array {
        $entries = $this->editPlanEntries($item);
        $rawPlan = is_array($item) && ! array_is_list($item) ? $item : [];

        if (is_string($item)) {
            $this->warn($warnings, 'edit_plan_string_normalized');
        }

        if ($entries === [] && $cuts !== []) {
            $entries = $cuts;
            $this->warn($warnings, 'edit_plan_derived_from_recommended_cuts');
        }

        if ($entries === [] && $moments !== []) {
            $entries = $moments;
            $this->warn($warnings, 'edit_plan_derived_from_detected_moments');
        }

        if ($entries === []) {
            $entries = [[
                'start_seconds' => 0,
                'end_seconds' => 3,
                'instruction' => 'Open with the clearest product shot and explain the product context honestly.',
                'caption' => 'Product context',
            ]];
            $this->warn($warnings, 'edit_plan_minimal_fallback_used');
        }

        $timeline = [];

        foreach ($entries as $index => $entry) {
            $timelineItem = $this->timelineItem($entry, $index);

            if ($timelineItem !== null) {
                $timeline[] = $timelineItem;
            }
        }

        if ($timeline === []) {
            $timeline[] = [
                'start_seconds' => 0,
                'end_seconds' => 3,
                'instruction' => 'Open with the clearest product shot and explain the product context honestly.',
                'caption' => 'Product context',
            ];
            $this->warn($warnings, 'edit_plan_timeline_fallback_used');
        }

        $targetDuration = $this->numberValue($rawPlan['target_duration_seconds'] ?? $rawPlan['duration_seconds'] ?? $rawPlan['duration'] ?? null)
            ?? $this->durationFromTimeline($timeline)
            ?? 30.0;

        return [
            'title' => $this->stringValue($rawPlan['title'] ?? null) ?? 'Video-to-Campaign edit plan',
            'format' => 'vertical_9_16',
            'platform' => 'tiktok',
            'target_duration_seconds' => max(1, $targetDuration),
            'opening' => $this->stringValue($rawPlan['opening'] ?? null) ?? Str::limit($videoSummary, 180, ''),
            'middle' => $this->stringValue($rawPlan['middle'] ?? null) ?? Str::limit($campaignAngle, 180, ''),
            'closing' => $this->stringValue($rawPlan['closing'] ?? null) ?? 'Close with a factual CTA and remind viewers to check the product details.',
            'timeline' => array_slice($timeline, 0, 12),
            'source_video_name' => $asset->original_name ?: 'uploaded source video',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function editPlanEntries(mixed $item): array
    {
        if (is_string($item)) {
            return [$item];
        }

        if (! is_array($item)) {
            return [];
        }

        if (array_is_list($item)) {
            return $item;
        }

        foreach (['timeline', 'scenes', 'clips', 'cuts', 'steps'] as $key) {
            if (is_array($item[$key] ?? null)) {
                return $this->items($item[$key]);
            }
        }

        foreach (['instruction', 'action', 'description', 'text', 'opening'] as $key) {
            if ($this->stringValue($item[$key] ?? null) !== null) {
                return [$item];
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function timelineItem(mixed $entry, int $index): ?array
    {
        if (is_string($entry)) {
            $instruction = $this->stringValue($entry);

            return $instruction === null ? null : [
                'start_seconds' => null,
                'end_seconds' => null,
                'instruction' => $instruction,
                'caption' => '',
            ];
        }

        if (! is_array($entry)) {
            return null;
        }

        [$start, $end] = $this->timeRange($entry);
        $instruction = $this->stringValue(
            $entry['instruction']
            ?? $entry['action']
            ?? $entry['description']
            ?? $entry['reason']
            ?? $entry['voiceover']
            ?? $entry['narration']
            ?? $entry['text']
            ?? $entry['title']
            ?? $entry['scene']
            ?? null,
        );

        if ($instruction === null) {
            $instruction = 'Use this source video moment in the campaign edit.';
        }

        return [
            'start_seconds' => $start,
            'end_seconds' => $end,
            'instruction' => $instruction,
            'caption' => $this->stringValue($entry['caption'] ?? $entry['on_screen_text'] ?? $entry['text_overlay'] ?? null) ?? '',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $timeline
     */
    private function durationFromTimeline(array $timeline): ?float
    {
        $max = null;

        foreach ($timeline as $item) {
            $end = $this->numberValue($item['end_seconds'] ?? null);

            if ($end !== null) {
                $max = $max === null ? $end : max($max, $end);
            }
        }

        return $max;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{0: float|null, 1: float|null}
     */
    private function timeRange(array $item): array
    {
        $start = $this->numberValue($item['start_seconds'] ?? $item['start'] ?? $item['start_time'] ?? null);
        $end = $this->numberValue($item['end_seconds'] ?? $item['end'] ?? $item['end_time'] ?? null);

        if ($start !== null || $end !== null) {
            return [$start, $end];
        }

        foreach (['timestamp', 'time_range', 'timeRange', 'time', 'range'] as $key) {
            if (! is_string($item[$key] ?? null)) {
                continue;
            }

            preg_match_all('/\d+(?:\.\d+)?/', $item[$key], $matches);
            $values = array_map('floatval', $matches[0] ?? []);

            if (count($values) >= 2) {
                return [$values[0], $values[1]];
            }

            if (count($values) === 1) {
                return [$values[0], null];
            }
        }

        return [null, null];
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, 4000, '');
    }

    private function numberValue(mixed $value): ?float
    {
        return is_numeric($value) ? max(0, (float) $value) : null;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function enumValue(mixed $value, array $allowed, string $fallback): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    /**
     * @param  array<string, mixed>  $responseData
     * @return array<string, int>|null
     */
    private function tokenUsage(array $responseData): ?array
    {
        $usage = $responseData['usage'] ?? $responseData['usageMetadata'] ?? null;

        if (! is_array($usage)) {
            return null;
        }

        $inputTokens = (int) ($usage['input_tokens']
            ?? $usage['prompt_tokens']
            ?? $usage['promptTokenCount']
            ?? 0);
        $outputTokens = (int) ($usage['output_tokens']
            ?? $usage['completion_tokens']
            ?? $usage['candidatesTokenCount']
            ?? 0);
        $totalTokens = (int) ($usage['total_tokens']
            ?? $usage['totalTokenCount']
            ?? ($inputTokens + $outputTokens));

        return [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $totalTokens,
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

        $inputRate = config('video_to_campaign.gemini.input_cost_per_1m_tokens');
        $outputRate = config('video_to_campaign.gemini.output_cost_per_1m_tokens');

        if (! is_numeric($inputRate) || ! is_numeric($outputRate)) {
            return null;
        }

        $inputCost = (($usage['input_tokens'] ?? 0) / 1_000_000) * (float) $inputRate;
        $outputCost = (($usage['output_tokens'] ?? 0) / 1_000_000) * (float) $outputRate;

        return round($inputCost + $outputCost, 6);
    }
}
